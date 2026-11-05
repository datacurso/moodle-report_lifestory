<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Student payload builder for report_lifestory.
 *
 * @package     report_lifestory
 * @copyright   2025 Datacurso
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_lifestory\local;

/**
 * Creates payloads for CSV export and AI requests.
 */
class payload_builder {
    /**
     * Cleans and returns safe feedback text from a grade object.
     *
     * @param \stdClass|null $grade Grade record.
     * @return string Cleaned feedback text.
     */
    private static function safe_feedback($grade): string {
        if (!$grade || empty($grade->feedback)) {
            return '';
        }
        return trim(strip_tags((string)$grade->feedback));
    }

    /**
     * Build the range label and the percentage of a grade within its bounds.
     *
     * @param float|null $finalgrade The grade, or null when there is none.
     * @param float $min The lowest possible grade.
     * @param float $max The highest possible grade.
     * @return array [range string, percentage or null]
     */
    private static function range_and_percentage(?float $finalgrade, float $min, float $max): array {
        $minlabel = $min == 0 ? '0' : number_format($min, 2);
        $range = $minlabel . '-' . number_format($max, 2);
        $percentage = ($max > $min && $finalgrade !== null)
            ? round((($finalgrade - $min) / ($max - $min)) * 100, 2)
            : null;

        return [$range, $percentage];
    }

    /**
     * Describe one activity grade.
     *
     * @param \grade_item $item The activity grade item.
     * @param \grade_grade|null $grade The student's grade, if any.
     * @param \context_course $coursecontext The course context.
     * @return array
     */
    private static function task_entry(\grade_item $item, ?\grade_grade $grade, \context_course $coursecontext): array {
        $finalgrade = ($grade && $grade->finalgrade !== null) ? (float)$grade->finalgrade : null;
        $min = (float)$item->grademin;
        $max = (float)$item->grademax;
        if ($grade && $finalgrade !== null) {
            $grade->grade_item = $item;
            $min = (float)$grade->get_grade_min();
            $max = (float)$grade->get_grade_max();
        }
        [$range, $percentage] = self::range_and_percentage($finalgrade, $min, $max);

        return [
            'name' => \format_string($item->get_name(), true, ['context' => $coursecontext]),
            'calculated_weight' => ($item->aggregationcoef2 ?? 0) > 0 ? round($item->aggregationcoef2 * 100, 2) : null,
            'grade' => $finalgrade,
            'range' => $range,
            'percentage' => $percentage,
            'feedback' => self::safe_feedback($grade),
            'contribution_to_total' => isset($item->weightoverride) && $item->weightoverride > 0
                ? round($item->weightoverride * 100, 2)
                : null,
        ];
    }

    /**
     * Describe a category or course total as the current user may see it.
     *
     * A total the user is not meant to see keeps its name with every value empty, like a
     * missing total, so the service cannot work out a hidden grade from it.
     *
     * @param \grade_item $item The category or course grade item.
     * @param \grade_grade|null $grade The student's grade for that item, if any.
     * @param grade_visibility $visibility Hidden grade rules of the course.
     * @param \context_course $coursecontext The course context.
     * @param bool $withweight Whether to include the calculated weight of the item.
     * @return array
     */
    private static function total_entry(
        \grade_item $item,
        ?\grade_grade $grade,
        grade_visibility $visibility,
        \context_course $coursecontext,
        bool $withweight
    ): array {
        $name = \format_string($item->get_name(), true, ['context' => $coursecontext]);
        $value = $visibility->total($item, $grade);
        if ($value === null) {
            return self::missing_total($name);
        }
        [$range, $percentage] = self::range_and_percentage($value['grade'], $value['min'], $value['max']);

        return [
            'name' => $name,
            'calculated_weight' => $withweight && ($item->aggregationcoef2 ?? 0) > 0
                ? round($item->aggregationcoef2 * 100, 2)
                : null,
            'grade' => $value['grade'],
            'range' => $range,
            'percentage' => $percentage,
            'feedback' => self::safe_feedback($grade),
            'contribution_to_total' => null,
        ];
    }

    /**
     * Builds a placeholder total object used when no total grade item exists.
     *
     * The external AI service requires every section and course to carry a
     * total object, so missing totals are replaced by this marker instead of
     * null values that would make the whole request fail.
     *
     * @param string $name Marker name to send instead of the grade item name.
     * @return array Placeholder total entry with null numeric fields.
     */
    private static function missing_total(string $name): array {
        return [
            'name' => $name,
            'calculated_weight' => null,
            'grade' => null,
            'range' => null,
            'percentage' => null,
            'feedback' => '',
            'contribution_to_total' => null,
        ];
    }

    /**
     * The courses of the student the current user may analyse, optionally limited to one.
     *
     * The same list rules the page, the CSV, the PDF and the data sent to the AI service.
     *
     * @param int $userid The student.
     * @param int $courseid Course filter, 0 for every course.
     * @return array Course records indexed by id.
     */
    public static function courses(int $userid, int $courseid = 0): array {
        $courses = course_access::filter_courses(\enrol_get_users_courses($userid), $userid);
        if ($courseid > 0) {
            $courses = array_filter($courses, static function ($course) use ($courseid): bool {
                return (int)$course->id === $courseid;
            });
        }
        return $courses;
    }

    /**
     * What a generation analyses, to decide later who may read its feedback.
     *
     * @param int $userid The student.
     * @param int $courseid Course filter, 0 for every course.
     * @return array ['courses' => course ids, 'hidden' => course ids whose hidden grades were included]
     */
    public static function scope(int $userid, int $courseid = 0): array {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');

        $scope = ['courses' => [], 'hidden' => []];
        foreach (self::courses($userid, $courseid) as $course) {
            $scope['courses'][] = (int)$course->id;
            if (has_capability('moodle/grade:viewhidden', \context_course::instance($course->id))) {
                $scope['hidden'][] = (int)$course->id;
            }
        }
        sort($scope['courses']);
        sort($scope['hidden']);
        return $scope;
    }

    /**
     * Build the payload sent to the AI service for a student, as the current user may see it.
     *
     * Grades hidden from the current user are left out, and totals that would give them away
     * follow the course setting for totals containing hidden items ({@see grade_visibility}).
     *
     * @param int $userid The student.
     * @param int $courseid Course filter, 0 for every course.
     * @return array
     */
    public static function build(int $userid, int $courseid = 0): array {
        global $DB, $CFG, $USER;

        require_once($CFG->libdir . '/gradelib.php');

        $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);

        $payload = [
            'userid' => (string)$USER->id,
            'student_id' => (string)$user->id,
            'student_name' => \fullname($user),
            'courses' => [],
        ];

        foreach (self::courses($userid, $courseid) as $course) {
            $coursecontext = \context_course::instance($course->id);
            $visibility = new grade_visibility((int)$course->id, $userid);
            $sections = [];

            $categories = \grade_category::fetch_all(['courseid' => $course->id]);
            $hascategories = false;

            foreach ($categories ?: [] as $cat) {
                if ($cat->is_course_category()) {
                    continue;
                }

                $items = \grade_item::fetch_all(['categoryid' => $cat->id]) ?: [];
                $categoryitem = \grade_item::fetch(['iteminstance' => $cat->id, 'itemtype' => 'category']);
                if (!$items && !$categoryitem) {
                    continue;
                }

                $hascategories = true;
                $tasks = [];
                $total = null;

                foreach ($items as $item) {
                    if (!in_array($item->itemtype, ['mod', 'manual'])) {
                        continue;
                    }
                    $grade = \grade_grade::fetch(['itemid' => $item->id, 'userid' => $userid]) ?: null;
                    if ($visibility->hides_item($item, $grade)) {
                        continue;
                    }
                    $tasks[] = self::task_entry($item, $grade, $coursecontext);
                }

                if ($categoryitem) {
                    $grade = \grade_grade::fetch(['itemid' => $categoryitem->id, 'userid' => $userid]) ?: null;
                    $total = self::total_entry($categoryitem, $grade, $visibility, $coursecontext, true);
                }

                if (!empty($tasks) || $total) {
                    $sections[] = [
                        'name' => \format_string($cat->get_name(), true, ['context' => $coursecontext]),
                        'tasks' => $tasks,
                        'total' => $total ?? self::missing_total('Total not available'),
                    ];
                }
            }

            $courseitems = \grade_item::fetch_all(['courseid' => $course->id]) ?: [];
            $courseitem = null;
            foreach ($courseitems as $item) {
                if ($item->itemtype === 'course') {
                    $courseitem = $item;
                    break;
                }
            }
            $coursegrade = $courseitem ? (\grade_grade::fetch(['itemid' => $courseitem->id, 'userid' => $userid]) ?: null) : null;
            $coursetotal = $courseitem ? self::total_entry($courseitem, $coursegrade, $visibility, $coursecontext, false) : null;

            if (!$hascategories) {
                $tasks = [];
                foreach ($courseitems as $item) {
                    if (!in_array($item->itemtype, ['mod', 'manual'])) {
                        continue;
                    }
                    $grade = \grade_grade::fetch(['itemid' => $item->id, 'userid' => $userid]) ?: null;
                    if ($visibility->hides_item($item, $grade)) {
                        continue;
                    }
                    $tasks[] = self::task_entry($item, $grade, $coursecontext);
                }

                if (!empty($tasks) || $coursetotal) {
                    $sections[] = [
                        'name' => $course->fullname,
                        'tasks' => $tasks,
                        'total' => $coursetotal ?? self::missing_total('Total not available'),
                    ];
                }
            }

            $payload['courses'][] = [
                'name' => $course->fullname,
                'sections' => array_values($sections),
                'total' => $coursetotal ?? self::missing_total('Total not available'),
            ];
        }

        return $payload;
    }
}

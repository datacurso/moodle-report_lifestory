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
     * Computes the range label and the percentage of a grade the way the user
     * grade report displays them.
     *
     * The bounds come from the user's grade when one exists: for category and
     * course totals whose category aggregates only graded items, Moodle stores
     * the per-user minimum and maximum in the grade record, which may differ
     * from the grade item bounds. Plain items yield their own bounds, and when
     * the user has no grade the grade item bounds are used.
     *
     * @param \grade_item $item Grade item the grade belongs to.
     * @param \grade_grade|null $grade User grade record, null when not graded.
     * @return array Two-element list: range label "min-max" and percentage (null when not computable).
     */
    private static function range_and_percentage(\grade_item $item, ?\grade_grade $grade): array {
        $min = (float)$item->grademin;
        $max = (float)$item->grademax;
        $finalgrade = null;

        if ($grade) {
            $finalgrade = $grade->finalgrade === null ? null : (float)$grade->finalgrade;
            if ($finalgrade !== null) {
                $grade->grade_item = $item;
                $min = (float)$grade->get_grade_min();
                $max = (float)$grade->get_grade_max();
            }
        }

        $minlabel = $min == 0 ? '0' : number_format($min, 2);
        $range = $minlabel . '-' . number_format($max, 2);
        $percentage = ($max > $min && $finalgrade !== null)
            ? round((($finalgrade - $min) / ($max - $min)) * 100, 2)
            : null;

        return [$range, $percentage];
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
     * Builds the payload with all student information.
     *
     * The course list is always restricted to the courses where the current
     * user can view the student's grades. When a course id is given, the
     * payload is further limited to that single course, and only if it
     * survived the permission filter.
     *
     * @param int $userid Moodle user ID.
     * @param int $courseid Course id to restrict the payload to, 0 for all permitted courses.
     * @return array Student data payload.
     */
    public static function build(int $userid, int $courseid = 0): array {
        global $DB, $CFG, $USER;

        require_once($CFG->libdir . '/gradelib.php');

        $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
        $courses = course_access::filter_courses(\enrol_get_users_courses($userid), $userid);

        if ($courseid > 0) {
            $courses = array_filter($courses, static function ($course) use ($courseid): bool {
                return (int)$course->id === $courseid;
            });
        }

        $payload = [
            'userid' => (string)$USER->id,
            'student_id' => (string)$user->id,
            'student_name' => \fullname($user),
            'courses' => [],
        ];

        foreach ($courses as $course) {
            $coursecontext = \context_course::instance($course->id);
            $sections = [];

            $categories = \grade_category::fetch_all(['courseid' => $course->id]);
            $hascategories = false;

            if ($categories) {
                foreach ($categories as $cat) {
                    if ($cat->is_course_category()) {
                        continue;
                    }

                    $items = \grade_item::fetch_all(['categoryid' => $cat->id]);
                    $categoryitem = \grade_item::fetch(['iteminstance' => $cat->id, 'itemtype' => 'category']);
                    if (!$items && !$categoryitem) {
                        continue;
                    }

                    $hascategories = true;
                    $tasks = [];
                    $total = null;

                    foreach ($items as $item) {
                        if ($item->itemtype === 'category') {
                            continue;
                        }
                        if (!in_array($item->itemtype, ['mod', 'manual'])) {
                            continue;
                        }

                        $grade = \grade_grade::fetch(['itemid' => $item->id, 'userid' => $userid]);
                        $finalgrade = $grade ? (float)$grade->finalgrade : null;
                        [$range, $percentage] = self::range_and_percentage($item, $grade ?: null);
                        $feedback = self::safe_feedback($grade);

                        $weight = ($item->aggregationcoef2 ?? 0) > 0
                            ? round($item->aggregationcoef2 * 100, 2)
                            : null;

                        $contribution = isset($item->weightoverride) && $item->weightoverride > 0
                            ? round($item->weightoverride * 100, 2)
                            : null;

                        $tasks[] = [
                            'name' => \format_string($item->get_name(), true, ['context' => $coursecontext]),
                            'calculated_weight' => $weight,
                            'grade' => $finalgrade,
                            'range' => $range,
                            'percentage' => $percentage,
                            'feedback' => $feedback,
                            'contribution_to_total' => $contribution,
                        ];
                    }

                    if ($categoryitem) {
                        $grade = \grade_grade::fetch(['itemid' => $categoryitem->id, 'userid' => $userid]);
                        $finalgrade = $grade ? (float)$grade->finalgrade : null;
                        [$range, $percentage] = self::range_and_percentage($categoryitem, $grade ?: null);
                        $feedback = self::safe_feedback($grade);

                        $total = [
                            'name' => \format_string($categoryitem->get_name(), true, ['context' => $coursecontext]),
                            'calculated_weight' => ($categoryitem->aggregationcoef2 ?? 0) > 0
                                ? round($categoryitem->aggregationcoef2 * 100, 2)
                                : null,
                            'grade' => $finalgrade,
                            'range' => $range,
                            'percentage' => $percentage,
                            'feedback' => $feedback,
                            'contribution_to_total' => null,
                        ];
                    }

                    if (!empty($tasks) || $total) {
                        $sections[] = [
                            'name' => \format_string($cat->get_name(), true, ['context' => $coursecontext]),
                            'tasks' => $tasks,
                            'total' => $total ?? self::missing_total('Total not available'),
                        ];
                    }
                }
            }

            if (!$hascategories) {
                $items = \grade_item::fetch_all(['courseid' => $course->id]) ?: [];
                $tasks = [];
                $total = null;

                foreach ($items as $item) {
                    if (!in_array($item->itemtype, ['mod', 'manual', 'course'])) {
                        continue;
                    }

                    if ($item->itemtype === 'course') {
                        $grade = \grade_grade::fetch(['itemid' => $item->id, 'userid' => $userid]);
                        $finalgrade = $grade ? (float)$grade->finalgrade : null;
                        [$range, $percentage] = self::range_and_percentage($item, $grade ?: null);
                        $feedback = self::safe_feedback($grade);

                        $total = [
                            'name' => \format_string($item->get_name(), true, ['context' => $coursecontext]),
                            'calculated_weight' => null,
                            'grade' => $finalgrade,
                            'range' => $range,
                            'percentage' => $percentage,
                            'feedback' => $feedback,
                            'contribution_to_total' => null,
                        ];
                        continue;
                    }

                    $grade = \grade_grade::fetch(['itemid' => $item->id, 'userid' => $userid]);
                    $finalgrade = $grade ? (float)$grade->finalgrade : null;
                    [$range, $percentage] = self::range_and_percentage($item, $grade ?: null);
                    $feedback = self::safe_feedback($grade);

                    $weight = ($item->aggregationcoef2 ?? 0) > 0
                        ? round($item->aggregationcoef2 * 100, 2)
                        : null;

                    $contribution = isset($item->weightoverride) && $item->weightoverride > 0
                        ? round($item->weightoverride * 100, 2)
                        : null;

                    $tasks[] = [
                        'name' => \format_string($item->get_name(), true, ['context' => $coursecontext]),
                        'calculated_weight' => $weight,
                        'grade' => $finalgrade,
                        'range' => $range,
                        'percentage' => $percentage,
                        'feedback' => $feedback,
                        'contribution_to_total' => $contribution,
                    ];
                }

                if (!empty($tasks) || $total) {
                    $sections[] = [
                        'name' => $course->fullname,
                        'tasks' => $tasks,
                        'total' => $total ?? self::missing_total('Total not available'),
                    ];
                }
            }

            $courseitems = \grade_item::fetch_all(['courseid' => $course->id]);
            $coursetotal = null;

            if ($courseitems) {
                foreach ($courseitems as $item) {
                    if ($item->itemtype === 'course') {
                        $grade = \grade_grade::fetch(['itemid' => $item->id, 'userid' => $userid]);
                        $finalgrade = $grade ? (float)$grade->finalgrade : null;
                        [$range, $percentage] = self::range_and_percentage($item, $grade ?: null);
                        $feedback = self::safe_feedback($grade);

                        $coursetotal = [
                            'name' => \format_string($item->get_name(), true, ['context' => $coursecontext]),
                            'calculated_weight' => null,
                            'grade' => $finalgrade,
                            'range' => $range,
                            'percentage' => $percentage,
                            'feedback' => $feedback,
                            'contribution_to_total' => null,
                        ];
                        break;
                    }
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

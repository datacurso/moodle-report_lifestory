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
     * Builds the payload with all student information.
     *
     * @param int $userid Moodle user ID.
     * @return array Student data payload.
     */
    public static function build(int $userid): array {
        global $DB, $CFG, $USER;

        require_once($CFG->libdir . '/gradelib.php');

        $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
        $courses = course_access::filter_courses(\enrol_get_users_courses($userid), $userid);

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
                        $range = '0-' . number_format($item->grademax, 2);
                        $percentage = ($item->grademax > 0 && $finalgrade !== null)
                            ? round(($finalgrade / $item->grademax) * 100, 2)
                            : null;
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
                        $range = '0-' . number_format($categoryitem->grademax, 2);
                        $percentage = ($categoryitem->grademax > 0 && $finalgrade !== null)
                            ? round(($finalgrade / $categoryitem->grademax) * 100, 2)
                            : null;
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
                            'total' => $total,
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
                        $range = '0-' . number_format($item->grademax, 2);
                        $percentage = ($item->grademax > 0 && $finalgrade !== null)
                            ? round(($finalgrade / $item->grademax) * 100, 2)
                            : null;
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
                    $range = '0-' . number_format($item->grademax, 2);
                    $percentage = ($item->grademax > 0 && $finalgrade !== null)
                        ? round(($finalgrade / $item->grademax) * 100, 2)
                        : null;
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
                        'total' => $total,
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
                        $range = '0-' . number_format($item->grademax, 2);
                        $percentage = ($item->grademax > 0 && $finalgrade !== null)
                            ? round(($finalgrade / $item->grademax) * 100, 2)
                            : null;
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
                'total' => $coursetotal,
            ];
        }

        return $payload;
    }
}

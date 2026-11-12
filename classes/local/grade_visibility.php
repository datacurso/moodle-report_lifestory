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
 * Hidden grades as the current user is allowed to see them in one course.
 *
 * @package     report_lifestory
 * @copyright   2026 Datacurso
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_lifestory\local;

/**
 * Applies the hidden grade rules of the user report to the data sent to the AI service.
 *
 * Whoever cannot see hidden grades in a course must not have them analysed for them either:
 * a hidden grade, or a total that would give it away, would otherwise reach the AI service and
 * end up written in the feedback. The rules mirror grade_report::blank_hidden_total_and_adjust_bounds():
 * hidden items are left out and totals that contain them are hidden, recalculated without them
 * or shown as they are, following the course setting "Hide totals if they contain hidden items".
 *
 * @package     report_lifestory
 * @copyright   2026 Datacurso
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class grade_visibility {
    /** @var bool Whether the current user may see hidden grades in the course. */
    private bool $canviewhidden;

    /** @var int One of the GRADE_REPORT_*_TOTAL_IF_CONTAINS_HIDDEN constants. */
    private int $totalsmode;

    /** @var array|null Result of grade_grade::get_hiding_affected(), computed on first use. */
    private ?array $affected = null;

    /** @var int The course. */
    private int $courseid;

    /** @var int The student the grades belong to. */
    private int $studentid;

    /**
     * Prepare the rules of one course for one student, as seen by the current user.
     *
     * @param int $courseid The course.
     * @param int $studentid The student the grades belong to.
     */
    public function __construct(int $courseid, int $studentid) {
        global $CFG;

        $this->courseid = $courseid;
        $this->studentid = $studentid;
        $this->canviewhidden = has_capability('moodle/grade:viewhidden', \context_course::instance($courseid));
        $this->totalsmode = (int)grade_get_setting(
            $courseid,
            'report_user_showtotalsifcontainhidden',
            $CFG->grade_report_user_showtotalsifcontainhidden ?? GRADE_REPORT_HIDE_TOTAL_IF_CONTAINS_HIDDEN
        );
    }

    /**
     * Whether the current user may see hidden grades in the course.
     *
     * @return bool
     */
    public function can_view_hidden(): bool {
        return $this->canviewhidden;
    }

    /**
     * Whether an activity grade must be left out because it is hidden from the current user.
     *
     * @param \grade_item $item The grade item.
     * @param \grade_grade|null $grade The student's grade, if any.
     * @return bool
     */
    public function hides_item(\grade_item $item, ?\grade_grade $grade): bool {
        if ($this->canviewhidden) {
            return false;
        }
        if ($item->is_hidden()) {
            return true;
        }
        if ($grade) {
            $grade->grade_item = $item;
            return $grade->is_hidden();
        }
        return false;
    }

    /**
     * The value a category or course total may show to the current user.
     *
     * @param \grade_item $item The category or course grade item.
     * @param \grade_grade|null $grade The student's grade for that item, if any.
     * @return array|null With 'grade', 'min' and 'max'; null when the total must not be shown.
     */
    public function total(\grade_item $item, ?\grade_grade $grade): ?array {
        $value = [
            'grade' => ($grade && $grade->finalgrade !== null) ? (float)$grade->finalgrade : null,
            'min' => (float)$item->grademin,
            'max' => (float)$item->grademax,
        ];
        if ($grade) {
            $grade->grade_item = $item;
            $value['min'] = (float)$grade->get_grade_min();
            $value['max'] = (float)$grade->get_grade_max();
        }

        if ($this->canviewhidden) {
            return $value;
        }
        if ($this->hides_item($item, $grade)) {
            return null;
        }
        if ($this->totalsmode === GRADE_REPORT_SHOW_REAL_TOTAL_IF_CONTAINS_HIDDEN) {
            return $value;
        }

        $affected = $this->affected();
        $id = (int)$item->id;
        $hidetotals = $this->totalsmode === GRADE_REPORT_HIDE_TOTAL_IF_CONTAINS_HIDDEN;

        if (array_key_exists($id, $affected['altered'])) {
            if ($hidetotals) {
                return null;
            }
            $value['grade'] = $affected['altered'][$id] === null ? null : (float)$affected['altered'][$id];
        } else if (array_key_exists($id, $affected['unknowngrades'])) {
            if ($hidetotals) {
                return null;
            }
            $value['grade'] = $affected['unknowngrades'][$id] === null ? null : (float)$affected['unknowngrades'][$id];
        }
        if (array_key_exists($id, $affected['alteredgrademin'])) {
            $value['min'] = (float)$affected['alteredgrademin'][$id];
        }
        if (array_key_exists($id, $affected['alteredgrademax'])) {
            $value['max'] = (float)$affected['alteredgrademax'][$id];
        }

        return $value;
    }

    /**
     * What hiding changes in the course for the student, computed once per course.
     *
     * @return array As returned by grade_grade::get_hiding_affected().
     */
    private function affected(): array {
        global $DB;

        if ($this->affected !== null) {
            return $this->affected;
        }

        $items = \grade_item::fetch_all(['courseid' => $this->courseid]) ?: [];
        $grades = [];
        if ($items) {
            [$insql, $params] = $DB->get_in_or_equal(array_keys($items), SQL_PARAMS_NAMED);
            $params['userid'] = $this->studentid;
            $records = $DB->get_records_select('grade_grades', "itemid $insql AND userid = :userid", $params);
            foreach ($records as $record) {
                $grades[(int)$record->itemid] = new \grade_grade($record, false);
            }
        }
        foreach ($items as $itemid => $item) {
            if (!isset($grades[$itemid])) {
                $grades[$itemid] = new \grade_grade(['userid' => $this->studentid, 'itemid' => $itemid], false);
            }
            $grades[$itemid]->grade_item = $item;
        }

        $this->affected = \grade_grade::get_hiding_affected($grades, $items);
        return $this->affected;
    }
}

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
 * Tests for the contribution to the course total and the exports of a course without categories.
 *
 * @package   report_lifestory
 * @category  test
 * @copyright 2026 Datacurso
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_lifestory\local;

/**
 * The contribution of each activity follows the user report, and a course without categories
 * prints its course total once in the CSV and the PDF.
 *
 * @package   report_lifestory
 * @category  test
 * @covers    \report_lifestory\local\payload_builder
 * @covers    \report_lifestory\local\csv_exporter
 * @covers    \report_lifestory\local\pdf_exporter
 */
final class exports_and_contribution_test extends \advanced_testcase {
    /**
     * Build the payload of a course without categories: 80 out of 100 and 25 out of 50.
     *
     * @return array The payload.
     */
    private function flat_course_payload(): array {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['fullname' => 'Maths']);
        $student = $generator->create_and_enrol($course, 'student');

        foreach ([['Fractions', 100, 80.0], ['Equations', 50, 25.0]] as [$name, $max, $value]) {
            $assign = $generator->create_module('assign', ['course' => $course->id, 'name' => $name, 'grade' => $max]);
            $item = \grade_item::fetch([
                'courseid' => $course->id,
                'itemtype' => 'mod',
                'itemmodule' => 'assign',
                'iteminstance' => $assign->id,
            ]);
            $item->update_final_grade($student->id, $value);
        }
        grade_regrade_final_grades($course->id);

        $this->setAdminUser();
        return payload_builder::build((int)$student->id);
    }

    /**
     * MDL-UNIT-024: each activity carries its contribution to the course total, as in the user report.
     */
    public function test_activity_contribution_to_the_course_total(): void {
        $this->resetAfterTest();
        $tasks = $this->flat_course_payload()['courses'][0]['sections'][0]['tasks'];

        $contributions = array_column($tasks, 'contribution_to_total', 'name');
        $this->assertEqualsWithDelta(53.33, $contributions['Fractions'], 0.01);
        $this->assertEqualsWithDelta(16.67, $contributions['Equations'], 0.01);
    }

    /**
     * MDL-E2E-009: the CSV of a course without categories lists the course total once.
     */
    public function test_csv_lists_the_course_total_once(): void {
        $this->resetAfterTest();
        $csv = csv_exporter::build_csv($this->flat_course_payload());

        $this->assertSame(1, substr_count($csv, get_string('coursetotal', 'grades')));
        $this->assertStringContainsString('"Maths","","' . get_string('coursetotal', 'grades') . '"', $csv);
    }

    /**
     * MDL-E2E-010: the PDF of a course without categories prints its name and its course total once.
     */
    public function test_pdf_prints_the_course_name_and_total_once(): void {
        $this->resetAfterTest();
        $html = pdf_exporter::build_html('Alpha Alpine', 'Feedback', $this->flat_course_payload());

        $this->assertSame(1, substr_count($html, 'Maths'));
        $this->assertSame(1, substr_count($html, 'font-weight: bold;">' . get_string('coursetotal', 'grades') . '</td>'));
        $this->assertStringContainsString('53.33', $html);
    }
}

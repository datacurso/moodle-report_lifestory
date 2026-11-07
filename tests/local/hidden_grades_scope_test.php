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
 * Tests for hidden grades in the AI data and the scope of the stored feedback.
 *
 * @package   report_lifestory
 * @category  test
 * @copyright 2026 Datacurso
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_lifestory\local;

/**
 * Hidden grades never reach the AI service for whoever cannot see them, and a stored
 * feedback is only shown to users who can see what it analysed.
 *
 * @package   report_lifestory
 * @category  test
 * @covers    \report_lifestory\local\grade_visibility
 * @covers    \report_lifestory\local\payload_builder
 * @covers    \report_lifestory\local\feedback_store
 */
final class hidden_grades_scope_test extends \advanced_testcase {
    /**
     * A course with a visible and a hidden assignment, a graded student and a teacher.
     *
     * Visible: 80 out of 100. Hidden: 9 out of 10. The stored course total is 89 out of 110.
     *
     * @param bool $teachercanviewhidden Whether the teacher keeps the right to see hidden grades.
     * @return array{0: \stdClass, 1: \stdClass, 2: \stdClass} Course, student and teacher.
     */
    private function course_with_hidden_grade(bool $teachercanviewhidden): array {
        global $CFG, $DB;
        require_once($CFG->libdir . '/gradelib.php');

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['fullname' => 'History']);
        $student = $generator->create_and_enrol($course, 'student');
        $teacher = $generator->create_and_enrol($course, 'editingteacher');

        foreach ([['Essay', 100, 80.0, false], ['Self assessment', 10, 9.0, true]] as [$name, $max, $value, $hidden]) {
            $assign = $generator->create_module('assign', ['course' => $course->id, 'name' => $name, 'grade' => $max]);
            $item = \grade_item::fetch([
                'courseid' => $course->id,
                'itemtype' => 'mod',
                'itemmodule' => 'assign',
                'iteminstance' => $assign->id,
            ]);
            $item->update_final_grade($student->id, $value);
            if ($hidden) {
                $item->set_hidden(1, true);
            }
        }
        grade_regrade_final_grades($course->id);

        if (!$teachercanviewhidden) {
            $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
            assign_capability('moodle/grade:viewhidden', CAP_PREVENT, $roleid, \context_course::instance($course->id), true);
        }

        return [$course, $student, $teacher];
    }

    /**
     * Names of every task sent for the first course.
     *
     * @param array $payload
     * @return string[]
     */
    private function task_names(array $payload): array {
        $names = [];
        foreach ($payload['courses'][0]['sections'] as $section) {
            foreach ($section['tasks'] as $task) {
                $names[] = $task['name'];
            }
        }
        return $names;
    }

    /**
     * MDL-INT-012: a hidden grade is not sent when the requester cannot see hidden grades.
     */
    public function test_hidden_grade_is_left_out_for_a_requester_who_cannot_see_it(): void {
        $this->resetAfterTest();
        [$course, $student, $teacher] = $this->course_with_hidden_grade(false);
        grade_set_setting($course->id, 'report_user_showtotalsifcontainhidden', GRADE_REPORT_HIDE_TOTAL_IF_CONTAINS_HIDDEN);
        $this->setUser($teacher);

        $payload = payload_builder::build((int)$student->id);

        $this->assertSame(['Essay'], $this->task_names($payload));
        $this->assertNull($payload['courses'][0]['total']['grade'], 'A total containing a hidden grade must not be sent.');
        $this->assertNull($payload['courses'][0]['total']['percentage']);
    }

    /**
     * MDL-INT-012: the total is recalculated without the hidden grade when the course says so.
     */
    public function test_total_leaves_the_hidden_grade_out_when_the_course_says_so(): void {
        $this->resetAfterTest();
        [$course, $student, $teacher] = $this->course_with_hidden_grade(false);
        grade_set_setting($course->id, 'report_user_showtotalsifcontainhidden', GRADE_REPORT_SHOW_TOTAL_IF_CONTAINS_HIDDEN);
        $this->setUser($teacher);

        $total = payload_builder::build((int)$student->id)['courses'][0]['total'];

        $this->assertEquals(80.0, $total['grade']);
        $this->assertSame('0-100.00', $total['range']);
    }

    /**
     * MDL-INT-012: a course that shows real totals keeps sending the stored total.
     */
    public function test_real_total_is_sent_when_the_course_shows_it(): void {
        $this->resetAfterTest();
        [$course, $student, $teacher] = $this->course_with_hidden_grade(false);
        grade_set_setting($course->id, 'report_user_showtotalsifcontainhidden', GRADE_REPORT_SHOW_REAL_TOTAL_IF_CONTAINS_HIDDEN);
        $this->setUser($teacher);

        $payload = payload_builder::build((int)$student->id);

        $this->assertSame(['Essay'], $this->task_names($payload));
        $this->assertEquals(89.0, $payload['courses'][0]['total']['grade']);
    }

    /**
     * MDL-INT-012: a requester who can see hidden grades still sends them.
     */
    public function test_hidden_grade_is_sent_when_the_requester_can_see_it(): void {
        $this->resetAfterTest();
        [, $student, $teacher] = $this->course_with_hidden_grade(true);
        $this->setUser($teacher);

        $payload = payload_builder::build((int)$student->id);

        $this->assertSame(['Essay', 'Self assessment'], $this->task_names($payload));
        $this->assertEquals(89.0, $payload['courses'][0]['total']['grade']);
    }

    /**
     * MDL-INT-013: the scope records the analysed courses and where hidden grades were included.
     */
    public function test_scope_records_courses_and_hidden_grades(): void {
        $this->resetAfterTest();
        [$course, $student, $teacher] = $this->course_with_hidden_grade(true);
        $this->setUser($teacher);

        $expected = ['courses' => [(int)$course->id], 'hidden' => [(int)$course->id]];
        $this->assertSame($expected, payload_builder::scope((int)$student->id));

        $this->setAdminUser();
        $other = $this->getDataGenerator()->create_course();
        $this->assertSame(['courses' => [], 'hidden' => []], payload_builder::scope((int)$student->id, (int)$other->id));
    }

    /**
     * MDL-INT-013: feedback that included hidden grades is not shown to who cannot see them.
     */
    public function test_feedback_with_hidden_grades_is_not_shown_to_who_cannot_see_them(): void {
        $this->resetAfterTest();
        [$course, $student] = $this->course_with_hidden_grade(false);
        $this->setAdminUser();
        feedback_store::save((int)$student->id, 0, 'Analysis by the admin', payload_builder::scope((int)$student->id));

        $viewer = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($viewer);

        $this->assertNull(feedback_store::get_visible_record((int)$student->id, 0));
        $this->assertNotNull(feedback_store::get_record((int)$student->id, 0), 'The record itself is kept.');

        $this->setAdminUser();
        $this->assertSame('Analysis by the admin', feedback_store::get_visible_record((int)$student->id, 0)->feedback);
    }

    /**
     * MDL-INT-013: feedback covering a course the viewer cannot see is not shown to them.
     */
    public function test_feedback_covering_another_course_is_not_shown(): void {
        $this->resetAfterTest();
        [$course, $student] = $this->course_with_hidden_grade(true);
        $generator = $this->getDataGenerator();
        $other = $generator->create_course(['fullname' => 'Maths']);
        $generator->enrol_user($student->id, $other->id, 'student');

        $this->setAdminUser();
        feedback_store::save((int)$student->id, 0, 'Both courses', payload_builder::scope((int)$student->id));

        $historyteacher = $generator->create_and_enrol($course, 'editingteacher');
        $this->setUser($historyteacher);
        $this->assertNull(feedback_store::get_visible_record((int)$student->id, 0));

        // A feedback generated by that teacher only covers History, and they can read it.
        feedback_store::save((int)$student->id, 0, 'History only', payload_builder::scope((int)$student->id));
        $this->assertSame('History only', feedback_store::get_visible_record((int)$student->id, 0)->feedback);
    }

    /**
     * MDL-INT-013: records stored before the scope existed require seeing every course and hidden grade.
     */
    public function test_feedback_without_scope_requires_seeing_every_course(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $student] = $this->course_with_hidden_grade(false);
        $this->setAdminUser();
        $id = feedback_store::save((int)$student->id, 0, 'Legacy feedback');
        $this->assertNull($DB->get_field('report_lifestory_feedback', 'scope', ['id' => $id]));

        $this->assertNotNull(feedback_store::get_visible_record((int)$student->id, 0));

        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);
        $this->assertNull(feedback_store::get_visible_record((int)$student->id, 0));
    }

    /**
     * MDL-INT-010: the course filter only keeps a course the student is enrolled in.
     */
    public function test_course_filter_requires_the_student_to_be_enrolled(): void {
        $this->resetAfterTest();
        [$course, $student] = $this->course_with_hidden_grade(true);
        $other = $this->getDataGenerator()->create_course();
        $this->setAdminUser();

        $this->assertSame([(int)$course->id], array_keys(payload_builder::courses((int)$student->id, (int)$course->id)));
        $this->assertSame([], payload_builder::courses((int)$student->id, (int)$other->id));
    }
}

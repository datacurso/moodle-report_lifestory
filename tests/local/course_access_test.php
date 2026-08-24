<?php
// This file is part of Moodle - http://moodle.org/.
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Tests for the course access rules of report_lifestory.
 *
 * @package   report_lifestory
 * @category  test
 * @copyright 2026 Datacurso
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_lifestory\local;

/**
 * Unit tests ensuring the report only exposes courses where the viewing
 * user is allowed to see the student's grades.
 *
 * The rule mirrors the core user grade report: access to a course requires
 * the gradereport/user:view capability in the course context, plus either
 * moodle/grade:viewall in the course context or moodle/grade:viewall in the
 * student's user context on a course that shows grades.
 *
 * @package   report_lifestory
 * @category  test
 * @covers    \report_lifestory\local\course_access
 */
final class course_access_test extends \advanced_testcase {
    /**
     * Creates the common fixture: two courses, a student enrolled in both,
     * and a teacher enrolled in the first course only.
     *
     * @return array Array with course1, course2, student and teacher.
     */
    private function create_courses_and_users(): array {
        $generator = $this->getDataGenerator();

        $course1 = $generator->create_course(['fullname' => 'Course One']);
        $course2 = $generator->create_course(['fullname' => 'Course Two']);

        $student = $generator->create_user();
        $generator->enrol_user($student->id, $course1->id, 'student');
        $generator->enrol_user($student->id, $course2->id, 'student');

        $teacher = $generator->create_user();
        $generator->enrol_user($teacher->id, $course1->id, 'editingteacher');

        return [$course1, $course2, $student, $teacher];
    }

    /**
     * Ensures a teacher only sees the student's grades in the course they teach.
     */
    public function test_teacher_only_sees_grades_in_their_own_course(): void {
        $this->resetAfterTest();

        [$course1, $course2, $student, $teacher] = $this->create_courses_and_users();

        $this->setUser($teacher);

        $this->assertTrue(course_access::can_view_student_grades($course1->id, $student->id));
        $this->assertFalse(course_access::can_view_student_grades($course2->id, $student->id));

        $filtered = course_access::filter_courses(enrol_get_users_courses($student->id), $student->id);

        $this->assertCount(1, $filtered);
        $this->assertArrayHasKey($course1->id, $filtered);
        $this->assertArrayNotHasKey($course2->id, $filtered);
    }

    /**
     * Ensures a system-wide manager sees the student's grades in every course.
     */
    public function test_system_manager_sees_grades_in_all_courses(): void {
        global $DB;

        $this->resetAfterTest();

        [$course1, $course2, $student] = $this->create_courses_and_users();

        $manager = $this->getDataGenerator()->create_user();
        $managerroleid = $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        role_assign($managerroleid, $manager->id, \context_system::instance()->id);

        $this->setUser($manager);

        $this->assertTrue(course_access::can_view_student_grades($course1->id, $student->id));
        $this->assertTrue(course_access::can_view_student_grades($course2->id, $student->id));

        $filtered = course_access::filter_courses(enrol_get_users_courses($student->id), $student->id);

        $this->assertCount(2, $filtered);
        $this->assertArrayHasKey($course1->id, $filtered);
        $this->assertArrayHasKey($course2->id, $filtered);
    }

    /**
     * Ensures a user without any role sees the student's grades in no course.
     */
    public function test_user_without_roles_sees_no_courses(): void {
        $this->resetAfterTest();

        [$course1, $course2, $student] = $this->create_courses_and_users();

        $nobody = $this->getDataGenerator()->create_user();
        $this->setUser($nobody);

        $this->assertFalse(course_access::can_view_student_grades($course1->id, $student->id));
        $this->assertFalse(course_access::can_view_student_grades($course2->id, $student->id));

        $filtered = course_access::filter_courses(enrol_get_users_courses($student->id), $student->id);

        $this->assertSame([], $filtered);
    }

    /**
     * Ensures the payload builder only includes the courses the viewing user
     * is allowed to see grades for.
     */
    public function test_payload_builder_respects_course_access(): void {
        global $DB;

        $this->resetAfterTest();

        [$course1, $course2, $student, $teacher] = $this->create_courses_and_users();

        $this->setUser($teacher);

        $payload = payload_builder::build($student->id);
        $coursenames = array_column($payload['courses'], 'name');

        $this->assertSame([$course1->fullname], $coursenames);

        $manager = $this->getDataGenerator()->create_user();
        $managerroleid = $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        role_assign($managerroleid, $manager->id, \context_system::instance()->id);

        $this->setUser($manager);

        $payload = payload_builder::build($student->id);
        $coursenames = array_column($payload['courses'], 'name');

        $this->assertContains($course1->fullname, $coursenames);
        $this->assertContains($course2->fullname, $coursenames);
        $this->assertCount(2, $coursenames);
    }
}

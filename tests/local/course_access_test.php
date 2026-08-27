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
     * Creates a role granting a single capability and assigns it to a user in a context.
     *
     * @param string $capability The capability to allow.
     * @param int $userid The user receiving the role.
     * @param \context $context The context of the assignment.
     * @return void
     */
    private function grant(string $capability, int $userid, \context $context): void {
        static $counter = 0;
        $counter++;

        $roleid = create_role('Access role ' . $counter, 'lifestoryaccess' . $counter, '');
        assign_capability($capability, CAP_ALLOW, $roleid, $context->id);
        role_assign($roleid, $userid, $context->id);
    }

    /**
     * MDL-UNIT-006: A teacher only sees the student's grades in the course they teach.
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
     * MDL-UNIT-006: A system-wide manager sees the student's grades in every course.
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
     * MDL-UNIT-006: A user without any role sees the student's grades in no course.
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
     * MDL-UNIT-006: moodle/grade:viewall held in the student's user context
     * grants access only when the course shows grades to students.
     */
    public function test_user_context_viewall_requires_course_showgrades(): void {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();

        $shown = $generator->create_course(['fullname' => 'Grades shown', 'showgrades' => 1]);
        $hidden = $generator->create_course(['fullname' => 'Grades hidden', 'showgrades' => 0]);

        $student = $generator->create_user();
        $generator->enrol_user($student->id, $shown->id, 'student');
        $generator->enrol_user($student->id, $hidden->id, 'student');

        // A mentor-like viewer: gradereport/user:view in both courses and
        // moodle/grade:viewall only in the student's user context.
        $mentor = $generator->create_user();
        $this->grant('gradereport/user:view', $mentor->id, \context_course::instance($shown->id));
        $this->grant('gradereport/user:view', $mentor->id, \context_course::instance($hidden->id));
        $this->grant('moodle/grade:viewall', $mentor->id, \context_user::instance($student->id));

        $this->setUser($mentor);

        $this->assertFalse(has_capability('moodle/grade:viewall', \context_course::instance($shown->id)));
        $this->assertTrue(course_access::can_view_student_grades($shown->id, $student->id));
        $this->assertFalse(course_access::can_view_student_grades($hidden->id, $student->id));

        $filtered = course_access::filter_courses(enrol_get_users_courses($student->id), $student->id);

        $this->assertSame([(int)$shown->id], array_map('intval', array_keys($filtered)));
    }

    /**
     * MDL-UNIT-006: moodle/grade:viewall without gradereport/user:view in the
     * course grants no access, whether held in the course or the user context.
     */
    public function test_viewall_without_user_report_capability_is_denied(): void {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();

        $course = $generator->create_course(['showgrades' => 1]);
        $student = $generator->create_user();
        $generator->enrol_user($student->id, $course->id, 'student');

        $courseviewer = $generator->create_user();
        $this->grant('moodle/grade:viewall', $courseviewer->id, \context_course::instance($course->id));

        $userviewer = $generator->create_user();
        $this->grant('moodle/grade:viewall', $userviewer->id, \context_user::instance($student->id));

        $this->setUser($courseviewer);
        $this->assertTrue(has_capability('moodle/grade:viewall', \context_course::instance($course->id)));
        $this->assertFalse(course_access::can_view_student_grades($course->id, $student->id));

        $this->setUser($userviewer);
        $this->assertTrue(has_capability('moodle/grade:viewall', \context_user::instance($student->id)));
        $this->assertFalse(course_access::can_view_student_grades($course->id, $student->id));
        $this->assertSame([], course_access::filter_courses(enrol_get_users_courses($student->id), $student->id));
    }

    /**
     * MDL-INT-013: The payload builder only includes the courses the viewing
     * user is allowed to see grades for.
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

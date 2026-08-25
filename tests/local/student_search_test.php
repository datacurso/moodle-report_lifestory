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
 * Tests for the student search scoping of report_lifestory.
 *
 * @package   report_lifestory
 * @category  test
 * @copyright 2026 Datacurso
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_lifestory\local;

use core_external\external_api;

/**
 * Unit tests ensuring the student search only returns students enrolled in
 * at least one course where the viewing user can view grades.
 *
 * A course qualifies when the viewing user holds both the
 * gradereport/user:view and the moodle/grade:viewall capabilities in the
 * course context, matching the rule applied by the course access helper.
 *
 * @package   report_lifestory
 * @category  test
 * @covers    \report_lifestory\local\student_search
 */
final class student_search_test extends \advanced_testcase {
    /**
     * Creates the common fixture: two courses, one student per course and a
     * teacher enrolled in the first course only.
     *
     * @return array Array with course1, course2, student1, student2 and teacher.
     */
    private function create_courses_and_users(): array {
        $generator = $this->getDataGenerator();

        $course1 = $generator->create_course(['fullname' => 'Course One']);
        $course2 = $generator->create_course(['fullname' => 'Course Two']);

        $student1 = $generator->create_user(['firstname' => 'Alpha', 'lastname' => 'Alpine']);
        $generator->enrol_user($student1->id, $course1->id, 'student');

        $student2 = $generator->create_user(['firstname' => 'Alpha', 'lastname' => 'Boreal']);
        $generator->enrol_user($student2->id, $course2->id, 'student');

        $teacher = $generator->create_user();
        $generator->enrol_user($teacher->id, $course1->id, 'editingteacher');

        return [$course1, $course2, $student1, $student2, $teacher];
    }

    /**
     * Creates a role holding the report view capability and assigns it to a user.
     *
     * @param int $userid The user receiving the role.
     * @return void
     */
    private function assign_report_view_role(int $userid): void {
        $systemcontext = \context_system::instance();

        $roleid = create_role('Life story viewer', 'lifeviewer' . $userid, '');
        assign_capability('report/lifestory:view', CAP_ALLOW, $roleid, $systemcontext->id);
        role_assign($roleid, $userid, $systemcontext->id);
    }

    /**
     * Ensures a teacher only finds students enrolled in the course they teach.
     */
    public function test_teacher_only_finds_students_of_their_own_course(): void {
        $this->resetAfterTest();

        [$course1, $course2, $student1, $student2, $teacher] = $this->create_courses_and_users();

        $this->setUser($teacher);

        $result = student_search::search('Alpha');

        $this->assertCount(1, $result['students']);
        $this->assertSame((int)$student1->id, $result['students'][0]['id']);
        $this->assertFalse($result['hasmore']);

        $this->assertSame(['students' => [], 'hasmore' => false], student_search::search('Boreal'));
    }

    /**
     * Ensures a system-wide manager finds students of every course, ordered by last name.
     */
    public function test_system_manager_finds_students_of_all_courses(): void {
        global $DB;

        $this->resetAfterTest();

        [$course1, $course2, $student1, $student2] = $this->create_courses_and_users();

        $manager = $this->getDataGenerator()->create_user();
        $managerroleid = $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        role_assign($managerroleid, $manager->id, \context_system::instance()->id);

        $this->setUser($manager);

        $result = student_search::search('Alpha');

        $this->assertCount(2, $result['students']);
        $this->assertSame((int)$student1->id, $result['students'][0]['id']);
        $this->assertSame((int)$student2->id, $result['students'][1]['id']);
        $this->assertFalse($result['hasmore']);
    }

    /**
     * Ensures a user without any course role finds no students at all.
     */
    public function test_user_without_roles_finds_no_students(): void {
        $this->resetAfterTest();

        $this->create_courses_and_users();

        $nobody = $this->getDataGenerator()->create_user();
        $this->setUser($nobody);

        $this->assertSame(['students' => [], 'hasmore' => false], student_search::search('Alpha'));
    }

    /**
     * Ensures the search caps the results at the limit and reports that more
     * students match when the eleventh match falls beyond the limit.
     */
    public function test_search_reports_more_matches_beyond_the_limit(): void {
        $this->resetAfterTest();

        [$course1, $course2, $student1, $student2, $teacher] = $this->create_courses_and_users();

        $generator = $this->getDataGenerator();
        for ($i = 1; $i <= 11; $i++) {
            $extra = $generator->create_user(['firstname' => 'Gamma', 'lastname' => sprintf('Match%02d', $i)]);
            $generator->enrol_user($extra->id, $course1->id, 'student');
        }

        $this->setUser($teacher);

        $result = student_search::search('Gamma');

        $this->assertCount(10, $result['students']);
        $this->assertTrue($result['hasmore']);
    }

    /**
     * Ensures a search matching fewer students than the limit keeps the
     * previous behaviour: all matches returned and no more-matches flag.
     */
    public function test_search_with_few_matches_does_not_report_more(): void {
        $this->resetAfterTest();

        [$course1, $course2, $student1, $student2, $teacher] = $this->create_courses_and_users();

        $generator = $this->getDataGenerator();
        $student3 = $generator->create_user(['firstname' => 'Alpha', 'lastname' => 'Cedar']);
        $generator->enrol_user($student3->id, $course1->id, 'student');

        $this->setUser($teacher);

        $result = student_search::search('Alpha');

        $this->assertCount(2, $result['students']);
        $this->assertSame((int)$student1->id, $result['students'][0]['id']);
        $this->assertSame((int)$student3->id, $result['students'][1]['id']);
        $this->assertFalse($result['hasmore']);
    }

    /**
     * Ensures suspended students never appear in the search results, even when
     * their name matches and they are enrolled in a viewable course.
     */
    public function test_search_excludes_suspended_students(): void {
        $this->resetAfterTest();

        [$course1, $course2, $student1, $student2, $teacher] = $this->create_courses_and_users();

        $generator = $this->getDataGenerator();
        $suspended = $generator->create_user([
            'firstname' => 'Alpha',
            'lastname' => 'Suspended',
            'suspended' => 1,
        ]);
        $generator->enrol_user($suspended->id, $course1->id, 'student');

        $this->setUser($teacher);

        $result = student_search::search('Alpha');

        $this->assertCount(1, $result['students']);
        $this->assertSame((int)$student1->id, $result['students'][0]['id']);
        $this->assertFalse($result['hasmore']);
    }

    /**
     * Ensures the grade viewable course ids match the courses where the user
     * holds both grade viewing capabilities.
     */
    public function test_grade_viewable_courseids_matches_grade_access(): void {
        $this->resetAfterTest();

        [$course1, $course2, $student1, $student2, $teacher] = $this->create_courses_and_users();

        $this->setUser($teacher);

        $courseids = course_access::grade_viewable_courseids();
        sort($courseids);

        $this->assertSame([(int)$course1->id], $courseids);

        $nobody = $this->getDataGenerator()->create_user();
        $this->setUser($nobody);

        $this->assertSame([], course_access::grade_viewable_courseids());
    }

    /**
     * Ensures the student role check accepts students and rejects every other
     * kind of user, matching the criterion applied by the search SQL.
     */
    public function test_is_student(): void {
        global $DB;

        $this->resetAfterTest();

        [$course1, $course2, $student1, $student2, $teacher] = $this->create_courses_and_users();

        $manager = $this->getDataGenerator()->create_user();
        $managerroleid = $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        role_assign($managerroleid, $manager->id, \context_system::instance()->id);

        $roleless = $this->getDataGenerator()->create_user();

        $this->assertTrue(student_search::is_student((int)$student1->id));
        $this->assertFalse(student_search::is_student((int)$teacher->id));
        $this->assertFalse(student_search::is_student((int)$manager->id));
        $this->assertFalse(student_search::is_student((int)$roleless->id));
        $this->assertFalse(student_search::is_student(999999));
    }

    /**
     * Ensures the external search function applies the same scoping as the
     * search helper for users holding the report view capability.
     */
    public function test_external_search_respects_grade_access_scope(): void {
        $this->resetAfterTest();

        [$course1, $course2, $student1, $student2, $teacher] = $this->create_courses_and_users();

        $generator = $this->getDataGenerator();
        $suspended = $generator->create_user([
            'firstname' => 'Alpha',
            'lastname' => 'Suspended',
            'suspended' => 1,
        ]);
        $generator->enrol_user($suspended->id, $course1->id, 'student');

        $nobody = $this->getDataGenerator()->create_user();
        $this->assign_report_view_role($nobody->id);
        $this->assign_report_view_role($teacher->id);

        $this->setUser($nobody);

        $result = \report_lifestory\external\search_students::execute('Alpha');
        $result = external_api::clean_returnvalue(
            \report_lifestory\external\search_students::execute_returns(),
            $result
        );

        $this->assertSame([], $result['students']);
        $this->assertFalse($result['hasmore']);

        $this->setUser($teacher);

        $result = \report_lifestory\external\search_students::execute('Alpha');
        $result = external_api::clean_returnvalue(
            \report_lifestory\external\search_students::execute_returns(),
            $result
        );

        $this->assertCount(1, $result['students']);
        $this->assertArrayHasKey('hasmore', $result);
        $this->assertFalse($result['hasmore']);

        $entry = $result['students'][0];
        $this->assertSame((int)$student1->id, $entry['id']);
        $this->assertArrayHasKey('fullname', $entry);
        $this->assertArrayHasKey('email', $entry);
        $this->assertArrayHasKey('profileimageurl', $entry);
    }
}

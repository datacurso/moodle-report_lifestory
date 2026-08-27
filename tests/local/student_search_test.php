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
     * Creates a system-wide manager and sets it as the current user.
     *
     * @return \stdClass The manager user record.
     */
    private function set_system_manager(): \stdClass {
        global $DB;

        $manager = $this->getDataGenerator()->create_user();
        $managerroleid = $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        role_assign($managerroleid, $manager->id, \context_system::instance()->id);
        $this->setUser($manager);

        return $manager;
    }

    /**
     * Returns the ids of the students in a search result, in result order.
     *
     * @param array $result Result of student_search::search().
     * @return int[] Student ids.
     */
    private static function ids(array $result): array {
        return array_column($result['students'], 'id');
    }

    /**
     * MDL-UNIT-004, MDL-UNIT-001: A teacher only finds students enrolled in
     * the course they teach, matching on the first name.
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
     * MDL-UNIT-002, MDL-UNIT-004: A system-wide manager finds students of
     * every course, ordered by last name.
     */
    public function test_system_manager_finds_students_of_all_courses(): void {
        $this->resetAfterTest();

        [$course1, $course2, $student1, $student2] = $this->create_courses_and_users();

        $this->set_system_manager();

        $result = student_search::search('Alpha');

        $this->assertCount(2, $result['students']);
        $this->assertSame((int)$student1->id, $result['students'][0]['id']);
        $this->assertSame((int)$student2->id, $result['students'][1]['id']);
        $this->assertFalse($result['hasmore']);
    }

    /**
     * MDL-UNIT-004: A user without any course role finds no students at all.
     */
    public function test_user_without_roles_finds_no_students(): void {
        $this->resetAfterTest();

        $this->create_courses_and_users();

        $nobody = $this->getDataGenerator()->create_user();
        $this->setUser($nobody);

        $this->assertSame(['students' => [], 'hasmore' => false], student_search::search('Alpha'));
    }

    /**
     * MDL-UNIT-003, MDL-UNIT-002: The search caps the results at the limit
     * and reports that more students match when the eleventh match falls
     * beyond the limit.
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
     * MDL-UNIT-003, MDL-UNIT-002: A search matching fewer students than the
     * limit returns every match and does not raise the more-matches flag.
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
     * MDL-UNIT-004, MDL-INT-005: Suspended students never appear in the
     * search results, even when their name matches and they are enrolled in
     * a viewable course.
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
     * MDL-UNIT-004: The grade viewable course ids match the courses where
     * the user holds both grade viewing capabilities.
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
     * MDL-INT-007: The student role check accepts students and rejects every
     * other kind of user, matching the criterion applied by the search SQL.
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
     * MDL-INT-004, MDL-CTR-002: The external search function applies the
     * same scoping as the search helper for users holding the report view
     * capability.
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

    /**
     * MDL-UNIT-001: Partial fragments of the first name, the last name and
     * the email address each locate the student.
     */
    public function test_search_matches_firstname_lastname_and_email_fragments(): void {
        $this->resetAfterTest();

        [$course1] = $this->create_courses_and_users();

        $generator = $this->getDataGenerator();
        $student = $generator->create_user([
            'firstname' => 'Valentina',
            'lastname' => 'Quintero',
            'email' => 'vquintero@lifestory.example',
        ]);
        $generator->enrol_user($student->id, $course1->id, 'student');

        $this->set_system_manager();

        $this->assertSame([(int)$student->id], self::ids(student_search::search('lenti')));
        $this->assertSame([(int)$student->id], self::ids(student_search::search('uinter')));
        $this->assertSame([(int)$student->id], self::ids(student_search::search('lifestory.example')));
        $this->assertSame([(int)$student->id], self::ids(student_search::search('vquintero@')));
    }

    /**
     * MDL-UNIT-001: A text spanning the first and last name together matches
     * through the full name.
     */
    public function test_search_matches_full_name_spanning_first_and_last_name(): void {
        $this->resetAfterTest();

        [$course1] = $this->create_courses_and_users();

        $generator = $this->getDataGenerator();
        $student = $generator->create_user(['firstname' => 'Valentina', 'lastname' => 'Quintero']);
        $generator->enrol_user($student->id, $course1->id, 'student');

        $this->set_system_manager();

        $this->assertSame([(int)$student->id], self::ids(student_search::search('Valentina Quintero')));
        $this->assertSame([(int)$student->id], self::ids(student_search::search('tina Quin')));
    }

    /**
     * MDL-UNIT-001: Matching does not distinguish upper and lower case.
     */
    public function test_search_is_case_insensitive(): void {
        $this->resetAfterTest();

        [$course1] = $this->create_courses_and_users();

        $generator = $this->getDataGenerator();
        $student = $generator->create_user(['firstname' => 'Valentina', 'lastname' => 'Quintero']);
        $generator->enrol_user($student->id, $course1->id, 'student');

        $this->set_system_manager();

        $this->assertSame([(int)$student->id], self::ids(student_search::search('VALENTINA')));
        $this->assertSame([(int)$student->id], self::ids(student_search::search('quintero')));
        $this->assertSame([(int)$student->id], self::ids(student_search::search('vAlEnTiNa qUiNtErO')));
    }

    /**
     * MDL-UNIT-001: A single character is enough to obtain matches.
     */
    public function test_search_with_a_single_character_returns_matches(): void {
        $this->resetAfterTest();

        [$course1] = $this->create_courses_and_users();

        $generator = $this->getDataGenerator();
        // 'q' appears in no other fixture name or generated email address.
        $student = $generator->create_user(['firstname' => 'Quirino', 'lastname' => 'Zabaleta']);
        $generator->enrol_user($student->id, $course1->id, 'student');

        $this->set_system_manager();

        $this->assertSame([(int)$student->id], self::ids(student_search::search('Q')));
        $this->assertSame([(int)$student->id], self::ids(student_search::search('q')));
    }

    /**
     * MDL-UNIT-002: Results are ordered alphabetically by last name and then
     * by first name.
     */
    public function test_search_orders_by_lastname_then_firstname(): void {
        $this->resetAfterTest();

        [$course1] = $this->create_courses_and_users();

        $generator = $this->getDataGenerator();
        $zetaalpha = $generator->create_user([
            'firstname' => 'Alpha', 'lastname' => 'Zeta', 'email' => 'za@orderly.example',
        ]);
        $betabravo = $generator->create_user([
            'firstname' => 'Bravo', 'lastname' => 'Beta', 'email' => 'bb@orderly.example',
        ]);
        $betaalpha = $generator->create_user([
            'firstname' => 'Alpha', 'lastname' => 'Beta', 'email' => 'ba@orderly.example',
        ]);
        foreach ([$zetaalpha, $betabravo, $betaalpha] as $student) {
            $generator->enrol_user($student->id, $course1->id, 'student');
        }

        $this->set_system_manager();

        $this->assertSame(
            [(int)$betaalpha->id, (int)$betabravo->id, (int)$zetaalpha->id],
            self::ids(student_search::search('orderly.example'))
        );
    }

    /**
     * MDL-UNIT-002: Deleted users are excluded even when they still hold the
     * student role.
     */
    public function test_search_excludes_deleted_users(): void {
        global $DB;

        $this->resetAfterTest();

        [$course1, $course2, $student1] = $this->create_courses_and_users();

        $generator = $this->getDataGenerator();
        $deleted = $generator->create_user(['firstname' => 'Alpha', 'lastname' => 'Deleted']);
        $generator->enrol_user($deleted->id, $course1->id, 'student');
        // Flag the account as deleted while keeping its role assignment.
        $DB->set_field('user', 'deleted', 1, ['id' => $deleted->id]);

        $this->set_system_manager();

        $ids = self::ids(student_search::search('Alpha'));

        $this->assertContains((int)$student1->id, $ids);
        $this->assertNotContains((int)$deleted->id, $ids);
    }

    /**
     * MDL-UNIT-002: Only users holding the student role appear; teachers,
     * managers and roleless users matching the text are excluded.
     */
    public function test_search_excludes_users_without_the_student_role(): void {
        global $DB;

        $this->resetAfterTest();

        [$course1, $course2, $student1] = $this->create_courses_and_users();

        $generator = $this->getDataGenerator();
        $teacher = $generator->create_user(['firstname' => 'Alpha', 'lastname' => 'Teacher']);
        $generator->enrol_user($teacher->id, $course1->id, 'editingteacher');
        $roleless = $generator->create_user(['firstname' => 'Alpha', 'lastname' => 'Roleless']);

        $manager = $this->set_system_manager();
        $DB->set_field('user', 'firstname', 'Alpha', ['id' => $manager->id]);

        $ids = self::ids(student_search::search('Alpha'));

        $this->assertContains((int)$student1->id, $ids);
        $this->assertNotContains((int)$teacher->id, $ids);
        $this->assertNotContains((int)$roleless->id, $ids);
        $this->assertNotContains((int)$manager->id, $ids);
    }

    /**
     * MDL-UNIT-002: A student holding the role in several courses appears once.
     */
    public function test_search_returns_a_student_enrolled_in_several_courses_once(): void {
        $this->resetAfterTest();

        [$course1, $course2] = $this->create_courses_and_users();

        $generator = $this->getDataGenerator();
        $student = $generator->create_user(['firstname' => 'Multi', 'lastname' => 'Course']);
        $generator->enrol_user($student->id, $course1->id, 'student');
        $generator->enrol_user($student->id, $course2->id, 'student');

        $this->set_system_manager();

        $result = student_search::search('Multi');

        $this->assertSame([(int)$student->id], self::ids($result));
        $this->assertFalse($result['hasmore']);
    }

    /**
     * MDL-UNIT-005: An empty or whitespace-only query returns no results and
     * raises no error.
     */
    public function test_search_with_empty_or_whitespace_query_returns_nothing(): void {
        $this->resetAfterTest();

        $this->create_courses_and_users();
        $this->set_system_manager();

        $this->assertSame(['students' => [], 'hasmore' => false], student_search::search(''));
        $this->assertSame(['students' => [], 'hasmore' => false], student_search::search('   '));
        $this->assertSame(['students' => [], 'hasmore' => false], student_search::search("\t\n"));
    }

    /**
     * MDL-UNIT-005: SQL wildcard characters are searched literally and do not
     * widen the matches.
     */
    public function test_search_treats_wildcard_characters_literally(): void {
        $this->resetAfterTest();

        [$course1] = $this->create_courses_and_users();

        $generator = $this->getDataGenerator();
        $percent = $generator->create_user(['firstname' => 'Hundred', 'lastname' => 'Percent%Sign']);
        $generator->enrol_user($percent->id, $course1->id, 'student');
        $underscore = $generator->create_user(['firstname' => 'Under', 'lastname' => 'Score_Sign']);
        $generator->enrol_user($underscore->id, $course1->id, 'student');

        $this->set_system_manager();

        // Without escaping, '%' alone would match every student.
        $this->assertSame([(int)$percent->id], self::ids(student_search::search('%')));
        $this->assertSame([(int)$percent->id], self::ids(student_search::search('Percent%')));

        // Without escaping, '_' would match any single character.
        $this->assertSame([(int)$underscore->id], self::ids(student_search::search('_')));
        $this->assertSame([(int)$underscore->id], self::ids(student_search::search('Score_S')));
        $this->assertSame([], self::ids(student_search::search('Alp_a')));
    }

    /**
     * MDL-UNIT-005: Accented and non-latin queries run without error and find
     * matching students.
     */
    public function test_search_with_unicode_query_does_not_fail(): void {
        $this->resetAfterTest();

        [$course1] = $this->create_courses_and_users();

        $generator = $this->getDataGenerator();
        $accented = $generator->create_user(['firstname' => 'José', 'lastname' => 'Muñoz']);
        $generator->enrol_user($accented->id, $course1->id, 'student');
        $cyrillic = $generator->create_user(['firstname' => 'Иван', 'lastname' => 'Петров']);
        $generator->enrol_user($cyrillic->id, $course1->id, 'student');

        $this->set_system_manager();

        $this->assertContains((int)$accented->id, self::ids(student_search::search('José')));
        $this->assertContains((int)$accented->id, self::ids(student_search::search('Muñoz')));
        $this->assertContains((int)$cyrillic->id, self::ids(student_search::search('Петров')));
        $this->assertSame([], self::ids(student_search::search('日本語テキスト')));
    }

    /**
     * MDL-INT-006: A suspended student must not be consultable by typing the
     * user id in the page URL.
     * [Pendiente:skip] direct consultation does not apply the suspension exclusion yet.
     */
    public function test_suspended_student_is_not_reachable_by_direct_url(): void {
        $this->markTestSkipped('[Pendiente:skip] suspended students are still reachable by direct URL');
    }

    /**
     * MDL-INT-008: Custom roles based on the student archetype must be
     * recognised by the search and the direct consultation.
     * [Pendiente:skip] only the standard student role shortname is recognised.
     */
    public function test_custom_student_roles_are_recognised(): void {
        $this->markTestSkipped('[Pendiente:skip] only role shortname "student" is recognized');
    }
}

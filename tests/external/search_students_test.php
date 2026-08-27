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
 * Tests for the student search external function of report_lifestory.
 *
 * @package   report_lifestory
 * @category  test
 * @copyright 2026 Datacurso
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_lifestory\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

use core_external\external_api;

/**
 * Tests for the search_students external function: authentication,
 * capability check, response contract and search scoping.
 *
 * @package   report_lifestory
 * @category  test
 * @covers    \report_lifestory\external\search_students
 */
final class search_students_test extends \externallib_advanced_testcase {
    /**
     * Creates a course with a student and a viewer holding both the report
     * view capability and grade access in the course.
     *
     * @return array Array with course, student and viewer.
     */
    private function create_fixture(): array {
        $generator = $this->getDataGenerator();

        $course = $generator->create_course();

        $student = $generator->create_user([
            'firstname' => 'Alpha',
            'lastname' => 'Alpine',
            'email' => 'alpha.alpine@example.com',
        ]);
        $generator->enrol_user($student->id, $course->id, 'student');

        $viewer = $generator->create_user();
        $generator->enrol_user($viewer->id, $course->id, 'editingteacher');
        $this->assign_report_view_role($viewer->id);

        return [$course, $student, $viewer];
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
     * MDL-INT-004: The service refuses requests from users who are not logged in.
     *
     * Under PHPUnit the login redirect raised by require_login() surfaces as
     * a moodle_exception, the parent class of require_login_exception.
     */
    public function test_execute_requires_login(): void {
        $this->resetAfterTest();

        $this->create_fixture();
        $this->setUser(null);

        $this->expectException(\moodle_exception::class);
        search_students::execute('Alpha');
    }

    /**
     * MDL-INT-004: The service rejects authenticated users lacking the report
     * view capability.
     */
    public function test_execute_requires_report_view_capability(): void {
        $this->resetAfterTest();

        [$course] = $this->create_fixture();

        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);

        $this->expectException(\required_capability_exception::class);
        search_students::execute('Alpha');
    }

    /**
     * MDL-INT-004: Every result carries the id, full name, email and a
     * pluginfile URL for the profile image of the student.
     */
    public function test_execute_returns_identity_and_profile_image_url(): void {
        $this->resetAfterTest();

        [, $student, $viewer] = $this->create_fixture();
        $this->setUser($viewer);

        $result = external_api::clean_returnvalue(
            search_students::execute_returns(),
            search_students::execute('Alpha')
        );

        $this->assertCount(1, $result['students']);
        $entry = $result['students'][0];

        $this->assertSame((int)$student->id, $entry['id']);
        $this->assertSame(fullname($student), $entry['fullname']);
        $this->assertSame('alpha.alpine@example.com', $entry['email']);

        $usercontext = \context_user::instance($student->id);
        $this->assertStringContainsString('/pluginfile.php', $entry['profileimageurl']);
        $this->assertStringContainsString('/' . $usercontext->id . '/user/icon/', $entry['profileimageurl']);
        $this->assertStringEndsWith('f1', $entry['profileimageurl']);
    }

    /**
     * MDL-INT-004: The service returns at most ten students and flags that
     * more students match when the eleventh match falls beyond the limit.
     */
    public function test_execute_caps_results_and_reports_more_matches(): void {
        $this->resetAfterTest();

        [$course, , $viewer] = $this->create_fixture();

        $generator = $this->getDataGenerator();
        for ($i = 1; $i <= 11; $i++) {
            $extra = $generator->create_user(['firstname' => 'Gamma', 'lastname' => sprintf('Match%02d', $i)]);
            $generator->enrol_user($extra->id, $course->id, 'student');
        }

        $this->setUser($viewer);

        $result = external_api::clean_returnvalue(search_students::execute_returns(), search_students::execute('Gamma'));

        $this->assertCount(10, $result['students']);
        $this->assertTrue($result['hasmore']);

        $result = external_api::clean_returnvalue(search_students::execute_returns(), search_students::execute('Alpha'));

        $this->assertCount(1, $result['students']);
        $this->assertFalse($result['hasmore']);
    }

    /**
     * MDL-INT-004: The service applies the same matching and scoping rules as
     * the search helper: suspended students and students of courses without
     * grade access are excluded.
     */
    public function test_execute_applies_search_scope(): void {
        $this->resetAfterTest();

        [$course, $student, $viewer] = $this->create_fixture();

        $generator = $this->getDataGenerator();
        $suspended = $generator->create_user(['firstname' => 'Alpha', 'lastname' => 'Suspended', 'suspended' => 1]);
        $generator->enrol_user($suspended->id, $course->id, 'student');

        $othercourse = $generator->create_course();
        $outofscope = $generator->create_user(['firstname' => 'Alpha', 'lastname' => 'Elsewhere']);
        $generator->enrol_user($outofscope->id, $othercourse->id, 'student');

        $this->setUser($viewer);

        $result = external_api::clean_returnvalue(search_students::execute_returns(), search_students::execute('alp'));

        $this->assertSame([(int)$student->id], array_column($result['students'], 'id'));
        $this->assertFalse($result['hasmore']);
    }

    /**
     * MDL-CTR-002: The raw execute() result exposes exactly the declared
     * fields with the declared types and passes the return value cleaning.
     */
    public function test_execute_result_matches_declared_contract(): void {
        $this->resetAfterTest();

        [, $student, $viewer] = $this->create_fixture();
        $this->setUser($viewer);

        $raw = search_students::execute('Alpha');

        $this->assertSame(['students', 'hasmore'], array_keys($raw));
        $this->assertIsBool($raw['hasmore']);
        $this->assertIsArray($raw['students']);
        $this->assertCount(1, $raw['students']);

        $entry = $raw['students'][0];
        $this->assertSame(['id', 'fullname', 'email', 'profileimageurl'], array_keys($entry));
        $this->assertIsInt($entry['id']);
        $this->assertSame((int)$student->id, $entry['id']);
        $this->assertIsString($entry['fullname']);
        $this->assertNotSame('', $entry['fullname']);
        $this->assertIsString($entry['email']);
        $this->assertNotFalse(filter_var($entry['email'], FILTER_VALIDATE_EMAIL));
        $this->assertIsString($entry['profileimageurl']);
        $this->assertNotFalse(filter_var($entry['profileimageurl'], FILTER_VALIDATE_URL));

        // Cleaning against the declared structure must accept the raw result unchanged.
        $clean = external_api::clean_returnvalue(search_students::execute_returns(), $raw);
        $this->assertSame($raw, $clean);
    }

    /**
     * MDL-CTR-002: An empty result still honours the contract.
     */
    public function test_execute_empty_result_matches_declared_contract(): void {
        $this->resetAfterTest();

        [, , $viewer] = $this->create_fixture();
        $this->setUser($viewer);

        $raw = search_students::execute('nobody-matches-this');

        $this->assertSame(['students' => [], 'hasmore' => false], $raw);
        $this->assertSame($raw, external_api::clean_returnvalue(search_students::execute_returns(), $raw));
    }
}

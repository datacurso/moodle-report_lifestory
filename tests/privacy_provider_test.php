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
 * Privacy provider test for report_lifestory.
 *
 * @package   report_lifestory
 * @category  test
 * @copyright   2025 Datacurso
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_lifestory;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;
use report_lifestory\local\feedback_store;
use report_lifestory\privacy\provider;

/**
 * Unit tests for report_lifestory privacy provider.
 *
 * @package   report_lifestory
 * @category  test
 * @covers    \report_lifestory\privacy\provider
 */
final class privacy_provider_test extends provider_testcase {
    /**
     * Ensure that the provider declares the correct external AI service.
     * @covers \report_lifestory\privacy\provider::get_metadata
     */
    public function test_get_metadata_declares_external_service(): void {
        $collection = new collection('report_lifestory');
        $metadata = provider::get_metadata($collection)->get_collection();

        $links = [];
        foreach ($metadata as $item) {
            if ($item->get_name() === 'ai_provider') {
                $links[] = $item;
            }
        }

        $this->assertCount(1, $links, 'Exactly one ai_provider external location should be declared in get_metadata().');

        $item = reset($links);
        $this->assertInstanceOf(\core_privacy\local\metadata\types\external_location::class, $item);

        $fields = $item->get_privacy_fields();
        $expected = [
            'site_id',
            'site_url',
            'userid',
            'student_id',
            'student_name',
            'courses',
            'timezone',
            'lang',
        ];
        $this->assertEqualsCanonicalizing($expected, array_keys($fields));

        // The summary and every field description must resolve to a real language string.
        $stringman = get_string_manager();
        $this->assertTrue($stringman->string_exists($item->get_summary(), 'report_lifestory'));
        foreach ($fields as $field => $identifier) {
            $this->assertTrue(
                $stringman->string_exists($identifier, 'report_lifestory'),
                "Missing language string for privacy field '{$field}'."
            );
            $this->assertNotEmpty(get_string($identifier, 'report_lifestory'));
        }
    }

    /**
     * Ensure that the provider declares the stored feedback database table.
     * @covers \report_lifestory\privacy\provider::get_metadata
     */
    public function test_get_metadata_declares_feedback_table(): void {
        $collection = new collection('report_lifestory');
        $metadata = provider::get_metadata($collection)->get_collection();

        $tables = [];
        foreach ($metadata as $item) {
            if ($item->get_name() === 'report_lifestory_feedback') {
                $tables[] = $item;
            }
        }

        $this->assertCount(1, $tables, 'Exactly one report_lifestory_feedback table should be declared in get_metadata().');

        $item = reset($tables);
        $this->assertInstanceOf(\core_privacy\local\metadata\types\database_table::class, $item);

        $fields = $item->get_privacy_fields();
        $expected = [
            'studentid',
            'courseid',
            'feedback',
            'usermodified',
            'timecreated',
            'timemodified',
        ];
        $this->assertEqualsCanonicalizing($expected, array_keys($fields));

        // The summary and every field description must resolve to a real language string.
        $stringman = get_string_manager();
        $this->assertTrue($stringman->string_exists($item->get_summary(), 'report_lifestory'));
        foreach ($fields as $field => $identifier) {
            $this->assertTrue(
                $stringman->string_exists($identifier, 'report_lifestory'),
                "Missing language string for privacy field '{$field}'."
            );
            $this->assertNotEmpty(get_string($identifier, 'report_lifestory'));
        }
    }

    /**
     * Verify that only users with stored feedback get the system context.
     * @covers \report_lifestory\privacy\provider::get_contexts_for_userid
     */
    public function test_get_contexts_for_userid(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $student = $this->getDataGenerator()->create_user();
        $otheruser = $this->getDataGenerator()->create_user();

        feedback_store::save($student->id, 0, 'Stored feedback text.');

        $contextlist = provider::get_contexts_for_userid($student->id);
        $this->assertEquals([\context_system::instance()->id], $contextlist->get_contextids());

        $contextlist = provider::get_contexts_for_userid($otheruser->id);
        $this->assertEmpty($contextlist->get_contextids(), 'Users without stored feedback should have no contexts.');
    }

    /**
     * Verify that the stored feedback of a student is exported at the system context.
     * @covers \report_lifestory\privacy\provider::export_user_data
     */
    public function test_export_user_data(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $student = $this->getDataGenerator()->create_user();

        feedback_store::save($student->id, 0, 'Exported feedback text.');

        $context = \context_system::instance();
        $contextlist = new approved_contextlist($student, 'report_lifestory', [$context->id]);
        provider::export_user_data($contextlist);

        $writer = writer::with_context($context);
        $this->assertTrue($writer->has_any_data());

        $data = $writer->get_data([get_string('lifestory', 'report_lifestory')]);
        $this->assertNotEmpty($data->feedback);
        $this->assertSame('Exported feedback text.', reset($data->feedback)->feedback);
    }

    /**
     * Verify that deleting for one user removes only the rows of that student.
     * @covers \report_lifestory\privacy\provider::delete_data_for_user
     */
    public function test_delete_data_for_user(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $student1 = $this->getDataGenerator()->create_user();
        $student2 = $this->getDataGenerator()->create_user();

        feedback_store::save($student1->id, 0, 'Feedback for student one.');
        feedback_store::save($student2->id, 0, 'Feedback for student two.');

        $context = \context_system::instance();
        $contextlist = new approved_contextlist($student1, 'report_lifestory', [$context->id]);
        provider::delete_data_for_user($contextlist);

        $this->assertEquals(0, $DB->count_records('report_lifestory_feedback', ['studentid' => $student1->id]));
        $this->assertEquals(1, $DB->count_records('report_lifestory_feedback', ['studentid' => $student2->id]));
    }

    /**
     * Verify that all stored feedback is removed when deleting the system context.
     * @covers \report_lifestory\privacy\provider::delete_data_for_all_users_in_context
     */
    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $student1 = $this->getDataGenerator()->create_user();
        $student2 = $this->getDataGenerator()->create_user();

        feedback_store::save($student1->id, 0, 'Feedback for student one.');
        feedback_store::save($student2->id, 0, 'Feedback for student two.');

        provider::delete_data_for_all_users_in_context(\context_system::instance());

        $this->assertEquals(0, $DB->count_records('report_lifestory_feedback'));
    }

    /**
     * Verify that students and generators are listed for the system context.
     * @covers \report_lifestory\privacy\provider::get_users_in_context
     */
    public function test_get_users_in_context(): void {
        $this->resetAfterTest();

        $student = $this->getDataGenerator()->create_user();
        $generatoruser = $this->getDataGenerator()->create_user();
        $otheruser = $this->getDataGenerator()->create_user();

        $this->setUser($generatoruser);
        feedback_store::save($student->id, 0, 'Stored feedback text.');

        $userlist = new userlist(\context_system::instance(), 'report_lifestory');
        provider::get_users_in_context($userlist);

        $userids = $userlist->get_userids();
        $this->assertContains((int) $student->id, $userids);
        $this->assertContains((int) $generatoruser->id, $userids);
        $this->assertNotContains((int) $otheruser->id, $userids);
    }

    /**
     * Verify that deleting an approved userlist removes only the listed students.
     * @covers \report_lifestory\privacy\provider::delete_data_for_users
     */
    public function test_delete_data_for_users(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $student1 = $this->getDataGenerator()->create_user();
        $student2 = $this->getDataGenerator()->create_user();

        feedback_store::save($student1->id, 0, 'Feedback for student one.');
        feedback_store::save($student2->id, 0, 'Feedback for student two.');

        $context = \context_system::instance();
        $userlist = new approved_userlist($context, 'report_lifestory', [$student1->id]);
        provider::delete_data_for_users($userlist);

        $this->assertEquals(0, $DB->count_records('report_lifestory_feedback', ['studentid' => $student1->id]));
        $this->assertEquals(1, $DB->count_records('report_lifestory_feedback', ['studentid' => $student2->id]));
    }
}

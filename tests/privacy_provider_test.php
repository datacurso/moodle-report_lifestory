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
use core_privacy\tests\provider_testcase;
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

        $found = false;
        foreach ($metadata as $item) {
            if ($item->get_name() === 'ai_provider') {
                $found = true;
                $fields = $item->get_privacy_fields();

                // Verify expected data fields are declared.
                $this->assertArrayHasKey('site_id', $fields);
                $this->assertArrayHasKey('userid', $fields);
                $this->assertArrayHasKey('student_id', $fields);
                $this->assertArrayHasKey('student_name', $fields);
                $this->assertArrayHasKey('courses', $fields);
            }
        }

        $this->assertTrue($found, 'The ai_provider external location should be declared in get_metadata().');
    }

    /**
     * Verify that no contexts or local user data are stored.
     * @covers \report_lifestory\privacy\provider::get_contexts_for_userid
     */
    public function test_no_contexts_or_local_data(): void {
        $contextlist = provider::get_contexts_for_userid(999);
        $this->assertEmpty($contextlist->get_contextids(), 'report_lifestory should not store user contexts locally.');
    }
}

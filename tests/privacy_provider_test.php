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
     * Verify that no contexts or local user data are stored.
     * @covers \report_lifestory\privacy\provider::get_contexts_for_userid
     */
    public function test_no_contexts_or_local_data(): void {
        $contextlist = provider::get_contexts_for_userid(999);
        $this->assertEmpty($contextlist->get_contextids(), 'report_lifestory should not store user contexts locally.');
    }
}

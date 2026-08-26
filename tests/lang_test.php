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
 * Tests for the language pack parity of report_lifestory.
 *
 * @package   report_lifestory
 * @category  test
 * @copyright 2026 Datacurso
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_lifestory;

/**
 * Unit tests ensuring the nine language packs share the same keys and none
 * of them carries an empty text.
 *
 * @package   report_lifestory
 * @category  test
 * @coversNothing
 */
final class lang_test extends \basic_testcase {
    /** @var string[] The nine supported language codes. */
    private const LANGS = ['de', 'en', 'es', 'es_mx', 'es_mx_kids', 'fr', 'id', 'pt_br', 'ru'];

    /** @var string[] Strings added in the latest releases that every pack must carry. */
    private const RECENT_KEYS = [
        'searchbutton',
        'searchmorematches',
        'searchnoresults',
        'regeneratefeedback',
        'feedbackgeneratedon',
        'event:csvexported',
        'event:feedbackgenerated',
        'event:pdfexported',
        'event:reportviewed',
        'pdfnocoursedata',
        'studentlabel',
        'privacy:metadata:ai_provider',
        'privacy:metadata:ai_provider:siteid',
        'privacy:metadata:report_lifestory_feedback',
    ];

    /**
     * Loads the strings of a language pack.
     *
     * @param string $lang Language code.
     * @return array<string, string> Strings keyed by identifier.
     */
    private static function load_strings(string $lang): array {
        global $CFG;

        $file = $CFG->dirroot . '/report/lifestory/lang/' . $lang . '/report_lifestory.php';
        self::assertFileExists($file);

        $string = [];
        include($file);

        return $string;
    }

    /**
     * MDL-UNIT-026: Every language pack exists and holds exactly the same set
     * of keys as the English pack.
     */
    public function test_all_language_packs_share_the_english_key_set(): void {
        $english = self::load_strings('en');
        $this->assertNotEmpty($english);

        $expected = array_keys($english);
        sort($expected);

        foreach (self::LANGS as $lang) {
            $keys = array_keys(self::load_strings($lang));
            sort($keys);

            $this->assertSame(
                $expected,
                $keys,
                "Language pack '{$lang}' does not match the English key set. Missing: "
                    . implode(', ', array_diff($expected, $keys)) . '. Extra: '
                    . implode(', ', array_diff($keys, $expected)) . '.'
            );
        }
    }

    /**
     * MDL-UNIT-026: No key holds an empty text in any language pack.
     */
    public function test_no_language_pack_has_empty_values(): void {
        foreach (self::LANGS as $lang) {
            foreach (self::load_strings($lang) as $key => $value) {
                $this->assertIsString($value, "String '{$key}' in pack '{$lang}' is not a string.");
                $this->assertNotSame('', trim($value), "String '{$key}' in pack '{$lang}' is empty.");
            }
        }
    }

    /**
     * MDL-UNIT-026: The texts introduced by the latest releases are present
     * in all nine packs.
     */
    public function test_recent_strings_are_present_in_every_pack(): void {
        foreach (self::LANGS as $lang) {
            $strings = self::load_strings($lang);
            foreach (self::RECENT_KEYS as $key) {
                $this->assertArrayHasKey($key, $strings, "String '{$key}' is missing in pack '{$lang}'.");
            }
        }
    }
}

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
 * Tests for the CSV exporter of report_lifestory.
 *
 * @package   report_lifestory
 * @category  test
 * @copyright 2026 Datacurso
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_lifestory\local;

/**
 * Unit tests for the CSV assembly of report_lifestory.
 *
 * Covers the preservation of original UTF-8 characters across languages,
 * the presence of the header row, the escaping of embedded double quotes,
 * and the absence of a byte order mark in the assembled CSV string.
 *
 * @package   report_lifestory
 * @category  test
 * @covers    \report_lifestory\local\csv_exporter
 */
final class csv_exporter_test extends \advanced_testcase {
    /**
     * Builds a task or total entry with the payload shape used by the builder.
     *
     * @param string $name Item name.
     * @param string $feedback Feedback text.
     * @return array Task entry.
     */
    private static function task(string $name, string $feedback): array {
        return [
            'name' => $name,
            'calculated_weight' => 25.0,
            'grade' => 8.5,
            'range' => '0-10.00',
            'percentage' => 85.0,
            'feedback' => $feedback,
            'contribution_to_total' => 21.25,
        ];
    }

    /**
     * Builds a multilingual payload fixture mirroring the builder output shape.
     *
     * @return array Payload fixture.
     */
    private static function create_payload(): array {
        return [
            'student_id' => '42',
            'courses' => [
                [
                    'name' => 'Evaluación de diseño ñandú über',
                    'sections' => [
                        [
                            'name' => 'Leçon d\'œuvre à côté',
                            'tasks' => [
                                self::task('Prüfung größe ßtraße', 'Avaliação lição'),
                                self::task('Оценка успеваемости', 'Penilaian akhir'),
                                self::task('Quote task', 'She said "well done" today'),
                            ],
                            'total' => self::task('Section total', 'Solid progress overall'),
                        ],
                    ],
                    'total' => self::task('Course total', 'Great course result'),
                ],
            ],
        ];
    }

    /**
     * Ensures every international string survives byte-identical in the CSV.
     */
    public function test_build_csv_preserves_original_characters(): void {
        $csv = csv_exporter::build_csv(self::create_payload());

        $this->assertStringContainsString('Evaluación de diseño ñandú über', $csv);
        $this->assertStringContainsString('Leçon d\'œuvre à côté', $csv);
        $this->assertStringContainsString('Prüfung größe ßtraße', $csv);
        $this->assertStringContainsString('Avaliação lição', $csv);
        $this->assertStringContainsString('Оценка успеваемости', $csv);
        $this->assertStringContainsString('Penilaian akhir', $csv);
    }

    /**
     * Ensures the CSV starts with the localized header row.
     */
    public function test_build_csv_contains_header_row(): void {
        $csv = csv_exporter::build_csv(self::create_payload());

        $header = sprintf(
            "%s,%s,%s,%s,%s,%s\n",
            get_string('course', 'report_lifestory'),
            get_string('section', 'report_lifestory'),
            get_string('activity', 'report_lifestory'),
            get_string('gradepercent', 'report_lifestory'),
            get_string('range', 'report_lifestory'),
            get_string('feedback', 'report_lifestory')
        );

        $this->assertStringStartsWith($header, $csv);
    }

    /**
     * Ensures embedded double quotes are doubled inside a quoted field.
     */
    public function test_build_csv_escapes_embedded_double_quotes(): void {
        $csv = csv_exporter::build_csv(self::create_payload());

        $this->assertStringContainsString('"She said ""well done"" today"', $csv);
    }

    /**
     * Ensures the assembled CSV carries no byte order mark of its own.
     *
     * The byte order mark is prepended only at output time by the export
     * method, so the assembled string must stay free of it.
     */
    public function test_build_csv_has_no_byte_order_mark(): void {
        $csv = csv_exporter::build_csv(self::create_payload());

        $this->assertNotSame("\xEF\xBB\xBF", substr($csv, 0, 3));
    }
}

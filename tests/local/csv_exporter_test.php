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
     * Builds the placeholder total used by the payload builder for missing totals.
     *
     * @return array Placeholder total entry.
     */
    private static function missing_total(): array {
        return [
            'name' => 'Total not available',
            'calculated_weight' => null,
            'grade' => null,
            'range' => null,
            'percentage' => null,
            'feedback' => '',
            'contribution_to_total' => null,
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
     * Parses CSV content into rows using a stream so embedded newlines are honoured.
     *
     * @param string $csv CSV content.
     * @return array<int, array<int, string>> Parsed rows.
     */
    private static function parse_csv(string $csv): array {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $csv);
        rewind($handle);

        $rows = [];
        while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            $rows[] = $row;
        }
        fclose($handle);

        return $rows;
    }

    /**
     * MDL-UNIT-019: Every international string survives byte-identical in the CSV.
     */
    public function test_build_csv_preserves_original_characters(): void {
        $csv = csv_exporter::build_csv(self::create_payload());

        $this->assertStringContainsString('Evaluación de diseño ñandú über', $csv);
        $this->assertStringContainsString('Leçon d\'œuvre à côté', $csv);
        $this->assertStringContainsString('Prüfung größe ßtraße', $csv);
        $this->assertStringContainsString('Avaliação lição', $csv);
        $this->assertStringContainsString('Оценка успеваемости', $csv);
        $this->assertStringContainsString('Penilaian akhir', $csv);
        $this->assertTrue(mb_check_encoding($csv, 'UTF-8'));
    }

    /**
     * MDL-UNIT-020: The CSV starts with the localized six column header row.
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

        $rows = self::parse_csv($csv);
        $this->assertSame(
            ['Course', 'Section', 'Activity', 'Grade (%)', 'Range', 'Feedback'],
            $rows[0]
        );
    }

    /**
     * MDL-UNIT-020: Each task of each section produces one six column row
     * with the course, the section and the task data in declared order.
     */
    public function test_build_csv_produces_one_six_column_row_per_task(): void {
        $rows = self::parse_csv(csv_exporter::build_csv(self::create_payload()));

        // Header + 3 tasks + 1 section total + 1 course total.
        $this->assertCount(6, $rows);
        foreach ($rows as $row) {
            $this->assertCount(6, $row);
        }

        $this->assertSame(
            ['Evaluación de diseño ñandú über', 'Leçon d\'œuvre à côté', 'Prüfung größe ßtraße', '85', '0-10.00', 'Avaliação lição'],
            $rows[1]
        );
        $this->assertSame(
            ['Evaluación de diseño ñandú über', 'Leçon d\'œuvre à côté', 'Оценка успеваемости', '85', '0-10.00', 'Penilaian akhir'],
            $rows[2]
        );
        $this->assertSame('Quote task', $rows[3][2]);
    }

    /**
     * MDL-UNIT-021: Section and course totals appear as extra rows with the
     * total name in the activity column.
     */
    public function test_build_csv_includes_totals_as_rows(): void {
        $rows = self::parse_csv(csv_exporter::build_csv(self::create_payload()));

        $this->assertSame(
            ['Evaluación de diseño ñandú über', 'Leçon d\'œuvre à côté', 'Section total', '85', '0-10.00', 'Solid progress overall'],
            $rows[4]
        );
        $this->assertSame(
            ['Evaluación de diseño ñandú über', '', 'Course total', '85', '0-10.00', 'Great course result'],
            $rows[5]
        );
    }

    /**
     * MDL-UNIT-021: Missing percentages and ranges are written as a dash,
     * and placeholder totals produce a row of dashes without error.
     */
    public function test_build_csv_writes_dash_for_missing_values_and_placeholder_totals(): void {
        $payload = self::create_payload();
        $ungraded = self::task('Ungraded task', '');
        $ungraded['percentage'] = null;
        $ungraded['range'] = null;
        $payload['courses'][0]['sections'][0]['tasks'] = [$ungraded];
        $payload['courses'][0]['sections'][0]['total'] = self::missing_total();
        $payload['courses'][0]['total'] = self::missing_total();

        $rows = self::parse_csv(csv_exporter::build_csv($payload));

        $this->assertCount(4, $rows);
        $this->assertSame(['Ungraded task', '-', '-', ''], array_slice($rows[1], 2));
        $this->assertSame(
            ['Evaluación de diseño ñandú über', 'Leçon d\'œuvre à côté', 'Total not available', '-', '-', ''],
            $rows[2]
        );
        $this->assertSame(
            ['Evaluación de diseño ñandú über', '', 'Total not available', '-', '-', ''],
            $rows[3]
        );
    }

    /**
     * MDL-UNIT-022: Embedded double quotes are doubled inside a quoted field.
     */
    public function test_build_csv_escapes_embedded_double_quotes(): void {
        $csv = csv_exporter::build_csv(self::create_payload());

        $this->assertStringContainsString('"She said ""well done"" today"', $csv);

        $rows = self::parse_csv($csv);
        $this->assertSame('She said "well done" today', $rows[3][5]);
    }

    /**
     * MDL-UNIT-022: Commas and line breaks inside a text stay in a single
     * field so every row keeps its six columns.
     */
    public function test_build_csv_keeps_commas_and_newlines_in_one_field(): void {
        $payload = self::create_payload();
        $payload['courses'][0]['sections'][0]['tasks'] = [
            self::task('Essay, part one', "First line\nSecond line, with comma\r\nThird"),
        ];

        $rows = self::parse_csv(csv_exporter::build_csv($payload));

        $this->assertCount(4, $rows);
        foreach ($rows as $row) {
            $this->assertCount(6, $row);
        }
        $this->assertSame('Essay, part one', $rows[1][2]);
        $this->assertSame("First line\nSecond line, with comma\r\nThird", $rows[1][5]);
    }

    /**
     * MDL-UNIT-022: The download filename carries the history prefix and the
     * student id only, without the student's name or the date.
     */
    public function test_export_filename_uses_student_id_only(): void {
        if (!function_exists('xdebug_get_headers')) {
            $this->markTestSkipped('xdebug is required to capture the headers emitted by export().');
        }
        if (headers_sent()) {
            $this->markTestSkipped('Headers were already sent by the test runner; export() headers cannot be captured.');
        }

        $payload = self::create_payload();
        $payload['student_name'] = 'Alpha Alpine';

        ob_start();
        csv_exporter::export($payload);
        ob_end_clean();

        $headers = xdebug_get_headers();
        $disposition = '';
        foreach ($headers as $header) {
            if (stripos($header, 'Content-Disposition:') === 0) {
                $disposition = $header;
            }
        }

        $this->assertNotSame('', $disposition, 'No Content-Disposition header was emitted.');
        $this->assertStringContainsString('attachment; filename="history_42.csv"', $disposition);
        $this->assertStringNotContainsString('Alpha', $disposition);
        $this->assertStringNotContainsString(date('Y'), $disposition);
        $this->assertContains('Content-Type: text/csv; charset=utf-8', $headers);
    }

    /**
     * MDL-UNIT-023: The assembled CSV carries no byte order mark of its own.
     *
     * The byte order mark is prepended only at output time by the export
     * method, so the assembled string must stay free of it.
     */
    public function test_build_csv_has_no_byte_order_mark(): void {
        $csv = csv_exporter::build_csv(self::create_payload());

        $this->assertNotSame("\xEF\xBB\xBF", substr($csv, 0, 3));
    }

    /**
     * MDL-UNIT-023: The downloaded content starts with exactly one UTF-8 byte
     * order mark immediately followed by the header, with all international
     * characters intact.
     */
    public function test_export_output_starts_with_single_bom_followed_by_header(): void {
        $payload = self::create_payload();

        // The runner has already written to stdout, so the header() calls of
        // export() raise "headers already sent" warnings that are irrelevant
        // to the body under test; swallow them for the duration of the call.
        set_error_handler(static function (): bool {
            return true;
        }, E_WARNING);
        ob_start();
        try {
            csv_exporter::export($payload);
        } finally {
            $output = ob_get_clean();
            restore_error_handler();
        }

        $bom = "\xEF\xBB\xBF";
        $this->assertStringStartsWith($bom, $output);
        $this->assertSame(1, substr_count($output, $bom));
        $this->assertSame(csv_exporter::build_csv($payload), substr($output, 3));

        $header = get_string('course', 'report_lifestory') . ',' . get_string('section', 'report_lifestory');
        $this->assertStringStartsWith($bom . $header, $output);

        $this->assertStringContainsString('Оценка успеваемости', $output);
        $this->assertStringContainsString('ñandú über', $output);
        $this->assertTrue(mb_check_encoding($output, 'UTF-8'));
    }
}

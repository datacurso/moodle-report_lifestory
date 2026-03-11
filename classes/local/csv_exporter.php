<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * CSV export helper for report_lifestory.
 *
 * @package     report_lifestory
 * @copyright   2025 Datacurso
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_lifestory\local;

/**
 * Exports lifestory payloads as CSV.
 */
class csv_exporter {
    /**
     * Escape a value for CSV output.
     *
     * @param mixed $value Value to escape.
     * @return string
     */
    private static function csv_field($value): string {
        $value = (string)($value ?? '');
        return '"' . str_replace('"', '""', $value) . '"';
    }

    /**
     * Exports the student payload into a downloadable CSV file.
     *
     * @param array $payload Student data payload.
     * @return void
     */
    public static function export(array $payload): void {
        $csv = sprintf(
            "%s,%s,%s,%s,%s,%s\n",
            get_string('course', 'report_lifestory'),
            get_string('section', 'report_lifestory'),
            get_string('activity', 'report_lifestory'),
            get_string('gradepercent', 'report_lifestory'),
            get_string('range', 'report_lifestory'),
            get_string('feedback', 'report_lifestory')
        );

        foreach ($payload['courses'] as $course) {
            $coursename = $course['name'];

            foreach ($course['sections'] as $section) {
                $sectionname = $section['name'];

                foreach ($section['tasks'] as $task) {
                    $csv .= implode(',', [
                        self::csv_field($coursename),
                        self::csv_field($sectionname),
                        self::csv_field($task['name']),
                        self::csv_field($task['percentage'] ?? '-'),
                        self::csv_field($task['range'] ?? '-'),
                        self::csv_field($task['feedback'] ?? ''),
                    ]) . "\n";
                }

                if (!empty($section['total'])) {
                    $total = $section['total'];
                    $csv .= implode(',', [
                        self::csv_field($coursename),
                        self::csv_field($sectionname),
                        self::csv_field($total['name'] ?? get_string('total', 'report_lifestory')),
                        self::csv_field($total['percentage'] ?? '-'),
                        self::csv_field($total['range'] ?? '-'),
                        self::csv_field($total['feedback'] ?? ''),
                    ]) . "\n";
                }
            }

            if (!empty($course['total'])) {
                $total = $course['total'];
                $csv .= implode(',', [
                    self::csv_field($coursename),
                    self::csv_field(''),
                    self::csv_field($total['name'] ?? get_string('coursetotal', 'report_lifestory')),
                    self::csv_field($total['percentage'] ?? '-'),
                    self::csv_field($total['range'] ?? '-'),
                    self::csv_field($total['feedback'] ?? ''),
                ]) . "\n";
            }
        }

        $filename = 'history_' . $payload['student_id'] . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $csv;
    }
}

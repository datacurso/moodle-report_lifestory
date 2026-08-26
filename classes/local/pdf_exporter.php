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
 * PDF exporter for report_lifestory.
 *
 * @package     report_lifestory
 * @copyright   2025 Datacurso
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_lifestory\local;

/**
 * Builds and downloads PDF documents for the report.
 *
 * The document is rendered from the structured grades payload produced by
 * the payload builder through a dedicated Mustache template, so the PDF no
 * longer depends on the on-screen grade report markup.
 */
class pdf_exporter {
    /**
     * Build a readable and safe PDF filename.
     *
     * @param string $studentname Student full name.
     * @param int $time Timestamp used for the date part of the filename.
     * @return string
     */
    public static function build_filename(string $studentname, int $time): string {
        $name = trim($studentname);
        $name = preg_replace('/\s+/u', '_', $name);
        $name = preg_replace('/[^A-Za-z0-9_\-]/u', '', (string)$name);
        $name = trim((string)$name, '_-');

        if ($name === '') {
            $name = 'student';
        }

        $date = userdate($time, '%Y%m%d');
        return 'lifestory_' . $name . '_' . $date . '.pdf';
    }

    /**
     * Remove image tags from HTML before handing it to TCPDF.
     *
     * TCPDF fails when resolving some Moodle theme or remote image URLs, so
     * images are dropped from the feedback markup.
     *
     * @param string $html Raw HTML.
     * @return string
     */
    private static function strip_images(string $html): string {
        return (string)preg_replace('/<img\b[^>]*>/i', '', $html);
    }

    /**
     * Format a nullable numeric payload value for display.
     *
     * @param mixed $value Numeric value or null.
     * @return string Two decimal representation, or a dash when the value is missing.
     */
    private static function format_number($value): string {
        if ($value === null || $value === '') {
            return '-';
        }
        return number_format((float)$value, 2);
    }

    /**
     * Convert a payload task or total entry into a template row.
     *
     * @param array|null $entry Task or total entry from the payload.
     * @return array|null Row for the template, or null when the entry is missing.
     */
    private static function build_row(?array $entry): ?array {
        if ($entry === null) {
            return null;
        }

        $range = $entry['range'] ?? null;

        return [
            'name' => (string)($entry['name'] ?? ''),
            'calculatedweight' => self::format_number($entry['calculated_weight'] ?? null),
            'grade' => self::format_number($entry['grade'] ?? null),
            'range' => ($range === null || $range === '') ? '-' : (string)$range,
            'percentage' => self::format_number($entry['percentage'] ?? null),
            'feedback' => (string)($entry['feedback'] ?? ''),
            'contributiontototal' => self::format_number($entry['contribution_to_total'] ?? null),
        ];
    }

    /**
     * Convert a payload course into a template course entry.
     *
     * @param array $course Course entry from the payload.
     * @return array Course entry for the template.
     */
    private static function build_course(array $course): array {
        $sections = [];
        $hastasks = false;

        foreach ($course['sections'] ?? [] as $section) {
            $tasks = [];
            foreach ($section['tasks'] ?? [] as $task) {
                $tasks[] = self::build_row($task);
            }
            if (!empty($tasks)) {
                $hastasks = true;
            }

            $sections[] = [
                'name' => (string)($section['name'] ?? ''),
                'tasks' => $tasks,
                'total' => self::build_row($section['total'] ?? null),
            ];
        }

        $coursetotal = $course['total'] ?? null;
        $hastotalgrade = is_array($coursetotal) && isset($coursetotal['grade']);

        return [
            'name' => (string)($course['name'] ?? ''),
            'hasdata' => $hastasks || $hastotalgrade,
            'sections' => $sections,
            'total' => self::build_row($coursetotal),
        ];
    }

    /**
     * Build the PDF document HTML from the AI feedback and the grades payload.
     *
     * @param string $studentname Student full name.
     * @param string $feedbackmarkdown Feedback text in markdown.
     * @param array $payload Student data payload as produced by the payload builder.
     * @return string HTML rendered from the pdf_document template.
     */
    public static function build_html(string $studentname, string $feedbackmarkdown, array $payload): string {
        global $OUTPUT;

        $courses = [];
        foreach ($payload['courses'] ?? [] as $course) {
            $courses[] = self::build_course($course);
        }

        $context = [
            'title' => format_string(get_string('lifestory', 'report_lifestory')),
            'studentlabel' => format_string(get_string('studentlabel', 'report_lifestory')),
            'studentname' => $studentname,
            'feedbacktitle' => format_string(get_string('feedbackfromai', 'report_lifestory')),
            'feedbackhtml' => self::strip_images(format_text($feedbackmarkdown, FORMAT_MARKDOWN)),
            'courses' => $courses,
        ];

        return $OUTPUT->render_from_template('report_lifestory/pdf_document', $context);
    }

    /**
     * Download PDF with AI feedback and course grade data.
     *
     * @param string $studentname Student full name.
     * @param string $feedbackmarkdown Feedback text in markdown.
     * @param array $payload Student data payload as produced by the payload builder.
     * @param int $userid Student user ID.
     * @return void
     */
    public static function download(string $studentname, string $feedbackmarkdown, array $payload, int $userid): void {
        global $CFG;

        require_once($CFG->libdir . '/pdflib.php');

        $pdf = new \pdf('P', 'mm', 'A4', true, 'UTF-8');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(12, 12, 12);
        $pdf->SetAutoPageBreak(true, 12);
        $pdf->AddPage();

        $pdf->writeHTML(self::build_html($studentname, $feedbackmarkdown, $payload), true, false, false, false, '');

        $pdf->Output(self::build_filename($studentname, time()), 'D');
        exit;
    }
}

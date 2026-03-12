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
 */
class pdf_exporter {
    /**
     * Build a readable and safe PDF filename.
     *
     * @param string $studentname Student full name.
     * @return string
     */
    private static function build_filename(string $studentname): string {
        $name = trim($studentname);
        $name = preg_replace('/\s+/u', '_', $name);
        $name = preg_replace('/[^A-Za-z0-9_\-]/u', '', (string)$name);
        $name = trim((string)$name, '_-');

        if ($name === '') {
            $name = 'student';
        }

        $date = userdate(time(), '%Y%m%d');
        return 'lifestory_' . $name . '_' . $date . '.pdf';
    }

    /**
     * Normalize text for comparisons.
     *
     * @param string $text Input text.
     * @return string
     */
    private static function normalize_text(string $text): string {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\xc2\xa0", ' ', $text);
        $text = preg_replace('/\s+/u', ' ', $text);
        return \core_text::strtolower(trim((string)$text));
    }

    /**
     * Remove the leading empty data column when present.
     *
     * @param string $html Table HTML.
     * @return string
     */
    private static function remove_leading_empty_column(string $html): string {
        if ($html === '') {
            return $html;
        }

        $internalerrors = libxml_use_internal_errors(true);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $loaded = $dom->loadHTML('<?xml encoding="utf-8" ?><div>' . $html . '</div>');
        libxml_clear_errors();
        libxml_use_internal_errors($internalerrors);

        if (!$loaded) {
            return $html;
        }

        $xpath = new \DOMXPath($dom);
        $tables = $xpath->query('//table');
        if (!$tables) {
            return $html;
        }

        foreach ($tables as $table) {
            $removedcells = 0;
            $firstcells = $xpath->query('.//tr/td[1]', $table);
            if ($firstcells) {
                foreach ($firstcells as $cell) {
                    $celltext = self::normalize_text($cell->textContent ?? '');
                    if ($celltext === '' && $cell->parentNode) {
                        $cell->parentNode->removeChild($cell);
                        $removedcells++;
                    }
                }
            }

            if ($removedcells === 0) {
                continue;
            }

            $headerfirst = $xpath->query('.//tr[th][1]/th[1]', $table)->item(0);
            if ($headerfirst instanceof \DOMElement && $headerfirst->hasAttribute('colspan')) {
                $colspan = (int)$headerfirst->getAttribute('colspan');
                if ($colspan > 2) {
                    $headerfirst->setAttribute('colspan', (string)($colspan - 1));
                } else if ($colspan === 2) {
                    $headerfirst->removeAttribute('colspan');
                }
            }
        }

        $container = $dom->getElementsByTagName('div')->item(0);
        return $container ? (string)$dom->saveHTML($container) : $html;
    }

    /**
     * Clean HTML for TCPDF compatibility.
     *
     * @param string $html Raw HTML.
     * @return string
     */
    private static function sanitize_html_for_pdf(string $html): string {
        // TCPDF fails when resolving some Moodle theme/image URLs in <img> tags.
        $html = preg_replace('/<img\b[^>]*>/i', '', $html);
        // Remove inline background images that can trigger similar issues.
        $html = preg_replace('/background-image\s*:\s*url\([^)]*\)\s*;?/i', '', $html);
        return (string)$html;
    }

    /**
     * Keep course report table markup when present.
     *
     * @param string $html Raw report HTML.
     * @return string
     */
    private static function extract_report_tables(string $html): string {
        if (!preg_match_all('/<table\b[^>]*>.*?<\/table>/is', $html, $matches)) {
            return $html;
        }

        return implode('<br>', $matches[0]);
    }

    /**
     * Remove duplicated course title row from table body.
     *
     * @param string $html Table HTML.
     * @param string $coursename Course name shown as heading.
     * @return string
     */
    private static function remove_duplicate_course_row(string $html, string $coursename): string {
        if ($html === '' || $coursename === '') {
            return $html;
        }

        $target = self::normalize_text($coursename);
        if ($target === '') {
            return $html;
        }

        $internalerrors = libxml_use_internal_errors(true);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $loaded = $dom->loadHTML('<?xml encoding="utf-8" ?><div>' . $html . '</div>');
        libxml_clear_errors();
        libxml_use_internal_errors($internalerrors);

        if (!$loaded) {
            return $html;
        }

        $xpath = new \DOMXPath($dom);
        $tables = $xpath->query('//table');
        if (!$tables) {
            return $html;
        }

        foreach ($tables as $table) {
            $rows = $xpath->query('.//tr', $table);
            if (!$rows || $rows->length === 0) {
                continue;
            }

            $rowindex = 0;
            foreach ($rows as $row) {
                $normalizedrow = self::normalize_text($row->textContent ?? '');
                if ($rowindex <= 2 && $normalizedrow === $target) {
                    $row->parentNode->removeChild($row);
                    break;
                }
                $rowindex++;
            }
        }

        $container = $dom->getElementsByTagName('div')->item(0);
        return $container ? (string)$dom->saveHTML($container) : $html;
    }

    /**
     * Download PDF with AI feedback and course data.
     *
     * @param string $studentname Student full name.
     * @param string $feedbackmarkdown Feedback text in markdown.
     * @param array $coursesdata Course report data.
     * @param int $userid Student user ID.
     * @return void
     */
    public static function download(string $studentname, string $feedbackmarkdown, array $coursesdata, int $userid): void {
        global $CFG;

        require_once($CFG->libdir . '/pdflib.php');

        $pdf = new \pdf('P', 'mm', 'A4', true, 'UTF-8');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(12, 12, 12);
        $pdf->SetAutoPageBreak(true, 12);
        $pdf->AddPage();

        $title = format_string(get_string('lifestory', 'report_lifestory'));
        $feedbacktitle = format_string(get_string('feedbackfromai', 'report_lifestory'));
        $studentlabel = format_string(get_string('studentlabel', 'report_lifestory'));

        $feedbackhtml = self::sanitize_html_for_pdf(format_text($feedbackmarkdown, FORMAT_MARKDOWN));

        $html = '<style>
            h1 { text-align: center; }
            h2 { margin-top: 12px; }
            table { width: 100%; border-collapse: collapse; margin-top: 6px; margin-bottom: 12px; }
            th, td { border: 1px solid #9aa0a6; padding: 4px; }
            th { background-color: #f1f3f4; }
        </style>';

        $html .= '<h1>' . s($title) . '</h1>';
        $html .= '<p><strong>' . s($studentlabel) . ':</strong> ' . s($studentname) . '</p>';
        $html .= '<h2>' . s($feedbacktitle) . '</h2>';
        $html .= '<div>' . $feedbackhtml . '</div>';

        foreach ($coursesdata as $course) {
            $coursename = isset($course['fullname']) ? $course['fullname'] : '';
            $reporthtml = isset($course['reporthtml']) ? (string)$course['reporthtml'] : '';
            $reporthtml = self::extract_report_tables($reporthtml);
            $reporthtml = self::remove_duplicate_course_row($reporthtml, (string)$coursename);
            $reporthtml = self::remove_leading_empty_column($reporthtml);
            $reporthtml = self::sanitize_html_for_pdf($reporthtml);

            if (trim(strip_tags($reporthtml)) === '') {
                continue;
            }

            $html .= '<h2>' . s((string)$coursename) . '</h2>';
            $html .= '<div>' . (string)$reporthtml . '</div>';
        }

        $html = preg_replace('/(?:<br\s*\/?>\s*)+$/i', '', $html);

        $pdf->writeHTML($html, true, false, false, false, '');

        $lastpage = $pdf->getNumPages();
        if ($lastpage > 1) {
            $pdf->setPage($lastpage);
            $margins = $pdf->getMargins();
            $currenty = $pdf->GetY();
            if ($currenty <= ($margins['top'] + 12)) {
                $pdf->deletePage($lastpage);
            }
        }

        $filename = self::build_filename($studentname);
        $pdf->Output($filename, 'D');
        exit;
    }
}

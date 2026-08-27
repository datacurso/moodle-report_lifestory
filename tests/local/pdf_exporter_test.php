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
 * Tests for the PDF exporter of report_lifestory.
 *
 * @package   report_lifestory
 * @category  test
 * @copyright 2026 Datacurso
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_lifestory\local;

/**
 * Unit tests for the PDF document HTML assembled from the grades payload.
 *
 * Covers the rendering of course, section, task and total rows, the dash
 * placeholder for missing numeric values, the empty course notice, the
 * markdown conversion of the AI feedback, the absence of on-screen grade
 * report artifacts and the construction of the download filename.
 *
 * @package   report_lifestory
 * @category  test
 * @covers    \report_lifestory\local\pdf_exporter
 */
final class pdf_exporter_test extends \advanced_testcase {
    /**
     * Builds a task or total entry with the payload shape used by the builder.
     *
     * @param string $name Item name.
     * @param float|null $grade Final grade or null when not graded.
     * @param string $feedback Feedback text.
     * @return array Task entry.
     */
    private static function task(string $name, ?float $grade, string $feedback = ''): array {
        $graded = $grade !== null;
        return [
            'name' => $name,
            'calculated_weight' => $graded ? 50.0 : null,
            'grade' => $grade,
            'range' => '0-100.00',
            'percentage' => $graded ? $grade : null,
            'feedback' => $feedback,
            'contribution_to_total' => $graded ? 40.0 : null,
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
     * Builds a two course payload fixture mirroring the builder output shape.
     *
     * @return array Payload fixture.
     */
    private static function create_payload(): array {
        return [
            'userid' => '2',
            'student_id' => '42',
            'student_name' => 'Alpha Alpine',
            'courses' => [
                [
                    'name' => 'Curso de diseño',
                    'sections' => [
                        [
                            'name' => 'Unidad uno',
                            'tasks' => [
                                self::task('Tarea completa', 80.0, 'Excelente trabajo, ñandú über — très bien'),
                                self::task('Tarea pendiente', null),
                            ],
                            'total' => self::task('Unidad uno total', 80.0),
                        ],
                    ],
                    'total' => self::task('Curso de diseño total', 80.0),
                ],
                [
                    'name' => 'Curso vacío',
                    'sections' => [],
                    'total' => self::missing_total(),
                ],
            ],
        ];
    }

    /**
     * Prepares an admin session so templates can be rendered.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->setAdminUser();
    }

    /**
     * MDL-UNIT-024: The document contains the course structure, values and totals.
     *
     * @return void
     */
    public function test_build_html_renders_grades_payload(): void {
        $html = pdf_exporter::build_html('Alpha Alpine', 'Great progress.', self::create_payload());

        $this->assertStringContainsString('Alpha Alpine', $html);
        $this->assertStringContainsString('Curso de diseño', $html);
        $this->assertStringContainsString('Unidad uno', $html);
        $this->assertStringContainsString('Tarea completa', $html);
        $this->assertStringContainsString('>80.00</td>', $html);
        $this->assertStringContainsString('>0-100.00</td>', $html);
        $this->assertStringContainsString('>50.00</td>', $html);
        $this->assertStringContainsString('>40.00</td>', $html);
        $this->assertStringContainsString('Excelente trabajo, ñandú über — très bien', $html);
        $this->assertStringContainsString('Unidad uno total', $html);
        $this->assertStringContainsString('Curso de diseño total', $html);
        $this->assertStringContainsString('font-weight: bold;">Unidad uno total</td>', $html);
        $this->assertStringContainsString(get_string('calculatedweight', 'report_lifestory'), $html);
        $this->assertStringContainsString(get_string('contributiontototal', 'report_lifestory'), $html);
    }

    /**
     * MDL-UNIT-024: The document carries the report title, the student line
     * and the AI feedback heading, plus every table column heading.
     *
     * @return void
     */
    public function test_build_html_renders_title_student_line_and_headings(): void {
        $html = pdf_exporter::build_html('Alpha Alpine', 'Great progress.', self::create_payload());

        $title = get_string('lifestory', 'report_lifestory');
        $this->assertMatchesRegularExpression('/<h1[^>]*>\s*' . preg_quote($title, '/') . '\s*<\/h1>/', $html);
        $this->assertStringContainsString(
            '<strong>' . get_string('studentlabel', 'report_lifestory') . ':</strong> Alpha Alpine',
            $html
        );
        $this->assertStringContainsString(get_string('feedbackfromai', 'report_lifestory'), $html);
        $this->assertStringContainsString(get_string('coursetotal', 'report_lifestory'), $html);

        foreach (['activity', 'calculatedweight', 'grade', 'range', 'percentage', 'feedback', 'contributiontototal'] as $key) {
            $this->assertStringContainsString('>' . get_string($key, 'report_lifestory') . '</th>', $html);
        }

        // One heading per course and one sub heading per section.
        $this->assertSame(1, preg_match_all('/<h2>Curso de diseño<\/h2>/', $html));
        $this->assertSame(1, preg_match_all('/<h2>Curso vacío<\/h2>/', $html));
        $this->assertSame(1, preg_match_all('/<h3>Unidad uno<\/h3>/', $html));
    }

    /**
     * MDL-UNIT-024: Missing numeric values are rendered as a dash.
     *
     * @return void
     */
    public function test_build_html_renders_null_values_as_dash(): void {
        $html = pdf_exporter::build_html('Alpha Alpine', 'Great progress.', self::create_payload());

        $this->assertStringContainsString('Tarea pendiente', $html);
        $this->assertStringContainsString('>-</td>', $html);
    }

    /**
     * MDL-UNIT-024: A course without grade data shows its heading and the empty notice.
     *
     * @return void
     */
    public function test_build_html_shows_notice_for_course_without_data(): void {
        $html = pdf_exporter::build_html('Alpha Alpine', 'Great progress.', self::create_payload());
        $notice = get_string('pdfnocoursedata', 'report_lifestory');

        $this->assertStringContainsString('Curso vacío', $html);
        $this->assertStringContainsString('No grade data is available for this course.', $html);
        $this->assertSame(1, substr_count($html, $notice));
        $this->assertStringNotContainsString('Total not available', $html);

        // The notice belongs to the empty course, which is rendered after the graded one.
        $this->assertGreaterThan(strpos($html, 'Curso de diseño total'), strpos($html, $notice));
    }

    /**
     * MDL-UNIT-024: No on-screen grade report markup leaks into the document.
     *
     * @return void
     */
    public function test_build_html_has_no_screen_report_artifacts(): void {
        $feedback = "## Resumen\n\n![logo](http://example.com/theme/logo.png)\n\nBuen trabajo.";
        $html = pdf_exporter::build_html('Alpha Alpine', $feedback, self::create_payload());

        $this->assertStringNotContainsString('user-grade', $html);
        $this->assertStringNotContainsString('gradereport', $html);
        $this->assertStringNotContainsString('<img', $html);
    }

    /**
     * MDL-UNIT-024: The feedback markdown is converted to HTML.
     *
     * @return void
     */
    public function test_build_html_converts_feedback_markdown(): void {
        $html = pdf_exporter::build_html('Alpha Alpine', "## Resumen\n\nBuen trabajo.", self::create_payload());

        $this->assertStringNotContainsString('## Resumen', $html);
        $this->assertMatchesRegularExpression('/<h2[^>]*>\s*Resumen\s*<\/h2>/', $html);
        $this->assertStringContainsString('Buen trabajo.', $html);
    }

    /**
     * MDL-UNIT-024: A payload without courses shows the no courses notice.
     *
     * @return void
     */
    public function test_build_html_without_courses_shows_notice(): void {
        $payload = ['userid' => '2', 'student_id' => '42', 'student_name' => 'Alpha Alpine', 'courses' => []];
        $html = pdf_exporter::build_html('Alpha Alpine', 'Great progress.', $payload);

        $this->assertStringContainsString(get_string('nocoursesavailable', 'report_lifestory'), $html);
        $this->assertStringNotContainsString('<table>', $html);
    }

    /**
     * MDL-UNIT-025: The filename combines the report prefix, the student name
     * with spaces replaced by underscores and the date of the given time.
     *
     * @return void
     */
    public function test_build_filename_combines_prefix_name_and_date(): void {
        $time = 1767225600; // 2026-01-01 00:00:00 UTC.
        // userdate() strips the leading zero of the day by default (fixday),
        // so the date part is seven or eight digits depending on the day.
        $date = userdate($time, '%Y%m%d');

        $this->assertMatchesRegularExpression('/^\d{7,8}$/', $date);
        $this->assertSame('lifestory_Alpha_Alpine_' . $date . '.pdf', pdf_exporter::build_filename('Alpha Alpine', $time));
        $this->assertSame('lifestory_Alpha_Alpine_' . $date . '.pdf', pdf_exporter::build_filename('  Alpha   Alpine  ', $time));
        $this->assertSame('lifestory_Alpha_' . $date . '.pdf', pdf_exporter::build_filename('Alpha', $time));

        // The date part follows the supplied time, not the current one.
        $othertime = $time + 10 * DAYSECS;
        $this->assertSame(
            'lifestory_Alpha_' . userdate($othertime, '%Y%m%d') . '.pdf',
            pdf_exporter::build_filename('Alpha', $othertime)
        );
    }

    /**
     * MDL-UNIT-025: Accents and special characters are dropped from the
     * filename so it stays safe for any file system.
     *
     * @return void
     */
    public function test_build_filename_drops_accents_and_special_characters(): void {
        $time = 1767225600;
        $date = userdate($time, '%Y%m%d');

        $this->assertSame('lifestory_Mara_Jos_Prez_' . $date . '.pdf', pdf_exporter::build_filename('María José Pérez', $time));
        $this->assertSame('lifestory_OConnorSmith_' . $date . '.pdf', pdf_exporter::build_filename("O'Connor/Smith", $time));
        $this->assertSame('lifestory_Ren_Mller-Lde_' . $date . '.pdf', pdf_exporter::build_filename('René Müller-Lüde', $time));

        $filename = pdf_exporter::build_filename('Ñandú "Über" <script>?*|', $time);
        $this->assertMatchesRegularExpression('/^lifestory_[A-Za-z0-9_\-]+_\d{7,8}\.pdf$/', $filename);
        $this->assertDoesNotMatchRegularExpression('/[\s\/\\\\:*?"<>|]/', $filename);
    }

    /**
     * MDL-UNIT-025: A name that becomes empty after cleaning falls back to a
     * generic token so the file is still identifiable.
     *
     * @return void
     */
    public function test_build_filename_uses_fallback_when_name_empties(): void {
        $time = 1767225600;
        $date = userdate($time, '%Y%m%d');

        $this->assertSame('lifestory_student_' . $date . '.pdf', pdf_exporter::build_filename('', $time));
        $this->assertSame('lifestory_student_' . $date . '.pdf', pdf_exporter::build_filename('   ', $time));
        $this->assertSame('lifestory_student_' . $date . '.pdf', pdf_exporter::build_filename('ñ', $time));
        $this->assertSame('lifestory_student_' . $date . '.pdf', pdf_exporter::build_filename('Оценка Успеваемости', $time));
        $this->assertSame('lifestory_student_' . $date . '.pdf', pdf_exporter::build_filename('___---', $time));
    }
}

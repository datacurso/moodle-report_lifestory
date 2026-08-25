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
 * markdown conversion of the AI feedback and the absence of on-screen grade
 * report artifacts.
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
     * Test that the document contains the course structure, values and totals.
     *
     * @return void
     */
    public function test_build_html_renders_grades_payload(): void {
        $html = pdf_exporter::build_html('Alpha Alpine', 'Great progress.', self::create_payload());

        $this->assertStringContainsString('Alpha Alpine', $html);
        $this->assertStringContainsString('Curso de diseño', $html);
        $this->assertStringContainsString('Unidad uno', $html);
        $this->assertStringContainsString('Tarea completa', $html);
        $this->assertStringContainsString('<td>80.00</td>', $html);
        $this->assertStringContainsString('<td>0-100.00</td>', $html);
        $this->assertStringContainsString('<td>50.00</td>', $html);
        $this->assertStringContainsString('<td>40.00</td>', $html);
        $this->assertStringContainsString('Excelente trabajo, ñandú über — très bien', $html);
        $this->assertStringContainsString('Unidad uno total', $html);
        $this->assertStringContainsString('Curso de diseño total', $html);
        $this->assertStringContainsString('<td style="font-weight: bold;">Unidad uno total</td>', $html);
        $this->assertStringContainsString(get_string('calculatedweight', 'report_lifestory'), $html);
        $this->assertStringContainsString(get_string('contributiontototal', 'report_lifestory'), $html);
    }

    /**
     * Test that missing numeric values are rendered as a dash.
     *
     * @return void
     */
    public function test_build_html_renders_null_values_as_dash(): void {
        $html = pdf_exporter::build_html('Alpha Alpine', 'Great progress.', self::create_payload());

        $this->assertStringContainsString('Tarea pendiente', $html);
        $this->assertStringContainsString('<td>-</td>', $html);
    }

    /**
     * Test that a course without grade data shows its heading and the empty notice.
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
     * Test that no on-screen grade report markup leaks into the document.
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
     * Test that the feedback markdown is converted to HTML.
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
     * Test that a payload without courses shows the no courses notice.
     *
     * @return void
     */
    public function test_build_html_without_courses_shows_notice(): void {
        $payload = ['userid' => '2', 'student_id' => '42', 'student_name' => 'Alpha Alpine', 'courses' => []];
        $html = pdf_exporter::build_html('Alpha Alpine', 'Great progress.', $payload);

        $this->assertStringContainsString(get_string('nocoursesavailable', 'report_lifestory'), $html);
        $this->assertStringNotContainsString('<table>', $html);
    }
}

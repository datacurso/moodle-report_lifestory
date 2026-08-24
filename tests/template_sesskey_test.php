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
 * Tests for the session key parameter in the report_lifestory action links.
 *
 * @package   report_lifestory
 * @category  test
 * @copyright 2026 Datacurso
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_lifestory;

/**
 * Unit tests ensuring the history_student template includes the session key
 * in the CSV export and AI feedback action links.
 *
 * These tests guard the CSRF protection of the report actions: index.php
 * calls require_sesskey() for the 'csv' and 'feedback' actions, so the links
 * rendered by the template must carry a valid sesskey parameter or every
 * legitimate click would be rejected.
 *
 * The subject under test is the Mustache template
 * report_lifestory/history_student, not a PHP class, hence @coversNothing.
 *
 * @package   report_lifestory
 * @category  test
 * @coversNothing
 */
final class template_sesskey_test extends \advanced_testcase {

    /**
     * Renders the history_student template with a context mirroring index.php.
     *
     * @param array $overrides Values overriding the default template context.
     * @return string The rendered HTML.
     */
    private function render_template(array $overrides = []): string {
        global $OUTPUT;

        $templatecontext = array_merge([
            'baseurl' => '/report/lifestory/index.php',
            'userid' => 42,
            'courseid' => 0,
            'searchvalue' => '',
            'searchresults' => [],
            'selecteduser' => [
                'id' => 42,
                'fullname' => 'Test Student',
                'email' => 'student@example.com',
            ],
            'hasuser' => true,
            'courses' => [],
            'feedback' => null,
            'feedbackraw' => '',
            'showfeedback' => false,
            'canexportpdf' => false,
            'headerlogo' => null,
            'sesskey' => 'TESTSESSKEY123',
            'cangeneratefeedback' => true,
            'alttext' => 'Logo',
        ], $overrides);

        return $OUTPUT->render_from_template('report_lifestory/history_student', $templatecontext);
    }

    /**
     * Ensures the AI feedback and CSV export links include the session key.
     */
    public function test_action_links_include_sesskey_parameter(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $html = $this->render_template();

        // Extract each action anchor tag by its id and inspect its attributes.
        $this->assertSame(1, preg_match('/<a\b[^>]*id="btn-feedback-ai"[^>]*>/', $html, $feedbackmatches));
        $this->assertStringContainsString('action=feedback', $feedbackmatches[0]);
        $this->assertStringContainsString('sesskey=TESTSESSKEY123', $feedbackmatches[0]);
        $this->assertStringContainsString('userid=42', $feedbackmatches[0]);

        $this->assertSame(1, preg_match('/<a\b[^>]*id="btn-csv-export"[^>]*>/', $html, $csvmatches));
        $this->assertStringContainsString('action=csv', $csvmatches[0]);
        $this->assertStringContainsString('sesskey=TESTSESSKEY123', $csvmatches[0]);
        $this->assertStringContainsString('userid=42', $csvmatches[0]);

        // The sesskey must directly follow the action parameter in each URL.
        // Literal ampersands in the template source are not escaped by
        // Mustache (only interpolated values are), but accept both forms.
        $this->assertSame(1, preg_match('/action=feedback&(amp;)?sesskey=TESTSESSKEY123/', $html));
        $this->assertSame(1, preg_match('/action=csv&(amp;)?sesskey=TESTSESSKEY123/', $html));
    }

    /**
     * Ensures the feedback link is hidden without the capability while the
     * CSV export link still carries the session key.
     */
    public function test_feedback_link_hidden_without_capability_and_csv_keeps_sesskey(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $html = $this->render_template(['cangeneratefeedback' => false]);

        $this->assertStringNotContainsString('btn-feedback-ai', $html);
        $this->assertStringNotContainsString('action=feedback', $html);

        $this->assertSame(1, preg_match('/<a\b[^>]*id="btn-csv-export"[^>]*>/', $html, $csvmatches));
        $this->assertStringContainsString('sesskey=TESTSESSKEY123', $csvmatches[0]);
    }
}

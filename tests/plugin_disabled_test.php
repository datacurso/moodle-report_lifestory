<?php
// This file is part of Moodle - http://moodle.org/
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

namespace report_lifestory;

use report_lifestory\external\search_students;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/adminlib.php');

/**
 * Entry points of report_lifestory when the plugin is switched off in the Datacurso AI provider.
 *
 * Disabled: the report entries under Site administration > Reports and the course navigation
 * entry are hidden, and the report page and the student search web service refuse with plugindisabled.
 * Enabled (no state saved in the provider): every entry point behaves as before.
 *
 * @package    report_lifestory
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::report_lifestory_extend_navigation_course
 * @covers     \report_lifestory\external\search_students
 */
final class plugin_disabled_test extends \advanced_testcase {
    /**
     * Switch the plugin off in the provider.
     *
     * @return void
     */
    private function disable_plugin(): void {
        set_config('plugin_enabled_report_lifestory', '0', 'aiprovider_datacurso');
    }

    /**
     * Enabled: the student search answers an admin as before.
     *
     * @return void
     */
    public function test_search_students_answers_when_enabled(): void {
        $this->require_ai_provider();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->getDataGenerator()->create_user(['firstname' => 'Lifestorysearch']);

        $result = search_students::execute('Lifestorysearch');

        $this->assertIsArray($result);
    }

    /**
     * Disabled: the student search refuses an admin with the provider's message linking to the plugins list.
     *
     * @return void
     */
    public function test_search_students_refuses_when_disabled(): void {
        $this->require_ai_provider();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->disable_plugin();

        try {
            search_students::execute('Lifestorysearch');
            $this->fail('The student search must refuse while the plugin is disabled.');
        } catch (\moodle_exception $e) {
            $this->assertSame('plugindisabled_admin', $e->errorcode);
            $this->assertSame('aiprovider_datacurso', $e->module);
            $this->assertStringContainsString('/ai/provider/datacurso/admin/report_sections.php', $e->link);
        }
    }

    /**
     * The report category and page under Site administration > Reports follow the state.
     *
     * @return void
     */
    public function test_admin_entries_follow_the_state(): void {
        $this->require_ai_provider();
        $this->resetAfterTest();
        $this->setAdminUser();

        $root = admin_get_root(true, false);
        $this->assertInstanceOf(\admin_category::class, $root->locate('report_lifestory_cat'));
        $this->assertInstanceOf(\admin_externalpage::class, $root->locate('report_lifestory'));

        $this->disable_plugin();
        $root = admin_get_root(true, false);
        $this->assertNull($root->locate('report_lifestory_cat'));
        $this->assertNull($root->locate('report_lifestory'));
    }

    /**
     * Build the course navigation as admin and return the URLs of the nodes the plugin added.
     *
     * @return string[]
     */
    private function navigation_urls(): array {
        global $CFG;
        require_once($CFG->dirroot . '/report/lifestory/lib.php');

        $course = $this->getDataGenerator()->create_course();
        $this->setAdminUser();
        $navigation = new \navigation_node('Reports');
        report_lifestory_extend_navigation_course($navigation, $course, \context_course::instance($course->id));

        $urls = [];
        foreach ($navigation->children as $child) {
            $urls[] = $child->action instanceof \moodle_url ? $child->action->out_omit_querystring() : '';
        }
        return $urls;
    }

    /**
     * The course navigation entry is added when enabled and hidden when disabled.
     *
     * @return void
     */
    public function test_course_navigation_entry_follows_the_state(): void {
        $this->require_ai_provider();
        $this->resetAfterTest();

        $reporturl = (new \moodle_url('/report/lifestory/index.php'))->out_omit_querystring();
        $this->assertContains($reporturl, $this->navigation_urls());

        $this->disable_plugin();
        $this->assertSame([], $this->navigation_urls());
    }

    /**
     * The report page refuses through the provider right after the login check.
     *
     * Page scripts cannot run inside PHPUnit, so the contract is checked on the script itself;
     * the refusal itself is the provider's require_enabled(), exercised by the web service tests above.
     *
     * @return void
     */
    public function test_report_page_requires_the_plugin_enabled(): void {
        global $CFG;
        $source = file_get_contents($CFG->dirroot . '/report/lifestory/index.php');

        $login = strpos($source, 'require_login();');
        $gate = strpos($source, "plugin_settings::require_enabled('report_lifestory');");
        $this->assertNotFalse($login);
        $this->assertNotFalse($gate);
        $this->assertGreaterThan($login, $gate);
    }

    /**
     * Skip when the Datacurso AI provider is absent: the code under test asks it whether this plugin is enabled.
     *
     * @return void
     */
    private function require_ai_provider(): void {
        // The CI pipeline does not install declared dependencies; without the Datacurso AI provider this test cannot run.
        if (!class_exists(\aiprovider_datacurso\local\plugin_settings::class)) {
            $this->markTestSkipped('aiprovider_datacurso is not installed.');
        }
    }
}

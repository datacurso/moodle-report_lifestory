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

use aiprovider_datacurso\local\plugin_settings;
use report_lifestory\external\search_students;
use report_lifestory\local\plugin_state;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/adminlib.php');

/**
 * Entry points of report_lifestory when the report is switched off in the Datacurso AI provider.
 *
 * Disabled: the report entries under Site administration > Reports and the course navigation
 * entry are hidden, and the report page and the student search web service refuse with plugindisabled.
 * Enabled (no state saved in the provider): every entry point behaves as before.
 * On Moodle Workplace the state is per tenant, and the tenant of the user always decides, never the tenant
 * that owns the course.
 *
 * @package    report_lifestory
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::report_lifestory_extend_navigation_course
 * @covers     \report_lifestory\external\search_students
 * @covers     \report_lifestory\local\plugin_state
 */
final class plugin_disabled_test extends \advanced_testcase {
    /**
     * Skip the whole class when the Datacurso AI provider is absent.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        // The CI pipeline does not install declared dependencies; without the Datacurso AI provider these tests cannot run.
        if (!class_exists(plugin_settings::class)) {
            $this->markTestSkipped('aiprovider_datacurso is not installed.');
        }
    }

    /**
     * Skip when the site has no Moodle Workplace tenancy.
     *
     * @return void
     */
    private function require_tenancy(): void {
        if (!class_exists('\tool_tenant\tenancy')) {
            $this->markTestSkipped('tool_tenant is not installed.');
        }
    }

    /**
     * Create a tenant that owns a new course category.
     *
     * @return array Tenant id and category id.
     */
    private function create_tenant_with_category(): array {
        $category = $this->getDataGenerator()->create_category();
        $tenant = $this->getDataGenerator()->get_plugin_generator('tool_tenant')
            ->create_tenant((object) ['categoryid' => $category->id]);
        return [(int) $tenant->id, (int) $category->id];
    }

    /**
     * Enabled: the student search answers an admin as before.
     *
     * @return void
     */
    public function test_search_students_answers_when_enabled(): void {
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
        $this->resetAfterTest();
        $this->setAdminUser();
        plugin_settings::set_enabled('report_lifestory', false, plugin_settings::resolve_tenant());

        try {
            search_students::execute('Lifestorysearch');
            $this->fail('The student search must refuse while the report is disabled.');
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
        $this->resetAfterTest();
        $this->setAdminUser();

        $root = admin_get_root(true, false);
        $this->assertInstanceOf(\admin_category::class, $root->locate('report_lifestory_cat'));
        $this->assertInstanceOf(\admin_externalpage::class, $root->locate('report_lifestory'));

        plugin_settings::set_enabled('report_lifestory', false, plugin_settings::resolve_tenant());
        $root = admin_get_root(true, false);
        $this->assertNull($root->locate('report_lifestory_cat'));
        $this->assertNull($root->locate('report_lifestory'));
    }

    /**
     * Create a user of a tenant who may view the report everywhere.
     *
     * @param int $tenantid Tenant of the user.
     * @return \stdClass
     */
    private function create_tenant_viewer(int $tenantid): \stdClass {
        global $DB;
        $viewer = $this->getDataGenerator()->get_plugin_generator('tool_tenant')->create_user(['tenantid' => $tenantid]);
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'lifestoryviewer'])
            ?: create_role('Lifestory viewer', 'lifestoryviewer', '');
        assign_capability('report/lifestory:view', CAP_ALLOW, $roleid, \context_system::instance()->id, true);
        role_assign($roleid, $viewer->id, \context_system::instance()->id);
        return $viewer;
    }

    /**
     * Build the course navigation as a user and return the URLs of the nodes the plugin added.
     *
     * @param \stdClass $course Course to build the navigation for.
     * @param \stdClass|null $user User to build it for; null builds it as admin.
     * @return string[]
     */
    private function navigation_urls(\stdClass $course, ?\stdClass $user = null): array {
        global $CFG;
        require_once($CFG->dirroot . '/report/lifestory/lib.php');

        if ($user) {
            $this->setUser($user);
        } else {
            $this->setAdminUser();
        }
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
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();

        $reporturl = (new \moodle_url('/report/lifestory/index.php'))->out_omit_querystring();
        $this->assertContains($reporturl, $this->navigation_urls($course));

        $this->setAdminUser();
        // The tenant of the viewer decides.
        plugin_settings::set_enabled('report_lifestory', false, plugin_settings::resolve_tenant());
        $this->assertSame([], $this->navigation_urls($course));
    }

    /**
     * The report page refuses through the provider right after the login check, for the tenant of the viewer.
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
        $gate = strpos($source, 'plugin_state::require_enabled();');
        $this->assertNotFalse($login);
        $this->assertNotFalse($gate);
        $this->assertGreaterThan($login, $gate);
    }

    /**
     * The tenant of the user decides, not the tenant of the course.
     *
     * A user of disabled tenant A is refused and sees no course entry in a course of enabled tenant B.
     *
     * @return void
     */
    public function test_user_of_disabled_tenant_is_refused_in_course_of_enabled_tenant(): void {
        $this->require_tenancy();
        $this->resetAfterTest();

        [$tenanta] = $this->create_tenant_with_category();
        [, $categoryb] = $this->create_tenant_with_category();
        $courseb = $this->getDataGenerator()->create_course(['category' => $categoryb]);
        $viewera = $this->create_tenant_viewer($tenanta);

        plugin_settings::set_enabled('report_lifestory', false, $tenanta);

        $this->assertSame([], $this->navigation_urls($courseb, $viewera));
        $this->assertFalse(plugin_state::is_enabled());
        try {
            plugin_state::require_enabled();
            $this->fail('A user of a disabled tenant must be refused in a course of an enabled tenant.');
        } catch (\moodle_exception $e) {
            $this->assertSame('plugindisabled', $e->errorcode);
            $this->assertSame('aiprovider_datacurso', $e->module);
        }
    }

    /**
     * A user of enabled tenant B keeps the report in a course of disabled tenant A.
     *
     * @return void
     */
    public function test_user_of_enabled_tenant_is_allowed_in_course_of_disabled_tenant(): void {
        $this->require_tenancy();
        $this->resetAfterTest();

        [$tenanta, $categorya] = $this->create_tenant_with_category();
        [$tenantb] = $this->create_tenant_with_category();
        $coursea = $this->getDataGenerator()->create_course(['category' => $categorya]);
        $viewerb = $this->create_tenant_viewer($tenantb);

        plugin_settings::set_enabled('report_lifestory', false, $tenanta);

        $reporturl = (new \moodle_url('/report/lifestory/index.php'))->out_omit_querystring();
        $this->assertContains($reporturl, $this->navigation_urls($coursea, $viewerb));
        $this->assertTrue(plugin_state::is_enabled());
        plugin_state::require_enabled();
    }

    /**
     * The student search follows the tenant of the caller: off in that tenant refuses, off elsewhere does not.
     *
     * @return void
     */
    public function test_search_students_follows_the_tenant_of_the_caller(): void {
        $this->require_tenancy();
        $this->resetAfterTest();

        [$tenanta] = $this->create_tenant_with_category();
        [$tenantb] = $this->create_tenant_with_category();
        $this->setUser($this->create_tenant_viewer($tenanta));

        plugin_settings::set_enabled('report_lifestory', false, $tenantb);
        $this->assertIsArray(search_students::execute('Lifestorysearch'));

        plugin_settings::set_enabled('report_lifestory', false, $tenanta);
        try {
            search_students::execute('Lifestorysearch');
            $this->fail('The student search must refuse while the report is disabled for the tenant of the caller.');
        } catch (\moodle_exception $e) {
            $this->assertSame('plugindisabled', $e->errorcode);
            $this->assertSame('aiprovider_datacurso', $e->module);
        }
    }

    /**
     * A value saved for the tenant of the user wins over the site default.
     *
     * @return void
     */
    public function test_tenant_value_wins_over_the_site_default(): void {
        $this->require_tenancy();
        $this->resetAfterTest();

        [$tenanta] = $this->create_tenant_with_category();
        [$tenantb] = $this->create_tenant_with_category();
        $viewera = $this->create_tenant_viewer($tenanta);
        $viewerb = $this->create_tenant_viewer($tenantb);

        plugin_settings::set_enabled('report_lifestory', false);
        plugin_settings::set_enabled('report_lifestory', true, $tenanta);

        $this->setUser($viewera);
        $this->assertTrue(plugin_state::is_enabled());
        $this->setUser($viewerb);
        $this->assertFalse(plugin_state::is_enabled());
    }
}

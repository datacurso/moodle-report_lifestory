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
 * Tests for report_lifestory settings visibility.
 *
 * @package   report_lifestory
 * @category  test
 * @copyright 2026 Datacurso
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_lifestory;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/adminlib.php');

/**
 * Unit tests for report_lifestory admin settings page registration.
 *
 * @package   report_lifestory
 * @category  test
 */
final class settings_test extends \advanced_testcase {

    /**
     * Ensures users with report capability can see the report link without site config capability.
     *
     * @coversNothing
     */
    public function test_report_link_visible_without_site_config_capability(): void {
        $this->resetAfterTest(true);

        $context = \context_system::instance();
        $user = $this->getDataGenerator()->create_user();

        $roleid = create_role('Lifestory viewer', 'lifestoryviewer', 'Role for lifestory report access tests.');
        assign_capability('report/lifestory:view', CAP_ALLOW, $roleid, $context->id);
        role_assign($roleid, $user->id, $context->id);

        $this->setUser($user);

        $this->assertFalse(has_capability('moodle/site:config', $context));
        $this->assertTrue(has_capability('report/lifestory:view', $context));

        $adminroot = admin_get_root(true);
        $category = $adminroot->locate('report_lifestory_cat');
        $page = $adminroot->locate('report_lifestory');

        $this->assertInstanceOf('\admin_category', $category);
        $this->assertInstanceOf('\admin_externalpage', $page);
    }
}

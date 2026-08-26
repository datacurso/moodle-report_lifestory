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
 * Tests for the navigation callbacks of report_lifestory.
 *
 * @package   report_lifestory
 * @category  test
 * @copyright 2026 Datacurso
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_lifestory;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/navigationlib.php');
require_once($CFG->dirroot . '/report/lifestory/lib.php');

/**
 * Unit tests for the course navigation extension of the report and the
 * absence of a user profile navigation callback.
 *
 * @package   report_lifestory
 * @category  test
 * @coversNothing
 */
final class lib_test extends \advanced_testcase {
    /**
     * Creates a course and a user holding the report view capability in it.
     *
     * @return array Array with course, context and viewer.
     */
    private function create_course_and_viewer(): array {
        $generator = $this->getDataGenerator();

        $course = $generator->create_course();
        $context = \context_course::instance($course->id);

        $viewer = $generator->create_user();
        $roleid = create_role('Course report viewer', 'coursereportviewer', '');
        assign_capability('report/lifestory:view', CAP_ALLOW, $roleid, $context->id);
        role_assign($roleid, $viewer->id, $context->id);

        return [$course, $context, $viewer];
    }

    /**
     * MDL-INT-003: A user with the view capability in the course gets a
     * navigation entry that opens the report filtered by that course.
     */
    public function test_course_navigation_adds_filtered_report_link_for_authorised_user(): void {
        $this->resetAfterTest();

        [$course, $context, $viewer] = $this->create_course_and_viewer();
        $this->setUser($viewer);

        $navigation = new \navigation_node('Reports');
        report_lifestory_extend_navigation_course($navigation, $course, $context);

        $this->assertSame(1, $navigation->children->count());

        $node = null;
        foreach ($navigation->children as $child) {
            $node = $child;
        }

        $this->assertInstanceOf(\navigation_node::class, $node);
        $this->assertSame(get_string('lifestory', 'report_lifestory'), $node->text);
        $this->assertSame(\navigation_node::TYPE_SETTING, $node->type);
        $this->assertInstanceOf(\moodle_url::class, $node->action);
        $this->assertStringContainsString('/report/lifestory/index.php', $node->action->out(false));
        $this->assertEquals($course->id, $node->action->get_param('id'));
    }

    /**
     * MDL-INT-003: A user without the view capability in the course gets no
     * navigation entry.
     */
    public function test_course_navigation_adds_nothing_without_capability(): void {
        $this->resetAfterTest();

        [$course, $context] = $this->create_course_and_viewer();

        $generator = $this->getDataGenerator();
        $teacher = $generator->create_user();
        $generator->enrol_user($teacher->id, $course->id, 'teacher');
        $this->setUser($teacher);

        $this->assertFalse(has_capability('report/lifestory:view', $context));

        $navigation = new \navigation_node('Reports');
        report_lifestory_extend_navigation_course($navigation, $course, $context);

        $this->assertSame(0, $navigation->children->count());
    }

    /**
     * MDL-INT-002: The report exposes no user profile navigation callback, so
     * it never appears in user profiles.
     */
    public function test_no_user_profile_navigation_callback_exists(): void {
        $this->assertTrue(function_exists('report_lifestory_extend_navigation_course'));
        $this->assertFalse(function_exists('report_lifestory_extend_navigation_user'));
        $this->assertFalse(function_exists('report_lifestory_myprofile_navigation'));
    }
}

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
 * Public API of the lifestory report.
 *
 * @package     report_lifestory
 * @copyright   2026 Datacurso
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Extends the course navigation with a link to the life story report.
 *
 * The link opens the report filtered by the current course, so the viewer
 * only sees grades belonging to that course.
 *
 * @param navigation_node $navigation The navigation node to extend.
 * @param stdClass $course The course object for the report.
 * @param context $context The context of the course.
 * @return void
 */
function report_lifestory_extend_navigation_course(navigation_node $navigation, stdClass $course, context $context): void {
    if (!has_capability('report/lifestory:view', $context)) {
        return;
    }

    $url = new moodle_url('/report/lifestory/index.php', ['id' => $course->id]);
    $navigation->add(
        get_string('lifestory', 'report_lifestory'),
        $url,
        navigation_node::TYPE_SETTING,
        null,
        null,
        new pix_icon('i/report', '')
    );
}

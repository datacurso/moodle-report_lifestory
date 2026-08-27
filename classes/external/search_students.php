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

namespace report_lifestory\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use report_lifestory\local\student_search;

/**
 * External function for searching students.
 *
 * @package     report_lifestory
 * @copyright   2025 Datacurso
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class search_students extends external_api {
    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'query' => new external_value(PARAM_TEXT, 'Search query'),
        ]);
    }

    /**
     * Search for students.
     *
     * @param string $query Search query
     * @return array
     */
    public static function execute($query) {
        $params = self::validate_parameters(self::execute_parameters(), [
            'query' => $query,
        ]);

        require_login();

        $context = \context_system::instance();
        self::validate_context($context);

        require_capability('report/lifestory:view', $context);

        $searchdata = student_search::search($params['query']);

        $results = [];
        foreach ($searchdata['students'] as $student) {
            $usercontext = \context_user::instance($student['id']);
            $profileimageurl = \moodle_url::make_pluginfile_url(
                $usercontext->id,
                'user',
                'icon',
                null,
                '/',
                'f1'
            )->out(false);

            $results[] = [
                'id' => $student['id'],
                'fullname' => $student['fullname'],
                'email' => $student['email'],
                'profileimageurl' => $profileimageurl,
            ];
        }

        return ['students' => $results, 'hasmore' => $searchdata['hasmore']];
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'students' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'User ID'),
                    'fullname' => new external_value(PARAM_TEXT, 'Full name'),
                    'email' => new external_value(PARAM_EMAIL, 'Email address'),
                    'profileimageurl' => new external_value(PARAM_URL, 'Profile image URL'),
                ])
            ),
            'hasmore' => new external_value(PARAM_BOOL, 'Whether more students match beyond the returned results'),
        ]);
    }
}

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
 * Privacy subsystem implementation for report_lifestory.
 *
 * @package    report_lifestory
 * @copyright  2025 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_lifestory\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;

/**
 * Privacy provider for report_lifestory.
 *
 * @package    report_lifestory
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe the types of personal data transmitted by this plugin.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection The updated collection of metadata items.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_external_location_link(
            'ai_provider',
            [
                'site_id' => 'privacy:metadata:ai_provider:siteid',
                'site_url' => 'privacy:metadata:ai_provider:siteurl',
                'userid' => 'privacy:metadata:ai_provider:userid',
                'student_id' => 'privacy:metadata:ai_provider:studentid',
                'student_name' => 'privacy:metadata:ai_provider:studentname',
                'courses' => 'privacy:metadata:ai_provider:courses',
                'timezone' => 'privacy:metadata:ai_provider:timezone',
                'lang' => 'privacy:metadata:ai_provider:lang',
            ],
            'privacy:metadata:ai_provider'
        );

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for a given user.
     *
     * @param int $userid The ID of the user whose data contexts should be retrieved.
     * @return contextlist The list of contexts containing user information.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        return new contextlist();
    }

    /**
     * Delete user data for the specified user in the given context list.
     *
     * @param approved_contextlist $contextlist The approved contexts for the user.
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        // No local data to delete.
    }

    /**
     * Delete all user data for all users in the specified context.
     *
     * @param \context $context The context for which all user data should be deleted.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        // No local data to delete.
    }

    /**
     * Export user data for the given approved context list.
     *
     * @param approved_contextlist $contextlist The approved contexts to export data from.
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        // No local data to export.
    }
}

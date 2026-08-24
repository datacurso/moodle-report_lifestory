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
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for report_lifestory.
 *
 * The plugin sends pseudonymised data to an external AI service and stores
 * the latest AI-generated feedback per student locally at the system context.
 *
 * @package    report_lifestory
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe the types of personal data stored and transmitted by this plugin.
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

        $collection->add_database_table(
            'report_lifestory_feedback',
            [
                'studentid' => 'privacy:metadata:report_lifestory_feedback:studentid',
                'courseid' => 'privacy:metadata:report_lifestory_feedback:courseid',
                'feedback' => 'privacy:metadata:report_lifestory_feedback:feedback',
                'usermodified' => 'privacy:metadata:report_lifestory_feedback:usermodified',
                'timecreated' => 'privacy:metadata:report_lifestory_feedback:timecreated',
                'timemodified' => 'privacy:metadata:report_lifestory_feedback:timemodified',
            ],
            'privacy:metadata:report_lifestory_feedback'
        );

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for a given user.
     *
     * Stored feedback lives at the system context, so the system context is
     * returned when the user appears as the student the feedback is about or
     * as the user who generated it.
     *
     * @param int $userid The ID of the user whose data contexts should be retrieved.
     * @return contextlist The list of contexts containing user information.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;

        $contextlist = new contextlist();

        $select = 'studentid = :studentid OR usermodified = :usermodified';
        $params = ['studentid' => $userid, 'usermodified' => $userid];

        if ($DB->record_exists_select('report_lifestory_feedback', $select, $params)) {
            $contextlist->add_system_context();
        }

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist containing users who have data in this context.
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();

        if (!$context instanceof \context_system) {
            return;
        }

        $sql = 'SELECT studentid FROM {report_lifestory_feedback}';
        $userlist->add_from_sql('studentid', $sql, []);

        $sql = 'SELECT usermodified FROM {report_lifestory_feedback}';
        $userlist->add_from_sql('usermodified', $sql, []);
    }

    /**
     * Export user data for the given approved context list.
     *
     * Every stored feedback row where the user is the student or the
     * generator is exported under the system context.
     *
     * @param approved_contextlist $contextlist The approved contexts to export data from.
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $hassystemcontext = false;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof \context_system) {
                $hassystemcontext = true;
                break;
            }
        }

        if (!$hassystemcontext) {
            return;
        }

        $userid = $contextlist->get_user()->id;

        $select = 'studentid = :studentid OR usermodified = :usermodified';
        $params = ['studentid' => $userid, 'usermodified' => $userid];
        $records = $DB->get_records_select('report_lifestory_feedback', $select, $params, 'id ASC');

        if (!$records) {
            return;
        }

        $data = [];
        foreach ($records as $record) {
            $data[] = (object) [
                'studentid' => transform::user($record->studentid),
                'courseid' => $record->courseid,
                'feedback' => $record->feedback,
                'usermodified' => transform::user($record->usermodified),
                'timecreated' => transform::datetime($record->timecreated),
                'timemodified' => transform::datetime($record->timemodified),
            ];
        }

        writer::with_context(\context_system::instance())->export_data(
            [get_string('lifestory', 'report_lifestory')],
            (object) ['feedback' => $data]
        );
    }

    /**
     * Delete all user data for all users in the specified context.
     *
     * @param \context $context The context for which all user data should be deleted.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if (!$context instanceof \context_system) {
            return;
        }

        $DB->delete_records('report_lifestory_feedback');
    }

    /**
     * Delete user data for the specified user in the given context list.
     *
     * Rows where the user is the student the feedback is about are removed.
     *
     * @param approved_contextlist $contextlist The approved contexts for the user.
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof \context_system) {
                $DB->delete_records('report_lifestory_feedback', ['studentid' => $contextlist->get_user()->id]);
                return;
            }
        }
    }

    /**
     * Delete data for multiple users within a single context.
     *
     * Rows where any of the approved users is the student the feedback is
     * about are removed.
     *
     * @param approved_userlist $userlist The approved context and users to delete data for.
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();

        if (!$context instanceof \context_system) {
            return;
        }

        $userids = $userlist->get_userids();

        if (empty($userids)) {
            return;
        }

        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $DB->delete_records_select('report_lifestory_feedback', "studentid {$insql}", $inparams);
    }
}

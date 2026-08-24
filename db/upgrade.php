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
 * Plugin upgrade steps are defined here.
 *
 * @package     report_lifestory
 * @category    upgrade
 * @copyright   2025 Datacurso
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * Execute report_lifestory upgrade from the given old version.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_report_lifestory_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    // For further information please read {@link https://docs.moodle.org/dev/Upgrade_API}.
    //
    // You will also have to create the db/install.xml file by using the XMLDB Editor.
    // Documentation for the XMLDB Editor can be found at {@link https://docs.moodle.org/dev/XMLDB_editor}.

    if ($oldversion < 2026082400) {
        // Define table report_lifestory_feedback to be created.
        $table = new xmldb_table('report_lifestory_feedback');

        // Adding fields to table report_lifestory_feedback.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('studentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('feedback', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table report_lifestory_feedback.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('studentid', XMLDB_KEY_FOREIGN, ['studentid'], 'user', ['id']);
        $table->add_key('usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);

        // Adding indexes to table report_lifestory_feedback.
        $table->add_index('studentcourse', XMLDB_INDEX_UNIQUE, ['studentid', 'courseid']);

        // Conditionally launch create table for report_lifestory_feedback.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Lifestory savepoint reached.
        upgrade_plugin_savepoint(true, 2026082400, 'report', 'lifestory');
    }

    return true;
}

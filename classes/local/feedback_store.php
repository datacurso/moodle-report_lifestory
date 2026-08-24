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

namespace report_lifestory\local;

/**
 * Server-side store for the AI-generated feedback of a student.
 *
 * The latest feedback generated for a student under a given course filter is
 * persisted in the report_lifestory_feedback table so that later actions,
 * such as the PDF export, use the stored text instead of any value supplied
 * by the browser.
 *
 * @package    report_lifestory
 * @copyright  2026 Datacurso
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class feedback_store {
    /**
     * Saves the AI-generated feedback for a student and course filter.
     *
     * An existing record for the same student and course filter is updated,
     * otherwise a new record is inserted, keeping a single row per pair.
     *
     * @param int $studentid The id of the student the feedback is about.
     * @param int $courseid The course filter used for generation (0 means all courses).
     * @param string $feedback The AI-generated feedback text to store.
     * @return void
     */
    public static function save(int $studentid, int $courseid, string $feedback): void {
        global $DB, $USER;

        $now = time();
        $record = $DB->get_record('report_lifestory_feedback', ['studentid' => $studentid, 'courseid' => $courseid]);

        if ($record) {
            $record->feedback = $feedback;
            $record->usermodified = $USER->id;
            $record->timemodified = $now;
            $DB->update_record('report_lifestory_feedback', $record);
            return;
        }

        $record = (object) [
            'studentid' => $studentid,
            'courseid' => $courseid,
            'feedback' => $feedback,
            'usermodified' => $USER->id,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $DB->insert_record('report_lifestory_feedback', $record);
    }

    /**
     * Returns the stored AI-generated feedback for a student and course filter.
     *
     * @param int $studentid The id of the student the feedback is about.
     * @param int $courseid The course filter used for generation (0 means all courses).
     * @return string|null The stored feedback text, or null when no record exists.
     */
    public static function get(int $studentid, int $courseid): ?string {
        global $DB;

        $feedback = $DB->get_field(
            'report_lifestory_feedback',
            'feedback',
            ['studentid' => $studentid, 'courseid' => $courseid]
        );

        return $feedback === false ? null : $feedback;
    }
}

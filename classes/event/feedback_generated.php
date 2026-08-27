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

namespace report_lifestory\event;

/**
 * Event triggered when a user generates AI feedback for a student.
 *
 * @package    report_lifestory
 * @copyright  2026 Datacurso
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class feedback_generated extends \core\event\base {
    /**
     * Creates the event for a consulted student.
     *
     * @param int $studentid The id of the student the AI feedback was generated for.
     * @param \context $context The context the feedback was generated in.
     * @param int $courseid The course filter used (0 means all courses).
     * @param int $feedbackid The id of the stored feedback record.
     * @return self The initialised event ready to be triggered.
     */
    public static function create_for_student(int $studentid, \context $context, int $courseid, int $feedbackid): self {
        return self::create([
            'relateduserid' => $studentid,
            'context' => $context,
            'objectid' => $feedbackid,
            'other' => ['courseid' => $courseid],
        ]);
    }

    /**
     * Initialises the event data.
     *
     * @return void
     */
    protected function init(): void {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'report_lifestory_feedback';
    }

    /**
     * Returns the localised general event name.
     *
     * @return string The event name.
     */
    public static function get_name(): string {
        return get_string('event:feedbackgenerated', 'report_lifestory');
    }

    /**
     * Returns a non-localised description of what happened.
     *
     * @return string The event description.
     */
    public function get_description(): string {
        return "The user with id '$this->userid' generated AI feedback for the student " .
            "with id '$this->relateduserid'.";
    }

    /**
     * Returns the URL of the report page related to this event.
     *
     * @return \moodle_url The related report URL.
     */
    public function get_url(): \moodle_url {
        $params = ['userid' => $this->relateduserid];
        if ($this->other['courseid']) {
            $params['id'] = $this->other['courseid'];
        }
        return new \moodle_url('/report/lifestory/index.php', $params);
    }

    /**
     * Custom validation ensuring all required event data is present.
     *
     * @return void
     * @throws \coding_exception When a required value is missing.
     */
    protected function validate_data() {
        parent::validate_data();

        if (!isset($this->relateduserid)) {
            throw new \coding_exception('The \'relateduserid\' value must be set.');
        }

        if (!isset($this->other['courseid'])) {
            throw new \coding_exception('The \'courseid\' value must be set in other.');
        }
    }

    /**
     * Declares how the object id maps for backup and restore.
     *
     * @return array The object id mapping declaration.
     */
    public static function get_objectid_mapping() {
        return ['db' => 'report_lifestory_feedback', 'restore' => \core\event\base::NOT_MAPPED];
    }

    /**
     * Declares that the other fields hold no mappable ids for backup and restore.
     *
     * @return bool False because there is nothing to map.
     */
    public static function get_other_mapping() {
        return false;
    }
}

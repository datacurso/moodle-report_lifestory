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
 * Tests for the course backup and restore behaviour with report_lifestory installed.
 *
 * @package   report_lifestory
 * @category  test
 * @copyright 2026 Datacurso
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_lifestory;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

use report_lifestory\local\feedback_store;

/**
 * Ensures the plugin does not interfere with course backup and restore: the
 * stored AI feedback lives at the site level and is neither included in the
 * course backup nor altered by a restore.
 *
 * @package   report_lifestory
 * @category  test
 * @coversNothing
 */
final class backup_restore_test extends \advanced_testcase {
    /**
     * MDL-INT-024: A full backup and restore cycle of a course completes
     * without error and leaves the stored feedback table unchanged.
     */
    public function test_backup_and_restore_leave_stored_feedback_unchanged(): void {
        global $CFG, $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['fullname' => 'Backed up course', 'shortname' => 'BACKUP']);
        $student = $generator->create_user();
        $generator->enrol_user($student->id, $course->id, 'student');
        $item = $generator->create_grade_item(['courseid' => $course->id, 'itemname' => 'Backed up item']);
        $generator->create_grade_grade(['itemid' => $item->id, 'userid' => $student->id, 'grade' => 9]);

        feedback_store::save((int) $student->id, 0, 'Feedback for all courses.');
        feedback_store::save((int) $student->id, (int) $course->id, 'Feedback for the backed up course.');

        $before = array_values($DB->get_records('report_lifestory_feedback', [], 'id ASC'));
        $this->assertCount(2, $before);

        // Backup.
        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $course->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id
        );
        $bc->execute_plan();
        $results = $bc->get_results();
        $file = $results['backup_destination'];
        $this->assertInstanceOf(\stored_file::class, $file);

        $backupid = 'report_lifestory_restore_' . uniqid();
        $filepath = $CFG->dataroot . '/temp/backup/' . $backupid;
        $file->extract_to_pathname(get_file_packer('application/vnd.moodle.backup'), $filepath);
        $bc->destroy();

        // The course backup must not carry any report_lifestory data.
        $this->assertFileExists($filepath . '/moodle_backup.xml');
        $matches = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($filepath)) as $entry) {
            if ($entry->isFile() && stripos($entry->getFilename(), 'lifestory') !== false) {
                $matches[] = $entry->getPathname();
            }
        }
        $this->assertSame([], $matches);
        $this->assertStringNotContainsString('report_lifestory_feedback', file_get_contents($filepath . '/moodle_backup.xml'));

        // Restore into a new course.
        $newcourseid = \restore_dbops::create_new_course('Restored course', 'RESTORED', $course->category);
        $rc = new \restore_controller(
            $backupid,
            $newcourseid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id,
            \backup::TARGET_NEW_COURSE
        );
        $this->assertTrue($rc->execute_precheck());
        // Third-party restore steps may echo progress messages; keep them out of the test output.
        ob_start();
        try {
            $rc->execute_plan();
        } finally {
            ob_end_clean();
        }
        $rc->destroy();

        $this->assertTrue($DB->record_exists('course', ['id' => $newcourseid]));
        $this->assertTrue($DB->record_exists('grade_items', ['courseid' => $newcourseid, 'itemname' => 'Backed up item']));

        // The site level feedback store is untouched: same rows, same content.
        $after = array_values($DB->get_records('report_lifestory_feedback', [], 'id ASC'));
        $this->assertEquals($before, $after);
        $this->assertSame('Feedback for the backed up course.', feedback_store::get((int) $student->id, (int) $course->id));
        $this->assertNull(feedback_store::get((int) $student->id, $newcourseid));
    }
}

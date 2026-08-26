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
 * Tests for the server-side AI feedback store of report_lifestory.
 *
 * @package   report_lifestory
 * @category  test
 * @copyright 2026 Datacurso
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_lifestory\local;

/**
 * Unit tests ensuring the feedback store persists one row per student and
 * course filter and returns the stored text on demand.
 *
 * @package   report_lifestory
 * @category  test
 * @covers    \report_lifestory\local\feedback_store
 */
final class feedback_store_test extends \advanced_testcase {
    /**
     * MDL-INT-017: A saved feedback text can be read back for the same pair.
     */
    public function test_save_and_get_roundtrip(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $student = $this->getDataGenerator()->create_user();

        feedback_store::save($student->id, 0, 'Great progress this term.');

        $this->assertSame('Great progress this term.', feedback_store::get($student->id, 0));
    }

    /**
     * MDL-INT-017: Saving twice for the same pair replaces the text and keeps a single row.
     */
    public function test_save_upserts_and_keeps_a_single_row(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $student = $this->getDataGenerator()->create_user();

        $firstid = feedback_store::save($student->id, 0, 'First feedback.');
        $secondid = feedback_store::save($student->id, 0, 'Updated feedback.');

        $this->assertIsInt($firstid);
        $this->assertSame($firstid, $secondid);
        $this->assertSame('Updated feedback.', feedback_store::get($student->id, 0));
        $this->assertEquals(1, $DB->count_records('report_lifestory_feedback', ['studentid' => $student->id]));
    }

    /**
     * MDL-INT-017: Feedback stored for different course filters does not overlap.
     */
    public function test_feedback_is_separated_per_course_filter(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $student = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();

        feedback_store::save($student->id, 0, 'All courses feedback.');
        feedback_store::save($student->id, $course->id, 'Single course feedback.');

        $this->assertSame('All courses feedback.', feedback_store::get($student->id, 0));
        $this->assertSame('Single course feedback.', feedback_store::get($student->id, $course->id));
    }

    /**
     * MDL-INT-017: Feedback stored for one student never leaks into another student.
     */
    public function test_feedback_is_separated_per_student(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $student1 = $this->getDataGenerator()->create_user();
        $student2 = $this->getDataGenerator()->create_user();

        feedback_store::save($student1->id, 0, 'Feedback for student one.');
        feedback_store::save($student2->id, 0, 'Feedback for student two.');

        $this->assertSame('Feedback for student one.', feedback_store::get($student1->id, 0));
        $this->assertSame('Feedback for student two.', feedback_store::get($student2->id, 0));
    }

    /**
     * MDL-INT-017: get_record returns null when no feedback exists for the pair.
     */
    public function test_get_record_returns_null_when_absent(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $student = $this->getDataGenerator()->create_user();

        $this->assertNull(feedback_store::get_record($student->id, 0));
    }

    /**
     * MDL-INT-017: get_record returns the full stored row with its timestamps.
     */
    public function test_get_record_returns_full_row(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $student = $this->getDataGenerator()->create_user();

        $savedid = feedback_store::save($student->id, 0, 'Stored analysis text.');

        $record = feedback_store::get_record($student->id, 0);

        $this->assertNotNull($record);
        $this->assertSame($savedid, (int) $record->id);
        $this->assertSame('Stored analysis text.', $record->feedback);
        $this->assertGreaterThan(0, (int) $record->timemodified);
    }

    /**
     * MDL-INT-017: A new record stores the generating user and both
     * timestamps, and a regeneration by another user updates the generator
     * while keeping the creation time.
     */
    public function test_save_records_generating_user_and_timestamps(): void {
        $this->resetAfterTest();

        $student = $this->getDataGenerator()->create_user();
        $generatoruser = $this->getDataGenerator()->create_user();
        $otheruser = $this->getDataGenerator()->create_user();

        $this->setUser($generatoruser);
        $before = time();
        feedback_store::save($student->id, 0, 'Generated text.');

        $record = feedback_store::get_record($student->id, 0);

        $this->assertEquals($generatoruser->id, $record->usermodified);
        $this->assertGreaterThanOrEqual($before, (int) $record->timecreated);
        $this->assertLessThanOrEqual(time(), (int) $record->timecreated);
        $this->assertEquals($record->timecreated, $record->timemodified);

        $this->setUser($otheruser);
        $this->waitForSecond();
        feedback_store::save($student->id, 0, 'Regenerated text.');

        $updated = feedback_store::get_record($student->id, 0);

        $this->assertEquals($otheruser->id, $updated->usermodified);
        $this->assertEquals($record->timecreated, $updated->timecreated);
        $this->assertGreaterThan((int) $record->timemodified, (int) $updated->timemodified);
    }

    /**
     * MDL-INT-017: A second save replaces the text and never moves timemodified backwards.
     */
    public function test_get_record_after_upsert_reflects_replacement(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $student = $this->getDataGenerator()->create_user();

        feedback_store::save($student->id, 0, 'First analysis text.');
        $first = feedback_store::get_record($student->id, 0);

        $this->waitForSecond();

        feedback_store::save($student->id, 0, 'Replacement analysis text.');
        $second = feedback_store::get_record($student->id, 0);

        $this->assertSame((int) $first->id, (int) $second->id);
        $this->assertSame('Replacement analysis text.', $second->feedback);
        $this->assertGreaterThanOrEqual((int) $first->timemodified, (int) $second->timemodified);
    }

    /**
     * MDL-INT-017: The store returns null when no feedback exists for the pair.
     */
    public function test_get_returns_null_when_absent(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $student = $this->getDataGenerator()->create_user();

        $this->assertNull(feedback_store::get($student->id, 0));

        feedback_store::save($student->id, 0, 'Only for the all courses filter.');

        $this->assertNull(feedback_store::get($student->id, 999999));
    }
}

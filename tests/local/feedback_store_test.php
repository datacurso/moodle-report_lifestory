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
     * Ensures a saved feedback text can be read back for the same pair.
     */
    public function test_save_and_get_roundtrip(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $student = $this->getDataGenerator()->create_user();

        feedback_store::save($student->id, 0, 'Great progress this term.');

        $this->assertSame('Great progress this term.', feedback_store::get($student->id, 0));
    }

    /**
     * Ensures saving twice for the same pair replaces the text and keeps a single row.
     */
    public function test_save_upserts_and_keeps_a_single_row(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $student = $this->getDataGenerator()->create_user();

        feedback_store::save($student->id, 0, 'First feedback.');
        feedback_store::save($student->id, 0, 'Updated feedback.');

        $this->assertSame('Updated feedback.', feedback_store::get($student->id, 0));
        $this->assertEquals(1, $DB->count_records('report_lifestory_feedback', ['studentid' => $student->id]));
    }

    /**
     * Ensures feedback stored for different course filters does not overlap.
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
     * Ensures feedback stored for one student never leaks into another student.
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
     * Ensures the store returns null when no feedback exists for the pair.
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

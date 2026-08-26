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
 * Tests for the log events of report_lifestory.
 *
 * @package   report_lifestory
 * @category  test
 * @copyright 2026 Datacurso
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_lifestory\event;

use report_lifestory\local\feedback_store;

/**
 * Unit tests ensuring the four report actions raise events that identify both
 * the acting user and the consulted student.
 *
 * @package   report_lifestory
 * @category  test
 * @covers    \report_lifestory\event\report_viewed
 * @covers    \report_lifestory\event\feedback_generated
 * @covers    \report_lifestory\event\csv_exported
 * @covers    \report_lifestory\event\pdf_exported
 */
final class events_test extends \advanced_testcase {
    /**
     * Creates the shared fixture: an acting user, a student and a course id.
     *
     * @return array The student, the acting user, the system context and the course id.
     */
    private function prepare_fixture(): array {
        $this->resetAfterTest();

        $student = $this->getDataGenerator()->create_user();
        $actor = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $this->setUser($actor);

        return [$student, $actor, \context_system::instance(), (int) $course->id];
    }

    /**
     * Triggers the given event inside a sink and returns the captured event.
     *
     * @param \core\event\base $event The event to trigger.
     * @return \core\event\base The captured event instance.
     */
    private function capture_event(\core\event\base $event): \core\event\base {
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $sink->close();

        $this->assertCount(1, $events);

        return reset($events);
    }

    /**
     * Asserts the common contract every report_lifestory event must honour.
     *
     * @param \core\event\base $event The captured event.
     * @param \stdClass $student The consulted student.
     * @param \stdClass $actor The acting user.
     * @param \context $context The expected context.
     * @param string $crud The expected crud value.
     * @param int $courseid The expected course filter recorded in the event.
     * @return void
     */
    private function assert_common_contract(
        \core\event\base $event,
        \stdClass $student,
        \stdClass $actor,
        \context $context,
        string $crud,
        int $courseid
    ): void {
        $this->assertEquals($actor->id, $event->userid);
        $this->assertEquals($student->id, $event->relateduserid);
        $this->assertEquals($context->id, $event->get_context()->id);
        $this->assertSame($crud, $event->crud);
        $this->assertEquals(\core\event\base::LEVEL_OTHER, $event->edulevel);
        $this->assertNotEmpty($event->get_name());
        $this->assertStringContainsString("'{$actor->id}'", $event->get_description());
        $this->assertStringContainsString("'{$student->id}'", $event->get_description());
        $this->assertStringContainsString('report/lifestory', $event->get_url()->out(false));
        $this->assertEquals($student->id, $event->get_url()->get_param('userid'));

        // The course filter is always recorded as additional data.
        $this->assertArrayHasKey('courseid', $event->other);
        $this->assertSame($courseid, (int) $event->other['courseid']);
        if ($courseid) {
            $this->assertEquals($courseid, $event->get_url()->get_param('id'));
        } else {
            $this->assertNull($event->get_url()->get_param('id'));
        }
    }

    /**
     * MDL-INT-023: The report viewed event carries the actor, the student and
     * the course filter.
     */
    public function test_report_viewed_event(): void {
        [$student, $actor, $context, $courseid] = $this->prepare_fixture();

        $event = $this->capture_event(report_viewed::create_for_student((int) $student->id, $context, $courseid));

        $this->assertInstanceOf(report_viewed::class, $event);
        $this->assert_common_contract($event, $student, $actor, $context, 'r', $courseid);
        $this->assertSame(get_string('event:reportviewed', 'report_lifestory'), $event->get_name());
    }

    /**
     * MDL-INT-023: The report viewed event records a zero course filter when
     * the report covers every course.
     */
    public function test_report_viewed_event_without_course_filter(): void {
        [$student, $actor, $context] = $this->prepare_fixture();

        $event = $this->capture_event(report_viewed::create_for_student((int) $student->id, $context, 0));

        $this->assert_common_contract($event, $student, $actor, $context, 'r', 0);
    }

    /**
     * MDL-INT-023: The CSV exported event carries the actor, the student and
     * the course filter.
     */
    public function test_csv_exported_event(): void {
        [$student, $actor, $context, $courseid] = $this->prepare_fixture();

        $event = $this->capture_event(csv_exported::create_for_student((int) $student->id, $context, $courseid));

        $this->assertInstanceOf(csv_exported::class, $event);
        $this->assert_common_contract($event, $student, $actor, $context, 'r', $courseid);
        $this->assertSame(get_string('event:csvexported', 'report_lifestory'), $event->get_name());
    }

    /**
     * MDL-INT-023: The PDF exported event carries the actor, the student and
     * the course filter.
     */
    public function test_pdf_exported_event(): void {
        [$student, $actor, $context, $courseid] = $this->prepare_fixture();

        $event = $this->capture_event(pdf_exported::create_for_student((int) $student->id, $context, $courseid));

        $this->assertInstanceOf(pdf_exported::class, $event);
        $this->assert_common_contract($event, $student, $actor, $context, 'r', $courseid);
        $this->assertSame(get_string('event:pdfexported', 'report_lifestory'), $event->get_name());
    }

    /**
     * MDL-INT-023: The feedback generated event references the stored
     * feedback record and the course filter it was generated under.
     */
    public function test_feedback_generated_event(): void {
        [$student, $actor, $context, $courseid] = $this->prepare_fixture();

        $feedbackid = feedback_store::save((int) $student->id, $courseid, 'Generated feedback text.');
        $this->assertIsInt($feedbackid);

        $event = $this->capture_event(
            feedback_generated::create_for_student((int) $student->id, $context, $courseid, $feedbackid)
        );

        $this->assertInstanceOf(feedback_generated::class, $event);
        $this->assert_common_contract($event, $student, $actor, $context, 'c', $courseid);
        $this->assertSame(get_string('event:feedbackgenerated', 'report_lifestory'), $event->get_name());
        $this->assertEquals($feedbackid, $event->objectid);
        $this->assertSame('report_lifestory_feedback', $event->objecttable);

        $secondid = feedback_store::save((int) $student->id, $courseid, 'Updated feedback text.');
        $this->assertSame($feedbackid, $secondid);
    }
}

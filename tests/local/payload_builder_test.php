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
 * Tests for the payload builder of report_lifestory.
 *
 * @package   report_lifestory
 * @category  test
 * @copyright 2026 Datacurso
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_lifestory\local;

/**
 * Unit tests for the AI payload builder.
 *
 * The external AI service requires every section and every course in the
 * payload to carry a total object, so these tests ensure the builder never
 * emits a null total, even when the course has an atypical grade structure
 * with missing category or course grade items.
 *
 * @package   report_lifestory
 * @category  test
 * @covers    \report_lifestory\local\payload_builder
 */
final class payload_builder_test extends \advanced_testcase {
    /**
     * Creates the common fixture: a course with a graded category, a student
     * and a system manager set as the current user.
     *
     * @return array Array with course, student and grade category record.
     */
    private function create_fixture(): array {
        global $DB;

        $generator = $this->getDataGenerator();

        $course = $generator->create_course(['fullname' => 'Course One']);

        $student = $generator->create_user();
        $generator->enrol_user($student->id, $course->id, 'student');

        $category = $generator->create_grade_category(['courseid' => $course->id]);
        $item = $generator->create_grade_item([
            'courseid' => $course->id,
            'categoryid' => $category->id,
            'itemname' => 'Manual item',
        ]);
        $generator->create_grade_grade([
            'itemid' => $item->id,
            'userid' => $student->id,
            'grade' => 8,
        ]);

        $manager = $generator->create_user();
        $managerroleid = $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        role_assign($managerroleid, $manager->id, \context_system::instance()->id);
        $this->setUser($manager);

        return [$course, $student, $category];
    }

    /**
     * Creates a second graded course and enrols the given student in it.
     *
     * @param \stdClass $student The student to enrol in the new course.
     * @return \stdClass The created course record.
     */
    private function create_second_course(\stdClass $student): \stdClass {
        $generator = $this->getDataGenerator();

        $course = $generator->create_course(['fullname' => 'Course Two']);
        $generator->enrol_user($student->id, $course->id, 'student');

        $item = $generator->create_grade_item([
            'courseid' => $course->id,
            'itemname' => 'Second course item',
        ]);
        $generator->create_grade_grade([
            'itemid' => $item->id,
            'userid' => $student->id,
            'grade' => 6,
        ]);

        return $course;
    }

    /**
     * Asserts every course and section total in the payload is a non-null
     * array carrying at least the name key.
     *
     * @param array $payload Payload returned by the builder.
     */
    private function assert_no_null_totals(array $payload): void {
        foreach ($payload['courses'] as $course) {
            $this->assertIsArray($course['total']);
            $this->assertArrayHasKey('name', $course['total']);

            foreach ($course['sections'] as $section) {
                $this->assertIsArray($section['total']);
                $this->assertArrayHasKey('name', $section['total']);
            }
        }
    }

    /**
     * Asserts a total entry is the placeholder used for missing grade items.
     *
     * @param array $total Total entry from the payload.
     */
    private function assert_missing_total_marker(array $total): void {
        $this->assertSame('Total not available', $total['name']);
        $this->assertNull($total['calculated_weight']);
        $this->assertNull($total['grade']);
        $this->assertNull($total['range']);
        $this->assertNull($total['percentage']);
        $this->assertSame('', $total['feedback']);
        $this->assertNull($total['contribution_to_total']);
    }

    /**
     * Ensures a normal course produces non-null totals for every course and
     * section in the payload.
     */
    public function test_normal_course_has_no_null_totals(): void {
        $this->resetAfterTest();

        [$course, $student] = $this->create_fixture();

        $payload = payload_builder::build($student->id);

        $this->assertCount(1, $payload['courses']);
        $this->assertSame($course->fullname, $payload['courses'][0]['name']);
        $this->assertNotEmpty($payload['courses'][0]['sections']);
        $this->assert_no_null_totals($payload);
    }

    /**
     * Ensures a deleted category grade item never produces a null section
     * total: the grade API recreates the item on access (self-healing), and
     * the builder guard covers the case where it cannot, so every section
     * total in the payload is always a complete object.
     */
    public function test_missing_category_item_never_yields_null_section_total(): void {
        global $DB;

        $this->resetAfterTest();

        [, $student, $category] = $this->create_fixture();

        // Simulate an atypical structure: the category total item is gone.
        $DB->delete_records('grade_items', ['itemtype' => 'category', 'iteminstance' => $category->id]);

        $payload = payload_builder::build($student->id);

        $this->assertNotEmpty($payload['courses'][0]['sections']);
        $this->assert_no_null_totals($payload);
    }

    /**
     * Ensures a course left without any grade item yields the placeholder
     * total at course level instead of a null value the external service
     * would reject.
     */
    public function test_course_without_grade_items_yields_placeholder_course_total(): void {
        global $DB;

        $this->resetAfterTest();

        [$course, $student] = $this->create_fixture();

        // Simulate a fully corrupted structure: no grade items and no grade
        // categories at all. While the course grade category row exists, the
        // grade API recreates the course total item on access, so this is the
        // only scenario where a total cannot self-heal.
        $DB->delete_records('grade_items', ['courseid' => $course->id]);
        $DB->delete_records('grade_categories', ['courseid' => $course->id]);

        $payload = payload_builder::build($student->id);

        $this->assert_missing_total_marker($payload['courses'][0]['total']);
        $this->assert_no_null_totals($payload);
    }

    /**
     * Ensures a course id filter limits the payload to that single course.
     */
    public function test_build_with_course_filter_returns_only_that_course(): void {
        $this->resetAfterTest();

        [$course1, $student] = $this->create_fixture();
        $this->create_second_course($student);

        $payload = payload_builder::build($student->id, (int)$course1->id);

        $this->assertCount(1, $payload['courses']);
        $this->assertSame($course1->fullname, $payload['courses'][0]['name']);
    }

    /**
     * Ensures the default course id keeps every permitted course in the payload.
     */
    public function test_build_without_course_filter_returns_all_permitted_courses(): void {
        $this->resetAfterTest();

        [$course1, $student] = $this->create_fixture();
        $course2 = $this->create_second_course($student);

        $payload = payload_builder::build($student->id, 0);

        $names = array_column($payload['courses'], 'name');
        $this->assertCount(2, $payload['courses']);
        $this->assertContains($course1->fullname, $names);
        $this->assertContains($course2->fullname, $names);
    }

    /**
     * Ensures filtering by a course the current user cannot view grades in
     * yields an empty course list instead of leaking the course data.
     */
    public function test_build_with_inaccessible_course_filter_returns_no_courses(): void {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();

        [$course1, $student] = $this->create_fixture();
        $course2 = $this->create_second_course($student);

        // A teacher with grade access only in the first course is the viewer.
        $teacher = $generator->create_user();
        $generator->enrol_user($teacher->id, $course1->id, 'editingteacher');
        $this->setUser($teacher);

        $payload = payload_builder::build($student->id, (int)$course2->id);

        $this->assertSame([], $payload['courses']);
    }

    /**
     * Ensures the payload root carries exactly the expected keys.
     */
    public function test_payload_root_keys(): void {
        $this->resetAfterTest();

        [, $student] = $this->create_fixture();

        $payload = payload_builder::build($student->id);

        $this->assertSame(['userid', 'student_id', 'student_name', 'courses'], array_keys($payload));
    }
}

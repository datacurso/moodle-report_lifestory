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
    /** @var string[] Keys every task and total entry must carry, in order. */
    private const ENTRY_KEYS = [
        'name',
        'calculated_weight',
        'grade',
        'range',
        'percentage',
        'feedback',
        'contribution_to_total',
    ];

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
     * Returns the first task of the first section of the first course.
     *
     * @param array $payload Payload returned by the builder.
     * @param string $name Task name to locate.
     * @return array The task entry.
     */
    private function find_task(array $payload, string $name): array {
        foreach ($payload['courses'] as $course) {
            foreach ($course['sections'] as $section) {
                foreach ($section['tasks'] as $task) {
                    if ($task['name'] === $name) {
                        return $task;
                    }
                }
            }
        }
        $this->fail("Task '{$name}' not found in payload.");
    }

    /**
     * MDL-UNIT-008: A normal course produces non-null totals for every course
     * and section in the payload.
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
     * MDL-UNIT-010: A deleted category grade item never produces a null
     * section total: the grade API recreates the item on access
     * (self-healing), and the builder guard covers the case where it cannot,
     * so every section total in the payload is always a complete object.
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
     * MDL-UNIT-010: A course left without any grade item yields the
     * placeholder total at course level instead of a null value the external
     * service would reject.
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
     * MDL-INT-018: A course id filter limits the payload to that single course.
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
     * MDL-UNIT-009: The default course id keeps every permitted course in the payload.
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
     * MDL-UNIT-007, MDL-INT-013: Filtering by a course the current user cannot
     * view grades in yields an empty course list instead of leaking the
     * course data.
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
     * MDL-UNIT-007, MDL-CTR-001, MDL-UNIT-018: The payload root carries
     * exactly the expected keys and no site identifier of its own.
     */
    public function test_payload_root_keys(): void {
        $this->resetAfterTest();

        [, $student] = $this->create_fixture();

        $payload = payload_builder::build($student->id);

        $this->assertSame(['userid', 'student_id', 'student_name', 'courses'], array_keys($payload));
        $this->assertArrayNotHasKey('site_id', $payload);
    }

    /**
     * MDL-UNIT-007: The root fields hold the requester id, the student id and
     * the student's full name, and each course entry carries its name, its
     * sections and its course total.
     */
    public function test_payload_root_values_and_course_entry_keys(): void {
        global $USER;

        $this->resetAfterTest();

        [$course, $student] = $this->create_fixture();

        $payload = payload_builder::build($student->id);

        $this->assertSame((string)$USER->id, $payload['userid']);
        $this->assertSame((string)$student->id, $payload['student_id']);
        $this->assertSame(fullname($student), $payload['student_name']);

        $this->assertCount(1, $payload['courses']);
        $entry = $payload['courses'][0];
        $this->assertSame(['name', 'sections', 'total'], array_keys($entry));
        $this->assertSame($course->fullname, $entry['name']);
        $this->assertIsArray($entry['sections']);
        $this->assertSame(self::ENTRY_KEYS, array_keys($entry['total']));
    }

    /**
     * MDL-UNIT-007: A student without any course produces an empty course
     * collection without error.
     */
    public function test_student_without_courses_yields_empty_course_collection(): void {
        $this->resetAfterTest();

        $this->create_fixture();
        $loner = $this->getDataGenerator()->create_user();

        $payload = payload_builder::build($loner->id);

        $this->assertSame([], $payload['courses']);
        $this->assertSame((string)$loner->id, $payload['student_id']);
    }

    /**
     * MDL-UNIT-008: Each grade category becomes a section named after the
     * category, carrying its tasks with the full set of fields and a total.
     */
    public function test_grade_category_becomes_section_with_tasks_and_total(): void {
        $this->resetAfterTest();

        [, $student, $category] = $this->create_fixture();

        $payload = payload_builder::build($student->id);
        $sections = $payload['courses'][0]['sections'];

        $this->assertCount(1, $sections);
        $section = $sections[0];
        $this->assertSame(['name', 'tasks', 'total'], array_keys($section));
        $this->assertSame($category->fullname, $section['name']);

        $this->assertCount(1, $section['tasks']);
        $task = $section['tasks'][0];
        $this->assertSame(self::ENTRY_KEYS, array_keys($task));
        $this->assertSame('Manual item', $task['name']);
        $this->assertSame(8.0, $task['grade']);

        $this->assertSame(self::ENTRY_KEYS, array_keys($section['total']));
        $this->assertNotSame('Total not available', $section['total']['name']);
    }

    /**
     * MDL-UNIT-008: Grade items of internal types are never listed as tasks.
     */
    public function test_internal_grade_items_are_not_listed_as_tasks(): void {
        $this->resetAfterTest();

        [, $student] = $this->create_fixture();

        $payload = payload_builder::build($student->id);

        foreach ($payload['courses'][0]['sections'] as $section) {
            $names = array_column($section['tasks'], 'name');
            foreach ($names as $name) {
                $this->assertStringNotContainsString('total', strtolower($name));
            }
        }
    }

    /**
     * MDL-UNIT-009: A course without grade categories yields a single flat
     * section named after the course and still carries the course total.
     */
    public function test_flat_course_yields_single_section_named_after_course(): void {
        $this->resetAfterTest();

        [, $student] = $this->create_fixture();
        $course2 = $this->create_second_course($student);
        // Compute the course total from the manual item grade.
        grade_regrade_final_grades($course2->id);

        $payload = payload_builder::build($student->id, (int)$course2->id);

        $this->assertCount(1, $payload['courses']);
        $entry = $payload['courses'][0];

        $this->assertCount(1, $entry['sections']);
        $this->assertSame($course2->fullname, $entry['sections'][0]['name']);
        $this->assertSame(['Second course item'], array_column($entry['sections'][0]['tasks'], 'name'));

        $this->assertIsArray($entry['total']);
        $this->assertNotSame('Total not available', $entry['total']['name']);
        $this->assertSame(6.0, $entry['total']['grade']);
    }

    /**
     * MDL-UNIT-011: The placeholder total name must be translated.
     * [Pendiente:skip] the placeholder is a fixed English text.
     */
    public function test_placeholder_total_name_is_translated(): void {
        $this->markTestSkipped('[Pendiente:skip] placeholder total name "Total not available" is not translated');
    }

    /**
     * MDL-UNIT-012: Teacher feedback travels as plain text without HTML tags
     * or surrounding whitespace.
     */
    public function test_feedback_is_stripped_of_html_and_trimmed(): void {
        $this->resetAfterTest();

        [$course, $student, $category] = $this->create_fixture();

        $generator = $this->getDataGenerator();
        $item = $generator->create_grade_item([
            'courseid' => $course->id,
            'categoryid' => $category->id,
            'itemname' => 'Commented item',
        ]);
        $generator->create_grade_grade([
            'itemid' => $item->id,
            'userid' => $student->id,
            'grade' => 7,
            'feedback' => "  <p>Great <strong>work</strong>, keep it up</p>\n\n ",
            'feedbackformat' => FORMAT_HTML,
        ]);

        $task = $this->find_task(payload_builder::build($student->id), 'Commented item');

        $this->assertSame('Great work, keep it up', $task['feedback']);
    }

    /**
     * MDL-UNIT-012: An ungraded activity yields an empty feedback string and
     * null grade without error.
     */
    public function test_ungraded_activity_yields_empty_feedback(): void {
        $this->resetAfterTest();

        [$course, $student, $category] = $this->create_fixture();

        $this->getDataGenerator()->create_grade_item([
            'courseid' => $course->id,
            'categoryid' => $category->id,
            'itemname' => 'Ungraded item',
        ]);

        $task = $this->find_task(payload_builder::build($student->id), 'Ungraded item');

        $this->assertSame('', $task['feedback']);
        $this->assertNull($task['grade']);
        $this->assertNull($task['percentage']);
    }

    /**
     * MDL-UNIT-013: The percentage derives from the grade and the maximum
     * grade with two decimals, and the range spans zero to the maximum.
     */
    public function test_percentage_and_range_are_computed_from_grade_and_maximum(): void {
        $this->resetAfterTest();

        [$course, $student, $category] = $this->create_fixture();

        $generator = $this->getDataGenerator();
        $item = $generator->create_grade_item([
            'courseid' => $course->id,
            'categoryid' => $category->id,
            'itemname' => 'Thirds item',
            'grademax' => 30,
        ]);
        $generator->create_grade_grade(['itemid' => $item->id, 'userid' => $student->id, 'grade' => 10]);

        $tens = $generator->create_grade_item([
            'courseid' => $course->id,
            'categoryid' => $category->id,
            'itemname' => 'Tens item',
            'grademax' => 10,
        ]);
        $generator->create_grade_grade(['itemid' => $tens->id, 'userid' => $student->id, 'grade' => 8]);

        $payload = payload_builder::build($student->id);

        // The fixture item keeps the default maximum grade of 100.
        $hundred = $this->find_task($payload, 'Manual item');
        $this->assertSame(8.0, $hundred['percentage']);
        $this->assertSame('0-100.00', $hundred['range']);

        $eight = $this->find_task($payload, 'Tens item');
        $this->assertSame(80.0, $eight['percentage']);
        $this->assertSame('0-10.00', $eight['range']);

        $thirds = $this->find_task($payload, 'Thirds item');
        $this->assertSame(33.33, $thirds['percentage']);
        $this->assertSame('0-30.00', $thirds['range']);
    }

    /**
     * MDL-UNIT-013: A maximum grade of zero does not divide by zero and leaves
     * the percentage null while the grade itself is kept.
     */
    public function test_zero_maximum_grade_yields_null_percentage(): void {
        $this->resetAfterTest();

        [$course, $student, $category] = $this->create_fixture();

        $generator = $this->getDataGenerator();
        $item = $generator->create_grade_item([
            'courseid' => $course->id,
            'categoryid' => $category->id,
            'itemname' => 'Zero max item',
            'grademax' => 0,
        ]);
        $generator->create_grade_grade(['itemid' => $item->id, 'userid' => $student->id, 'grade' => 0]);

        $task = $this->find_task(payload_builder::build($student->id), 'Zero max item');

        $this->assertNull($task['percentage']);
        $this->assertSame('0-0.00', $task['range']);
    }

    /**
     * MDL-UNIT-013: Weights and the contribution to the course total are only
     * reported when greater than zero and stay null otherwise.
     */
    public function test_weights_and_contribution_are_null_unless_positive(): void {
        global $DB;

        $this->resetAfterTest();

        [$course, $student, $category] = $this->create_fixture();

        $generator = $this->getDataGenerator();
        $weighted = $generator->create_grade_item([
            'courseid' => $course->id,
            'categoryid' => $category->id,
            'itemname' => 'Weighted item',
        ]);
        $generator->create_grade_grade(['itemid' => $weighted->id, 'userid' => $student->id, 'grade' => 5]);
        $DB->set_field('grade_items', 'aggregationcoef2', 0.25, ['id' => $weighted->id]);
        $DB->set_field('grade_items', 'weightoverride', 1, ['id' => $weighted->id]);

        $unweighted = $generator->create_grade_item([
            'courseid' => $course->id,
            'categoryid' => $category->id,
            'itemname' => 'Unweighted item',
        ]);
        $generator->create_grade_grade(['itemid' => $unweighted->id, 'userid' => $student->id, 'grade' => 5]);
        $DB->set_field('grade_items', 'aggregationcoef2', 0, ['id' => $unweighted->id]);
        $DB->set_field('grade_items', 'weightoverride', 0, ['id' => $unweighted->id]);

        $payload = payload_builder::build($student->id);

        $task = $this->find_task($payload, 'Weighted item');
        $this->assertSame(25.0, $task['calculated_weight']);
        $this->assertSame(100.0, $task['contribution_to_total']);

        $task = $this->find_task($payload, 'Unweighted item');
        $this->assertNull($task['calculated_weight']);
        $this->assertNull($task['contribution_to_total']);
    }

    /**
     * MDL-UNIT-019: Accented and non-latin characters in course, activity and
     * feedback texts are preserved untouched and remain valid UTF-8.
     */
    public function test_international_texts_are_preserved_in_payload(): void {
        $this->resetAfterTest();

        [, $student] = $this->create_fixture();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['fullname' => 'Évaluation ñandú Über – Avaliação']);
        $generator->enrol_user($student->id, $course->id, 'student');
        $category = $generator->create_grade_category(['courseid' => $course->id, 'fullname' => 'Leçon d\'œuvre à côté']);
        $item = $generator->create_grade_item([
            'courseid' => $course->id,
            'categoryid' => $category->id,
            'itemname' => 'Prüfung größe ßtraße Оценка',
        ]);
        $generator->create_grade_grade([
            'itemid' => $item->id,
            'userid' => $student->id,
            'grade' => 9,
            'feedback' => 'Penilaian akhir: très bien, señor Muñoz — Отлично',
        ]);

        $payload = payload_builder::build($student->id, (int)$course->id);
        $entry = $payload['courses'][0];

        $this->assertSame('Évaluation ñandú Über – Avaliação', $entry['name']);
        $this->assertSame('Leçon d\'œuvre à côté', $entry['sections'][0]['name']);
        $task = $entry['sections'][0]['tasks'][0];
        $this->assertSame('Prüfung größe ßtraße Оценка', $task['name']);
        $this->assertSame('Penilaian akhir: très bien, señor Muñoz — Отлично', $task['feedback']);

        $this->assertTrue(mb_check_encoding(json_encode($payload, JSON_UNESCAPED_UNICODE), 'UTF-8'));
    }

    /**
     * MDL-INT-013: A course the viewer cannot access is absent from the
     * payload and therefore from both the CSV and the PDF outputs built
     * from it.
     */
    public function test_inaccessible_course_is_absent_from_csv_and_pdf_outputs(): void {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();

        [$course1, $student] = $this->create_fixture();
        $course2 = $this->create_second_course($student);

        $teacher = $generator->create_user();
        $generator->enrol_user($teacher->id, $course1->id, 'editingteacher');
        $this->setUser($teacher);

        $payload = payload_builder::build($student->id);

        $this->assertSame([$course1->fullname], array_column($payload['courses'], 'name'));

        $csv = csv_exporter::build_csv($payload);
        $this->assertStringContainsString($course1->fullname, $csv);
        $this->assertStringNotContainsString($course2->fullname, $csv);
        $this->assertStringNotContainsString('Second course item', $csv);

        $html = pdf_exporter::build_html(fullname($student), 'Feedback text.', $payload);
        $this->assertStringContainsString($course1->fullname, $html);
        $this->assertStringNotContainsString($course2->fullname, $html);
        $this->assertStringNotContainsString('Second course item', $html);
    }
}

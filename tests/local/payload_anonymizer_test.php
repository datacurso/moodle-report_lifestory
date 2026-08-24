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
 * Tests for the payload anonymizer of report_lifestory.
 *
 * @package   report_lifestory
 * @category  test
 * @copyright 2026 Datacurso
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_lifestory\local;

/**
 * Unit tests for the anonymization of AI payloads.
 *
 * Covers the student name placeholder, the masking of the student name
 * inside feedback texts, the deterministic pseudonymization of identifier
 * fields, and the de-anonymization of AI replies.
 *
 * @package   report_lifestory
 * @category  test
 * @covers    \report_lifestory\local\payload_anonymizer
 */
final class payload_anonymizer_test extends \advanced_testcase {
    /**
     * Builds a task or total entry with the payload shape used by the builder.
     *
     * @param string $name Item name.
     * @param string $feedback Feedback text.
     * @return array Task entry.
     */
    private static function task(string $name, string $feedback): array {
        return [
            'name' => $name,
            'calculated_weight' => 25.0,
            'grade' => 8.5,
            'range' => '0-10.00',
            'percentage' => 85.0,
            'feedback' => $feedback,
            'contribution_to_total' => 21.25,
        ];
    }

    /**
     * Builds a representative payload fixture with name-bearing feedbacks.
     *
     * @return array Payload fixture.
     */
    private static function create_payload(): array {
        return [
            'site_id' => 'abc123sitehash',
            'userid' => '5',
            'student_id' => '42',
            'student_name' => 'María José Pérez',
            'courses' => [
                [
                    'name' => 'Curso de María',
                    'sections' => [
                        [
                            'name' => 'Sección de María',
                            'tasks' => [
                                self::task('Tarea de María', 'Excelente trabajo, María José Pérez, sigue así'),
                                self::task('Tarea dos', 'maría mejora la ortografía'),
                                self::task('Tarea tres', 'El apellido Pérez aparece solo'),
                            ],
                            'total' => self::task('Total sección', 'Buen uso de la periferia'),
                        ],
                    ],
                    'total' => self::task('Total curso', 'Sin nombres en este comentario'),
                ],
                [
                    'name' => 'Curso dos',
                    'sections' => [
                        [
                            'name' => 'Sección dos',
                            'tasks' => [
                                self::task('Tarea cuatro', 'María José Pérez'),
                            ],
                            'total' => null,
                        ],
                    ],
                    'total' => null,
                ],
            ],
        ];
    }

    /**
     * Pins the site identifier so pseudonyms are stable across runs.
     */
    private function set_fixed_site_identifier(): void {
        global $CFG;

        $this->resetAfterTest();
        $CFG->siteidentifier = 'lifestory-test-site-0';
    }

    /**
     * Ensures the student name is replaced and mapped for de-anonymization.
     */
    public function test_student_name_is_replaced_and_mapped(): void {
        $this->set_fixed_site_identifier();

        $result = payload_anonymizer::anonymize(self::create_payload());

        $this->assertSame('[STUDENT_NAME]', $result['payload']['student_name']);
        $this->assertSame(['[STUDENT_NAME]' => 'María José Pérez'], $result['replacements']);
    }

    /**
     * Ensures identifier fields become deterministic 16-hex-char pseudonyms.
     */
    public function test_identifier_fields_are_pseudonymized(): void {
        $this->set_fixed_site_identifier();

        $first = payload_anonymizer::anonymize(self::create_payload());
        $second = payload_anonymizer::anonymize(self::create_payload());

        $userid = $first['payload']['userid'];
        $studentid = $first['payload']['student_id'];

        $this->assertNotSame('5', $userid);
        $this->assertNotSame('42', $studentid);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $userid);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $studentid);
        $this->assertNotSame($userid, $studentid);
        $this->assertStringNotContainsString('5', $userid);
        $this->assertStringNotContainsString('42', $studentid);

        // Deterministic: two anonymize calls produce the same pseudonyms.
        $this->assertSame($userid, $second['payload']['userid']);
        $this->assertSame($studentid, $second['payload']['student_id']);

        // Pseudonyms are never added to the replacements map.
        $this->assertSame(['[STUDENT_NAME]' => 'María José Pérez'], $first['replacements']);
    }

    /**
     * Ensures name-bearing feedbacks are masked and other values untouched.
     */
    public function test_feedback_texts_are_masked(): void {
        $this->set_fixed_site_identifier();

        $result = payload_anonymizer::anonymize(self::create_payload());
        $payload = $result['payload'];

        $course1 = $payload['courses'][0];
        $tasks = $course1['sections'][0]['tasks'];

        $this->assertSame('Excelente trabajo, [STUDENT_NAME], sigue así', $tasks[0]['feedback']);
        $this->assertSame('[STUDENT_NAME] mejora la ortografía', $tasks[1]['feedback']);
        $this->assertSame('El apellido [STUDENT_NAME] aparece solo', $tasks[2]['feedback']);

        // A name fragment inside another word must not be replaced.
        $this->assertSame('Buen uso de la periferia', $course1['sections'][0]['total']['feedback']);

        // A feedback without any name stays unchanged.
        $this->assertSame('Sin nombres en este comentario', $course1['total']['feedback']);

        // No feedback keeps any name token, case-insensitively.
        foreach ($payload['courses'] as $course) {
            foreach ($course['sections'] as $section) {
                foreach ($section['tasks'] as $task) {
                    $this->assert_no_name_tokens($task['feedback']);
                }
                if ($section['total'] !== null) {
                    $this->assert_no_name_tokens($section['total']['feedback']);
                }
            }
            if ($course['total'] !== null) {
                $this->assert_no_name_tokens($course['total']['feedback']);
            }
        }

        // Masking applies only to feedback keys: names elsewhere stay untouched.
        $this->assertSame('Curso de María', $course1['name']);
        $this->assertSame('Sección de María', $course1['sections'][0]['name']);
        $this->assertSame('Tarea de María', $tasks[0]['name']);
    }

    /**
     * Ensures the full name alone collapses into exactly one placeholder.
     */
    public function test_adjacent_placeholders_are_collapsed(): void {
        $this->set_fixed_site_identifier();

        $result = payload_anonymizer::anonymize(self::create_payload());

        $feedback = $result['payload']['courses'][1]['sections'][0]['tasks'][0]['feedback'];

        $this->assertSame('[STUDENT_NAME]', $feedback);
    }

    /**
     * Ensures de-anonymization restores multiple placeholders in a reply.
     */
    public function test_deanonymize_text_restores_placeholders(): void {
        $replacements = ['[STUDENT_NAME]' => 'María José Pérez'];
        $reply = '[STUDENT_NAME] avanza bien. Recomendamos que [STUDENT_NAME] refuerce ortografía.';

        $restored = payload_anonymizer::deanonymize_text($reply, $replacements);

        $this->assertSame(
            'María José Pérez avanza bien. Recomendamos que María José Pérez refuerce ortografía.',
            $restored
        );
    }

    /**
     * Ensures the full roundtrip restores the real name in a crafted reply.
     */
    public function test_full_roundtrip_restores_real_name(): void {
        $this->set_fixed_site_identifier();

        $result = payload_anonymizer::anonymize(self::create_payload());

        $this->assertSame('[STUDENT_NAME]', $result['payload']['student_name']);

        $fakereply = 'El estudiante [STUDENT_NAME] muestra un progreso constante.';
        $restored = payload_anonymizer::deanonymize_text($fakereply, $result['replacements']);

        $this->assertStringContainsString('María José Pérez', $restored);
        $this->assertStringNotContainsString('[STUDENT_NAME]', $restored);
    }

    /**
     * Asserts a feedback text contains no student name token in any case.
     *
     * @param string $feedback Masked feedback text.
     */
    private function assert_no_name_tokens(string $feedback): void {
        $lower = mb_strtolower($feedback);

        $this->assertStringNotContainsString('maría', $lower);
        $this->assertStringNotContainsString('pérez', $lower);
    }
}

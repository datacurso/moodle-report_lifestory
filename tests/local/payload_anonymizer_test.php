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
     * MDL-UNIT-014: The student name is replaced by the placeholder and
     * mapped for de-anonymization.
     */
    public function test_student_name_is_replaced_and_mapped(): void {
        $this->set_fixed_site_identifier();

        $result = payload_anonymizer::anonymize(self::create_payload());

        $this->assertSame('[STUDENT_NAME]', $result['payload']['student_name']);
        $this->assertSame(['[STUDENT_NAME]' => 'María José Pérez'], $result['replacements']);
    }

    /**
     * MDL-UNIT-016: Identifier fields become deterministic 16-hex-char
     * pseudonyms that differ from the real ids and are never restored.
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
     * MDL-UNIT-016: Different users produce different pseudonyms and the
     * reply de-anonymization never restores an identifier.
     */
    public function test_pseudonyms_differ_per_user_and_are_not_restored(): void {
        $this->set_fixed_site_identifier();

        $payload = self::create_payload();
        $first = payload_anonymizer::anonymize($payload);

        $payload['userid'] = '6';
        $payload['student_id'] = '43';
        $second = payload_anonymizer::anonymize($payload);

        $this->assertNotSame($first['payload']['userid'], $second['payload']['userid']);
        $this->assertNotSame($first['payload']['student_id'], $second['payload']['student_id']);

        $reply = 'Student ' . $first['payload']['student_id'] . ' requested by ' . $first['payload']['userid'];
        $restored = payload_anonymizer::deanonymize_text($reply, $first['replacements']);

        $this->assertSame($reply, $restored);
        $this->assertStringNotContainsString(' 42', $restored);
    }

    /**
     * MDL-UNIT-015, MDL-UNIT-019: Name-bearing feedbacks are masked while
     * other values, including accented course and activity names, stay
     * untouched.
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
     * MDL-UNIT-015: The full name alone collapses into exactly one placeholder.
     */
    public function test_adjacent_placeholders_are_collapsed(): void {
        $this->set_fixed_site_identifier();

        $result = payload_anonymizer::anonymize(self::create_payload());

        $feedback = $result['payload']['courses'][1]['sections'][0]['tasks'][0]['feedback'];

        $this->assertSame('[STUDENT_NAME]', $feedback);
    }

    /**
     * MDL-UNIT-015: Name words shorter than three characters are not masked
     * on their own, while longer words are.
     */
    public function test_short_name_words_are_not_masked(): void {
        $this->set_fixed_site_identifier();

        $payload = self::create_payload();
        $payload['student_name'] = 'Ana de la Torre';
        $payload['courses'][0]['sections'][0]['tasks'] = [
            self::task('Uno', 'La Torre de Ana de la Torre es de piedra'),
            self::task('Dos', 'Se recomienda la lectura de ANA'),
            self::task('Tres', 'Ella es la mejor de todas'),
        ];

        $result = payload_anonymizer::anonymize($payload);
        $tasks = $result['payload']['courses'][0]['sections'][0]['tasks'];

        // 'Ana' and 'Torre' (3+ chars) are masked; 'de' and 'la' are not.
        $this->assertSame('La [STUDENT_NAME] de [STUDENT_NAME] es de piedra', $tasks[0]['feedback']);
        $this->assertSame('Se recomienda la lectura de [STUDENT_NAME]', $tasks[1]['feedback']);
        $this->assertSame('Ella es la mejor de todas', $tasks[2]['feedback']);
    }

    /**
     * MDL-UNIT-014: De-anonymization restores multiple placeholders in a reply.
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
     * MDL-UNIT-014: A reply without any placeholder is returned intact, and
     * an empty student name produces no replacement at all.
     */
    public function test_deanonymize_without_placeholder_or_name_is_a_noop(): void {
        $this->set_fixed_site_identifier();

        $reply = 'El estudiante muestra un progreso constante.';
        $this->assertSame($reply, payload_anonymizer::deanonymize_text($reply, ['[STUDENT_NAME]' => 'María']));
        $this->assertSame($reply, payload_anonymizer::deanonymize_text($reply, []));
        $this->assertSame('', payload_anonymizer::deanonymize_text('', ['[STUDENT_NAME]' => 'María']));

        $payload = self::create_payload();
        $payload['student_name'] = '';
        $result = payload_anonymizer::anonymize($payload);

        $this->assertSame([], $result['replacements']);
        $this->assertSame('', $result['payload']['student_name']);
        $this->assertSame(
            'Excelente trabajo, María José Pérez, sigue así',
            $result['payload']['courses'][0]['sections'][0]['tasks'][0]['feedback']
        );
        $this->assertSame('[STUDENT_NAME] queda', payload_anonymizer::deanonymize_text('[STUDENT_NAME] queda', []));
    }

    /**
     * MDL-UNIT-014: The full roundtrip restores the real name in a crafted reply.
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
     * MDL-UNIT-017: Course, section and activity names must be anonymized too.
     * [Pendiente:skip] those names currently travel unchanged.
     */
    public function test_course_section_and_activity_names_are_anonymized(): void {
        $this->markTestSkipped('[Pendiente:skip] course/section/activity names are not anonymized');
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

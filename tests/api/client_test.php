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
 * Tests for the AI client of report_lifestory.
 *
 * @package   report_lifestory
 * @category  test
 * @copyright 2026 Datacurso
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_lifestory\api;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/filelib.php');

use aiprovider_datacurso\httpclient\datacurso_api_base;
use report_lifestory\local\payload_anonymizer;
use report_lifestory\local\payload_builder;

/**
 * Tests for the generation flow against a simulated AI service, the
 * provider-level site identifier and the payload contract.
 *
 * The provider HTTP client is exercised for real; only the transport is
 * replaced through the core curl mock, which serves queued responses in
 * LIFO order. Every send_to_ai() call performs two HTTP requests: the
 * license region lookup made by the client constructor and the analysis
 * request itself, so the analysis reply is queued first.
 *
 * @package   report_lifestory
 * @category  test
 * @covers    \report_lifestory\api\client
 */
final class client_test extends \advanced_testcase {
    /**
     * Configures a license key and queues the mocked HTTP responses.
     *
     * @param string $analysisreply Raw body returned by the analysis endpoint.
     * @return void
     */
    private function mock_ai_service(string $analysisreply): void {
        $this->resetAfterTest();
        set_config('licensekey', 'test-license-key', 'aiprovider_datacurso');

        // LIFO queue: the region lookup is consumed first, then the analysis reply.
        \curl::mock_response($analysisreply);
        \curl::mock_response(json_encode(['is_for_eu' => false]));
    }

    /**
     * Builds a payload with the shape produced by the payload builder.
     *
     * @return array Payload fixture.
     */
    private static function create_payload(): array {
        return [
            'userid' => '5',
            'student_id' => '42',
            'student_name' => 'María José Pérez',
            'courses' => [
                [
                    'name' => 'Curso uno',
                    'sections' => [
                        [
                            'name' => 'Unidad uno',
                            'tasks' => [
                                [
                                    'name' => 'Tarea uno',
                                    'calculated_weight' => 25.0,
                                    'grade' => 8.0,
                                    'range' => '0-10.00',
                                    'percentage' => 80.0,
                                    'feedback' => 'Buen trabajo, María José Pérez',
                                    'contribution_to_total' => null,
                                ],
                            ],
                            'total' => [
                                'name' => 'Unidad uno total',
                                'calculated_weight' => null,
                                'grade' => 8.0,
                                'range' => '0-10.00',
                                'percentage' => 80.0,
                                'feedback' => '',
                                'contribution_to_total' => null,
                            ],
                        ],
                    ],
                    'total' => [
                        'name' => 'Total not available',
                        'calculated_weight' => null,
                        'grade' => null,
                        'range' => null,
                        'percentage' => null,
                        'feedback' => '',
                        'contribution_to_total' => null,
                    ],
                ],
            ],
        ];
    }

    /**
     * MDL-INT-016: A valid reply from the service is returned with the
     * student's real name restored in place of the placeholder.
     */
    public function test_send_to_ai_returns_reply_with_real_name_restored(): void {
        $this->mock_ai_service(json_encode(['reply' => "# Hola [STUDENT_NAME]\n\n[STUDENT_NAME] progresa bien."]));

        $reply = client::send_to_ai(self::create_payload());

        $this->assertSame("# Hola María José Pérez\n\nMaría José Pérez progresa bien.", $reply);
        $this->assertStringNotContainsString('[STUDENT_NAME]', $reply);
    }

    /**
     * MDL-INT-016: A reply without content yields the localized no response text.
     */
    public function test_send_to_ai_without_reply_returns_no_response_string(): void {
        $this->mock_ai_service('{}');

        $this->assertSame(get_string('noresponse', 'report_lifestory'), client::send_to_ai(self::create_payload()));
    }

    /**
     * MDL-INT-016: A reply whose content is not a string yields the localized
     * no response text instead of breaking.
     */
    public function test_send_to_ai_with_non_string_reply_returns_no_response_string(): void {
        $this->mock_ai_service(json_encode(['reply' => ['unexpected' => 'shape']]));

        $this->assertSame(get_string('noresponse', 'report_lifestory'), client::send_to_ai(self::create_payload()));
    }

    /**
     * MDL-INT-016: Without a configured license key the request is refused
     * with a Moodle exception before contacting the analysis service.
     */
    public function test_send_to_ai_without_license_key_throws(): void {
        $this->resetAfterTest();
        unset_config('licensekey', 'aiprovider_datacurso');

        $this->expectException(\moodle_exception::class);
        client::send_to_ai(self::create_payload());
    }

    /**
     * MDL-UNIT-018: The report payload carries no site identifier of its own;
     * the provider layer adds it.
     */
    public function test_report_payload_has_no_site_id(): void {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_user();
        $generator->enrol_user($student->id, $course->id, 'student');
        $this->setAdminUser();

        $payload = payload_builder::build($student->id);
        $this->assertArrayNotHasKey('site_id', $payload);
        $this->assertArrayNotHasKey('site_url', $payload);

        $anonymized = payload_anonymizer::anonymize($payload)['payload'];
        $this->assertArrayNotHasKey('site_id', $anonymized);
    }

    /**
     * MDL-UNIT-018: The provider site identifier is a UUID generated once,
     * stable across calls and independent from the site URL.
     */
    public function test_provider_site_uuid_is_random_persistent_and_url_independent(): void {
        global $CFG;

        $this->resetAfterTest();
        unset_config('site_uuid', 'aiprovider_datacurso');

        $first = datacurso_api_base::get_site_uuid();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $first
        );
        $this->assertNotSame(md5($CFG->wwwroot), $first);
        $this->assertSame($first, get_config('aiprovider_datacurso', 'site_uuid'));

        $this->assertSame($first, datacurso_api_base::get_site_uuid());

        $CFG->wwwroot = 'https://renamed.example.com/moodle';
        $this->assertSame($first, datacurso_api_base::get_site_uuid());
    }

    /**
     * MDL-CTR-001: The anonymized payload leaving Moodle carries the fields
     * the analysis service requires, with the student name replaced by the
     * placeholder, pseudonymised identifiers and no site identifier.
     */
    public function test_anonymized_payload_matches_service_contract(): void {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['fullname' => 'Contract course']);
        $student = $generator->create_user(['firstname' => 'Alpha', 'lastname' => 'Alpine']);
        $generator->enrol_user($student->id, $course->id, 'student');
        $item = $generator->create_grade_item(['courseid' => $course->id, 'itemname' => 'Contract item']);
        $generator->create_grade_grade(['itemid' => $item->id, 'userid' => $student->id, 'grade' => 7]);
        $this->setAdminUser();

        $payload = payload_anonymizer::anonymize(payload_builder::build($student->id))['payload'];

        $this->assertSame(['userid', 'student_id', 'student_name', 'courses'], array_keys($payload));
        $this->assertArrayNotHasKey('site_id', $payload);

        $this->assertSame('[STUDENT_NAME]', $payload['student_name']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $payload['userid']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $payload['student_id']);

        $this->assertIsArray($payload['courses']);
        $this->assertCount(1, $payload['courses']);
        $entrykeys = ['name', 'calculated_weight', 'grade', 'range', 'percentage', 'feedback', 'contribution_to_total'];

        foreach ($payload['courses'] as $courseentry) {
            $this->assertSame(['name', 'sections', 'total'], array_keys($courseentry));
            $this->assertIsString($courseentry['name']);
            $this->assertSame($entrykeys, array_keys($courseentry['total']));

            foreach ($courseentry['sections'] as $section) {
                $this->assertSame(['name', 'tasks', 'total'], array_keys($section));
                $this->assertIsString($section['name']);
                $this->assertSame($entrykeys, array_keys($section['total']));
                foreach ($section['tasks'] as $task) {
                    $this->assertSame($entrykeys, array_keys($task));
                    $this->assertIsString($task['name']);
                    $this->assertIsString($task['range']);
                    $this->assertIsString($task['feedback']);
                }
            }
        }

        $this->assertSame('Contract item', $payload['courses'][0]['sections'][0]['tasks'][0]['name']);
        $this->assertSame(7.0, $payload['courses'][0]['sections'][0]['tasks'][0]['grade']);
    }
}

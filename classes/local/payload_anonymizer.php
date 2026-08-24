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

/**
 * Payload anonymizer for report_lifestory AI requests.
 *
 * @package     report_lifestory
 * @copyright   2025 Datacurso
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_lifestory\local;

/**
 * Handles anonymization/de-anonymization for AI payloads.
 */
class payload_anonymizer {
    /**
     * Fields that are anonymized before sending data to AI.
     *
     * @var array<string, string>
     */
    private const ANONYMIZED_FIELDS = [
        'student_name' => '[STUDENT_NAME]',
    ];

    /**
     * Fields whose values are replaced with deterministic pseudonyms.
     *
     * @var string[]
     */
    private const PSEUDONYMIZED_FIELDS = ['userid', 'student_id'];

    /**
     * Anonymize configured payload fields.
     *
     * Masks the student name inside every feedback text, replaces the
     * configured direct fields with placeholders, and swaps identifier
     * fields for deterministic pseudonyms.
     *
     * @param array $payload Original payload.
     * @return array{payload: array, replacements: array<string, string>}
     */
    public static function anonymize(array $payload): array {
        $replacements = [];

        // Mask the student name in feedback texts before the name field is replaced.
        if (isset($payload['student_name']) && is_string($payload['student_name']) && $payload['student_name'] !== ''
                && isset($payload['courses']) && is_array($payload['courses'])) {
            $payload['courses'] = self::mask_student_name_in_feedback($payload['courses'], $payload['student_name']);
        }

        foreach (self::ANONYMIZED_FIELDS as $field => $placeholder) {
            if (isset($payload[$field]) && is_string($payload[$field]) && $payload[$field] !== '') {
                $replacements[$placeholder] = $payload[$field];
                $payload[$field] = $placeholder;
            }
        }

        // Identifier pseudonyms are intentionally kept out of the replacements
        // map: the AI reply never echoes them back.
        foreach (self::PSEUDONYMIZED_FIELDS as $field) {
            if (isset($payload[$field]) && is_string($payload[$field]) && $payload[$field] !== '') {
                $payload[$field] = self::pseudonymize($field, $payload[$field]);
            }
        }

        return [
            'payload' => $payload,
            'replacements' => $replacements,
        ];
    }

    /**
     * Replace occurrences of the student name inside feedback texts.
     *
     * Walks the courses tree recursively and rewrites the value of every
     * key named 'feedback', replacing the full name first and then each
     * individual name word, so no partial fragments are left behind.
     *
     * @param array $courses Courses branch of the payload.
     * @param string $studentname Original student full name.
     * @return array Courses branch with masked feedback texts.
     */
    private static function mask_student_name_in_feedback(array $courses, string $studentname): array {
        $tokens = [$studentname];
        $words = preg_split('/\s+/u', $studentname, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($words as $word) {
            // Short particles such as 'de' or 'la' are too ambiguous to mask.
            if (mb_strlen($word) >= 3) {
                $tokens[] = $word;
            }
        }

        return self::mask_feedback_values($courses, $tokens);
    }

    /**
     * Recursively mask name tokens in every 'feedback' string of a branch.
     *
     * @param array $data Payload branch to walk.
     * @param string[] $tokens Name tokens, full name first.
     * @return array Branch with masked feedback values.
     */
    private static function mask_feedback_values(array $data, array $tokens): array {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = self::mask_feedback_values($value, $tokens);
            } else if ($key === 'feedback' && is_string($value) && $value !== '') {
                $data[$key] = self::mask_tokens($value, $tokens);
            }
        }

        return $data;
    }

    /**
     * Replace every token occurrence in a text with the student placeholder.
     *
     * Matching is case-insensitive and Unicode-safe: lookarounds on letter
     * characters are used instead of word boundaries, which break on
     * accented letters. Adjacent duplicate placeholders produced by
     * overlapping tokens are collapsed into a single one.
     *
     * @param string $text Feedback text.
     * @param string[] $tokens Name tokens, full name first.
     * @return string Masked text.
     */
    private static function mask_tokens(string $text, array $tokens): string {
        $placeholder = self::ANONYMIZED_FIELDS['student_name'];

        foreach ($tokens as $token) {
            $pattern = '/(?<!\p{L})' . preg_quote($token, '/') . '(?!\p{L})/iu';
            $text = preg_replace($pattern, $placeholder, $text) ?? $text;
        }

        $quoted = preg_quote($placeholder, '/');
        $collapsed = preg_replace('/' . $quoted . '(?:\s*' . $quoted . ')+/u', $placeholder, $text);

        return $collapsed ?? $text;
    }

    /**
     * Build a deterministic pseudonym for an identifier field value.
     *
     * The pseudonym is the first 16 hex characters of an HMAC-SHA256 of
     * the field name and value, keyed with the site identifier, so the
     * same identifier always maps to the same pseudonym on a given site.
     *
     * @param string $field Payload field name.
     * @param string $value Original identifier value.
     * @return string 16-character hexadecimal pseudonym.
     */
    private static function pseudonymize(string $field, string $value): string {
        global $CFG;

        return substr(hash_hmac('sha256', $field . ':' . $value, $CFG->siteidentifier), 0, 16);
    }

    /**
     * Restore anonymized placeholders in AI reply text.
     *
     * @param string $text AI reply text.
     * @param array $replacements Placeholder to original value map.
     * @return string
     */
    public static function deanonymize_text(string $text, array $replacements): string {
        if ($text === '' || empty($replacements)) {
            return $text;
        }

        return str_replace(array_keys($replacements), array_values($replacements), $text);
    }
}

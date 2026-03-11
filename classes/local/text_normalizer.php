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
 * Text normalization helper for report_lifestory.
 *
 * @package     report_lifestory
 * @copyright   2025 Datacurso
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_lifestory\local;

/**
 * Handles accent and string normalization.
 */
class text_normalizer {
    /**
     * Mapping of accented and special characters to plain UTF-8 equivalents.
     *
     * @var array
     */
    private static $unwanted = [
        'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U',
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
        'ñ' => 'n', 'Ñ' => 'N',
    ];

    /**
     * Remove accents and special characters while keeping UTF-8.
     *
     * @param string $text Input text.
     * @return string Cleaned text.
     */
    public static function remove_accents(string $text): string {
        return strtr($text, self::$unwanted);
    }

    /**
     * Normalize the payload by iterating over all string values.
     *
     * @param array $payload Input array payload.
     * @return array Normalized array.
     */
    public static function normalize_payload(array $payload): array {
        array_walk_recursive($payload, static function(&$item): void {
            if (is_string($item)) {
                $item = self::remove_accents($item);
            }
        });

        return $payload;
    }
}

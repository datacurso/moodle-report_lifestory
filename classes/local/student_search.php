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
 * Student search helper for report_lifestory.
 *
 * @package     report_lifestory
 * @copyright   2025 Datacurso
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_lifestory\local;

/**
 * Search utilities for student lookup.
 */
class student_search {
    /**
     * Search students by name or email.
     *
     * Results are limited to students enrolled in at least one course where
     * the current user can view grades, as decided by
     * course_access::grade_viewable_courseids().
     *
     * @param string $query Search text.
     * @param int $limit Max number of users to return.
     * @return array[]
     */
    public static function search(string $query, int $limit = 10): array {
        global $DB;

        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $courseids = course_access::grade_viewable_courseids();
        if (empty($courseids)) {
            return [];
        }

        [$insql, $inparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'cid');

        $searchparam = '%' . $DB->sql_like_escape($query) . '%';
        $params = $inparams + [
            'studentrole' => 'student',
            'search1' => $searchparam,
            'search2' => $searchparam,
            'search3' => $searchparam,
            'search4' => $searchparam,
        ];

        $namefields = implode(', ', array_map(static function (string $field): string {
            return 'u.' . $field;
        }, \core_user\fields::for_name()->get_required_fields()));

        $sql = "SELECT DISTINCT u.id, u.email, {$namefields}
                  FROM {user} u
                  JOIN {role_assignments} ra ON ra.userid = u.id
                  JOIN {role} r ON r.id = ra.roleid
                 WHERE r.shortname = :studentrole
                   AND u.deleted = 0
                   AND EXISTS (
                        SELECT 1
                          FROM {user_enrolments} ue
                          JOIN {enrol} e ON e.id = ue.enrolid
                         WHERE ue.userid = u.id
                           AND e.courseid $insql
                   )
                   AND (
                        " . $DB->sql_like('u.firstname', ':search1', false) . " OR
                        " . $DB->sql_like('u.lastname', ':search2', false) . " OR
                        " . $DB->sql_like('u.email', ':search3', false) . " OR
                        " . $DB->sql_like($DB->sql_fullname('u.firstname', 'u.lastname'), ':search4', false) . "
                   )
              ORDER BY u.lastname ASC, u.firstname ASC";

        $students = $DB->get_records_sql($sql, $params, 0, $limit);

        return array_values(array_map(static function ($student): array {
            return [
                'id' => (int)$student->id,
                'fullname' => \fullname($student),
                'email' => $student->email,
            ];
        }, $students));
    }
}

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
 * Course access rules for report_lifestory.
 *
 * @package     report_lifestory
 * @copyright   2026 Datacurso
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_lifestory\local;

/**
 * Decides which courses the viewing user may see student grades for.
 *
 * Mirrors the access rule enforced by the core user grade report
 * (grade/report/user/index.php): access to a course requires the
 * gradereport/user:view capability in the course context, plus either
 * moodle/grade:viewall in the course context or moodle/grade:viewall in
 * the student's user context on a course that shows grades.
 */
class course_access {
    /**
     * Checks whether the current user can view the student's grades in a course.
     *
     * @param int $courseid The course ID.
     * @param int $studentid The ID of the student whose grades are viewed.
     * @return bool True when the current user can view the student's grades.
     */
    public static function can_view_student_grades(int $courseid, int $studentid): bool {
        $coursecontext = \context_course::instance($courseid);

        if (!has_capability('gradereport/user:view', $coursecontext)) {
            return false;
        }

        if (has_capability('moodle/grade:viewall', $coursecontext)) {
            return true;
        }

        $usercontext = \context_user::instance($studentid);

        return has_capability('moodle/grade:viewall', $usercontext) && get_course($courseid)->showgrades;
    }

    /**
     * Filters a list of courses down to those where the student's grades are visible.
     *
     * @param array $courses Course records keyed by id, as returned by enrol_get_users_courses().
     * @param int $studentid The ID of the student whose grades are viewed.
     * @return array The courses passing the access check, keys and order preserved.
     */
    public static function filter_courses(array $courses, int $studentid): array {
        return array_filter($courses, function ($course) use ($studentid) {
            return self::can_view_student_grades($course->id, $studentid);
        });
    }
}

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
 * Plugin strings are defined here.
 *
 * @package     report_lifestory
 * @category    string
 * @copyright   2025 Datacurso
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['activity'] = 'Activity';
$string['altlogo'] = 'Datacurso logo';
$string['clearselection'] = 'Clear selection';
$string['course'] = 'Course';
$string['coursetotal'] = 'Course total';
$string['error_ai_service'] = 'AI service error: {$a}';
$string['error_airequest'] = 'Error communicating with AI service: {$a}';
$string['exportcsv'] = 'Export to CSV';
$string['exportpdf'] = 'Export to PDF';
$string['feedback'] = 'Feedback';
$string['feedbackfromai'] = 'AI feedback';
$string['generatefeedback'] = 'Generate AI feedback';
$string['generatingfeedback'] = 'Generating feedback';
$string['gradepercent'] = 'Grade (%)';
$string['lifestory'] = 'Student life story';
$string['lifestory:generateaifeedback'] = 'Generate AI feedback for students';
$string['lifestory:view'] = 'View life story report';
$string['nofeedbacktopdf'] = 'Generate AI feedback before exporting PDF.';
$string['noreportdata'] = 'No report data available.';
$string['noresponse'] = 'No response received.';
$string['pluginname'] = 'AI Student Life Story';
$string['privacy:metadata:ai_provider'] = 'Data is sent to the Datacurso AI service to generate feedback based on the student’s academic history.';
$string['privacy:metadata:ai_provider:courses'] = 'Structured academic history used for the analysis: course, section and activity names, grades, ranges and percentages, and teacher feedback texts with the student’s name masked by a placeholder.';
$string['privacy:metadata:ai_provider:lang'] = 'The language of the user requesting the analysis, added by the AI provider layer.';
$string['privacy:metadata:ai_provider:siteid'] = 'A persistent randomly generated site identifier (UUID) added by the AI provider layer to distinguish the Moodle site. It is not derived from the site URL or from any personal data.';
$string['privacy:metadata:ai_provider:siteurl'] = 'The web address of the Moodle site, added by the AI provider layer on every request.';
$string['privacy:metadata:ai_provider:studentid'] = 'A pseudonymised identifier (HMAC hash) of the student being analysed. The real user ID is never sent.';
$string['privacy:metadata:ai_provider:studentname'] = 'A generic placeholder sent instead of the student’s real name. The real name never leaves the site and is restored locally when the response is shown.';
$string['privacy:metadata:ai_provider:timezone'] = 'The timezone of the user requesting the analysis, added by the AI provider layer.';
$string['privacy:metadata:ai_provider:userid'] = 'A pseudonymised identifier (HMAC hash) of the user requesting the analysis. The real user ID is never sent.';
$string['privacy:metadata:report_lifestory_feedback'] = 'Stores the latest AI-generated feedback for each student so it can be exported and consulted later.';
$string['privacy:metadata:report_lifestory_feedback:courseid'] = 'The course filter the feedback was generated under (0 means all courses).';
$string['privacy:metadata:report_lifestory_feedback:feedback'] = 'The AI-generated feedback text.';
$string['privacy:metadata:report_lifestory_feedback:studentid'] = 'The student the feedback is about.';
$string['privacy:metadata:report_lifestory_feedback:timecreated'] = 'The time the feedback record was created.';
$string['privacy:metadata:report_lifestory_feedback:timemodified'] = 'The time the feedback record was last modified.';
$string['privacy:metadata:report_lifestory_feedback:usermodified'] = 'The user who generated the feedback.';
$string['range'] = 'Range';
$string['searchusers'] = 'Search users';
$string['section'] = 'Section';
$string['selectuser'] = 'Please select a user to view their life story';
$string['studentlabel'] = 'Student';
$string['total'] = 'Total';
$string['unexpected_ai_error'] = 'Unexpected AI processing error: {$a}';

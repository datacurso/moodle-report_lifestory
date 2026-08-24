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
 * Report index page for lifestory.
 *
 * @package     report_lifestory
 * @copyright   2025 Datacurso
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->dirroot . '/grade/lib.php');
require_once($CFG->dirroot . '/grade/report/lib.php');

use report_lifestory\api\client;
use report_lifestory\event\csv_exported;
use report_lifestory\event\feedback_generated;
use report_lifestory\event\pdf_exported;
use report_lifestory\event\report_viewed;
use report_lifestory\local\course_access;
use report_lifestory\local\csv_exporter;
use report_lifestory\local\feedback_store;
use report_lifestory\local\payload_builder;
use report_lifestory\local\pdf_exporter;
use report_lifestory\local\student_search;
use report_lifestory\local\text_normalizer;

$userid = optional_param('userid', 0, PARAM_INT);
$courseid = optional_param('id', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$searchvalue = optional_param('searchvalue', '', PARAM_TEXT);
$feedbackraw = optional_param('feedbackraw', '', PARAM_RAW);

require_login();

if ($courseid) {
    $context = context_course::instance($courseid);
} else {
    $context = context_system::instance();
}

require_capability('report/lifestory:view', $context);

// Only users holding the student role are valid report targets, matching the search criterion.
if ($userid && !student_search::is_student($userid)) {
    throw new moodle_exception('invaliduser', 'error');
}

// Export CSV.
if ($userid && $action === 'csv') {
    require_sesskey();

    $payload = payload_builder::build($userid);
    $payload = text_normalizer::normalize_payload($payload);
    csv_exported::create_for_student($userid, $context, $courseid)->trigger();
    csv_exporter::export($payload);
    exit;
}

// Page configuration.
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/report/lifestory/index.php', ['userid' => $userid, 'id' => $courseid]));
$PAGE->set_title(get_string('lifestory', 'report_lifestory'));
$PAGE->set_heading(get_string('lifestory', 'report_lifestory'));

$PAGE->requires->js_call_amd('gradereport_user/user', 'init');
$PAGE->requires->js_call_amd('report_lifestory/togglecategories', 'init');
$PAGE->requires->js_call_amd('report_lifestory/button_loader', 'init');
$PAGE->requires->js_call_amd('report_lifestory/user_search', 'init', [
    (new moodle_url('/report/lifestory/index.php'))->out(false),
]);
$PAGE->requires->css(new moodle_url('/report/lifestory/styles/history_student.css'));

// Search students based on search value.
$searchresults = [];
$selecteduser = null;

if ($searchvalue !== '') {
    $searchresults = student_search::search($searchvalue);
}

// Get selected user info.
if ($userid) {
    $namefields = implode(', ', \core_user\fields::for_name()->get_required_fields());
    $selecteduser = $DB->get_record('user', ['id' => $userid], 'id, email, ' . $namefields);
    if ($selecteduser) {
        $selecteduser = [
            'id' => $selecteduser->id,
            'fullname' => fullname($selecteduser),
            'email' => $selecteduser->email,
        ];
    }
}

// Grade history.
$coursesdata = [];

if ($userid) {
    if ($courseid) {
        if (course_access::can_view_student_grades($courseid, $userid)) {
            $coursesdata[] = [
                'id' => $courseid,
                'fullname' => get_course($courseid)->fullname,
                'reporthtml' => report_lifestory_get_report_html($courseid, $userid),
            ];
        }
    } else {
        $courses = course_access::filter_courses(enrol_get_users_courses($userid), $userid);
        foreach ($courses as $course) {
            $coursesdata[] = [
                'id' => $course->id,
                'fullname' => $course->fullname,
                'reporthtml' => report_lifestory_get_report_html($course->id, $userid),
            ];
        }
    }
}

if ($userid && $action === '') {
    report_viewed::create_for_student($userid, $context, $courseid)->trigger();
}

// AI Feedback.
$feedbackhtml = null;
$feedbackdate = null;
$hasstoredfeedback = false;

if ($userid && $action === 'feedback') {
    require_sesskey();
    require_capability('report/lifestory:generateaifeedback', $context);

    try {
        $payload = payload_builder::build($userid);
        $response = client::send_to_ai($payload);

        $replytext = '';

        if (is_string($response)) {
            $decoded = json_decode($response, true);
            if (json_last_error() === JSON_ERROR_NONE && isset($decoded['reply'])) {
                $replytext = $decoded['reply'];
            } else {
                $replytext = $response;
            }
        } else if (is_array($response) && isset($response['reply'])) {
            $replytext = $response['reply'];
        } else {
            $replytext = get_string('noresponse', 'report_lifestory');
        }

        $feedbackraw = $replytext;
        $feedbackid = feedback_store::save($userid, $courseid, $replytext);
        feedback_generated::create_for_student($userid, $context, $courseid, $feedbackid)->trigger();
        $feedbackhtml = html_writer::div(
            format_text($replytext, FORMAT_MARKDOWN),
            'report_lifestory-feedbackcontent bg-light p-3 rounded'
        );
        $feedbackdate = userdate(time());
        $hasstoredfeedback = true;

        $cleanurl = (new moodle_url('/report/lifestory/index.php', ['userid' => $userid, 'id' => $courseid]))->out(false);
        $replacehistoryjs = "if (window.history && window.history.replaceState) {"
            . " window.history.replaceState(null, document.title, '" . $cleanurl . "');"
            . ' }';
        $PAGE->requires->js_init_code($replacehistoryjs);
    } catch (\moodle_exception $e) {
        debugging(get_string('error_ai_service', 'report_lifestory', $e->getMessage()), DEBUG_DEVELOPER);

        \core\notification::add(
            get_string('error_airequest', 'report_lifestory', $e->getMessage()),
            \core\output\notification::NOTIFY_ERROR
        );
    } catch (\Throwable $e) {
        debugging(get_string('unexpected_ai_error', 'report_lifestory', $e->getMessage()), DEBUG_DEVELOPER);

        \core\notification::add(
            get_string('error_airequest', 'report_lifestory', $e->getMessage()),
            \core\output\notification::NOTIFY_ERROR
        );
    }
}

// Stored feedback: show the persisted AI feedback when no fresh generation happened.
if ($userid && $feedbackhtml === null) {
    $storedrecord = feedback_store::get_record($userid, $courseid);
    if ($storedrecord) {
        $feedbackhtml = html_writer::div(
            format_text($storedrecord->feedback, FORMAT_MARKDOWN),
            'report_lifestory-feedbackcontent bg-light p-3 rounded'
        );
        $feedbackraw = $storedrecord->feedback;
        $feedbackdate = userdate($storedrecord->timemodified);
        $hasstoredfeedback = true;
    }
}

if ($userid && $action === 'pdf') {
    require_sesskey();

    $storedfeedback = feedback_store::get($userid, $courseid);

    if ($storedfeedback === null || $storedfeedback === '') {
        \core\notification::add(
            get_string('nofeedbacktopdf', 'report_lifestory'),
            \core\output\notification::NOTIFY_WARNING
        );
        redirect(new moodle_url('/report/lifestory/index.php', ['userid' => $userid, 'id' => $courseid]));
    }

    $studentname = $selecteduser['fullname'] ?? (string)$userid;
    pdf_exported::create_for_student($userid, $context, $courseid)->trigger();
    pdf_exporter::download($studentname, $storedfeedback, $coursesdata, $userid);
}

echo $OUTPUT->header();

// Render Mustache.
$renderer = $PAGE->get_renderer('core');
$headerlogo = new \report_lifestory\output\header_logo();
$logocontext = $headerlogo->export_for_template($renderer);
$cangeneratefeedback = has_capability('report/lifestory:generateaifeedback', $context);

$templatecontext = [
    'baseurl' => new moodle_url('/report/lifestory/index.php'),
    'userid' => $userid,
    'courseid' => $courseid,
    'searchvalue' => $searchvalue,
    'searchresults' => $searchresults,
    'selecteduser' => $selecteduser,
    'hasuser' => (bool)$userid,
    'courses' => $coursesdata,
    'feedback' => $feedbackhtml,
    'feedbackraw' => $feedbackraw,
    'showfeedback' => !empty($feedbackhtml),
    'feedbackdateline' => $feedbackdate !== null
        ? get_string('feedbackgeneratedon', 'report_lifestory', $feedbackdate)
        : null,
    'hasstoredfeedback' => $hasstoredfeedback,
    'canexportpdf' => $userid && !empty($feedbackhtml),
    'headerlogo' => $logocontext,
    'sesskey' => sesskey(),
    'cangeneratefeedback' => $cangeneratefeedback,
    'alttext' => get_string('altlogo', 'report_lifestory'),
];

echo $OUTPUT->render_from_template('report_lifestory/history_student', $templatecontext);
echo $OUTPUT->footer();

/**
 * Generates the grade report HTML for a given course and user.
 *
 * @param int $courseid The course ID.
 * @param int $userid The user ID.
 * @return string Rendered HTML for the grade report.
 */
function report_lifestory_get_report_html($courseid, $userid) {
    global $OUTPUT;

    $coursecontext = context_course::instance($courseid);
    $gpr = new grade_plugin_return([
        'type' => 'report',
        'plugin' => 'lifestory',
        'courseid' => $courseid,
        'userid' => $userid,
    ]);

    $report = new \gradereport_user\report\user($courseid, $gpr, $coursecontext, $userid);
    $report->showcontributiontocoursetotal = true;
    $report->process_data([]);
    $report->setup_table();

    if ($report->fill_table()) {
        return $report->print_table(true);
    }

    return $OUTPUT->notification(get_string('noreportdata', 'report_lifestory'), 'warning');
}

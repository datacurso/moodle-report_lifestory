<?php
// This file is part of Moodle - http://moodle.org/
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

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

use Behat\Mink\Exception\ExpectationException;

/**
 * Step definitions for report_lifestory behat tests.
 *
 * @package    report_lifestory
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_report_lifestory extends behat_base {
    /**
     * Opens the life story report page for the given user.
     *
     * This navigates directly to /report/lifestory/index.php?userid=N, which is
     * deterministic regardless of the ids assigned to generated users.
     *
     * @Given /^I view the life story report for user "(?P<username>[^"]*)"$/
     *
     * @param string $username The username of the user whose report is viewed.
     * @return void
     */
    public function i_view_the_life_story_report_for_user(string $username): void {
        global $DB;

        $userid = $DB->get_field('user', 'id', ['username' => $username], MUST_EXIST);

        $url = new moodle_url('/report/lifestory/index.php', ['userid' => $userid]);

        $this->execute('behat_general::i_visit', [$url->out_as_local_url(false)]);
    }

    /**
     * Opens the life story report page for the given user filtered by a course.
     *
     * This navigates directly to /report/lifestory/index.php?userid=N&id=C,
     * which is deterministic regardless of the ids assigned to generated
     * users and courses.
     *
     * @Given /^I view the life story report for user "(?P<username>[^"]*)" in course "(?P<shortname>[^"]*)"$/
     *
     * @param string $username The username of the user whose report is viewed.
     * @param string $shortname The shortname of the course the report is filtered by.
     * @return void
     */
    public function i_view_the_life_story_report_for_user_in_course(string $username, string $shortname): void {
        global $DB;

        $userid = $DB->get_field('user', 'id', ['username' => $username], MUST_EXIST);
        $courseid = $DB->get_field('course', 'id', ['shortname' => $shortname], MUST_EXIST);

        $url = new moodle_url('/report/lifestory/index.php', ['userid' => $userid, 'id' => $courseid]);

        $this->execute('behat_general::i_visit', [$url->out_as_local_url(false)]);
    }

    /**
     * Inserts a stored AI feedback record for the given user directly into the database.
     *
     * No generator exists for the report_lifestory_feedback table, so the record
     * is written directly, mirroring the row produced by a successful AI
     * generation for the all-courses filter.
     *
     * @Given /^stored life story feedback "(?P<text>[^"]*)" exists for user "(?P<username>[^"]*)"$/
     *
     * @param string $text The feedback text to store.
     * @param string $username The username of the student the feedback is about.
     * @return void
     */
    public function stored_life_story_feedback_exists_for_user(string $text, string $username): void {
        global $DB;

        $userid = $DB->get_field('user', 'id', ['username' => $username], MUST_EXIST);
        $now = time();

        $DB->insert_record('report_lifestory_feedback', (object) [
            'studentid' => $userid,
            'courseid' => 0,
            'feedback' => $text,
            'usermodified' => 2,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * Runs a life story student search without JavaScript.
     *
     * The search form is normally driven by the autocomplete module, so this
     * step requests the report index page directly with the searchvalue
     * parameter, exercising the server-side search and the static results
     * list rendered by the page template.
     *
     * @When /^I search the life story report for "(?P<searchvalue>[^"]*)"$/
     *
     * @param string $searchvalue The text to search for.
     * @return void
     */
    public function i_search_the_life_story_report_for(string $searchvalue): void {
        $url = new moodle_url('/report/lifestory/index.php', ['searchvalue' => $searchvalue]);

        $this->execute('behat_general::i_visit', [$url->out_as_local_url(false)]);
    }

    /**
     * Requests a life story action with an invalid sesskey and expects rejection.
     *
     * The request is sent with the current session cookies but a deliberately
     * invalid sesskey parameter, simulating a cross-site request forgery
     * attempt. The page must show the standard invalid sesskey error.
     *
     * The resulting error page contains the fatal error marker that Moodle's
     * after-step exception detector would report as a failure, so this step
     * performs the visit and the assertion itself and then navigates away to a
     * clean page before it finishes.
     *
     * @Then /^life story "(?P<action>csv|feedback)" action for "(?P<username>[^"]*)" without a valid sesskey should be rejected$/
     *
     * @param string $action The report action to request ('csv' or 'feedback').
     * @param string $username The username of the target user of the action.
     * @return void
     * @throws ExpectationException If the invalid sesskey error is not shown.
     */
    public function requesting_the_life_story_action_without_a_valid_sesskey_should_be_rejected(
        string $action,
        string $username
    ): void {
        global $DB;

        $userid = $DB->get_field('user', 'id', ['username' => $username], MUST_EXIST);

        $url = new moodle_url('/report/lifestory/index.php', [
            'userid' => $userid,
            'action' => $action,
            'sesskey' => 'invalidsesskey',
        ]);

        $this->getSession()->visit($this->locate_path($url->out_as_local_url(false)));

        $expectederror = get_string('invalidsesskey', 'error');
        $pagetext = $this->getSession()->getPage()->getText();

        if (strpos($pagetext, $expectederror) === false) {
            throw new ExpectationException(
                'The "' . $action . '" action was not rejected: the invalid sesskey error message was not shown.',
                $this->getSession()
            );
        }

        // Leave the error page so the after-step exception detector sees a normal page.
        $this->getSession()->visit($this->locate_path('/'));
    }

    /**
     * Requests the AI feedback action with a valid sesskey and expects a capability denial.
     *
     * The current session sesskey is extracted from the report index page, so the
     * request passes the session key check and reaches the server-side capability
     * check. The page must show the standard missing permissions error for the
     * generate AI feedback capability.
     *
     * The resulting error page contains the fatal error marker that Moodle's
     * after-step exception detector would report as a failure, so this step
     * performs the visit and the assertion itself and then navigates away to a
     * clean page before it finishes.
     *
     * @Then /^life story AI feedback action for "(?P<username>[^"]*)" with a valid sesskey should be denied by missing capability$/
     *
     * @param string $username The username of the target user of the action.
     * @return void
     * @throws ExpectationException If the sesskey cannot be extracted or the permissions error is not shown.
     */
    public function requesting_the_life_story_ai_feedback_action_should_be_denied_by_missing_capability(
        string $username
    ): void {
        $this->request_action_with_current_sesskey('feedback', $username);

        $expectederror = get_string(
            'nopermissions',
            'error',
            get_capability_string('report/lifestory:generateaifeedback')
        );
        $pagetext = $this->getSession()->getPage()->getText();

        if (strpos($pagetext, $expectederror) === false) {
            throw new ExpectationException(
                'The AI feedback action was not denied: the missing permissions error message was not shown.',
                $this->getSession()
            );
        }

        // Leave the error page so the after-step exception detector sees a normal page.
        $this->getSession()->visit($this->locate_path('/'));
    }

    /**
     * Requests the AI feedback action with a valid sesskey and expects to pass the permission gate.
     *
     * The current user holds the generate AI feedback capability, so the request
     * must get past the session key and capability checks and reach the AI
     * client. On the test site the AI provider is not configured, so the page
     * must show the AI communication error notification instead of any access
     * error, proving the server-side gate was crossed.
     *
     * The resulting page contains debugging output that Moodle's after-step
     * exception detector would report as a failure, so this step performs the
     * visit and the assertions itself and then navigates away to a clean page
     * before it finishes.
     *
     * @Then /^life story AI feedback action for "(?P<username>[^"]*)" with a valid sesskey should pass the permission gate$/
     *
     * @param string $username The username of the target user of the action.
     * @return void
     * @throws ExpectationException If an access error is shown or the AI client is never reached.
     */
    public function requesting_the_life_story_ai_feedback_action_should_pass_the_permission_gate(
        string $username
    ): void {
        $this->request_action_with_current_sesskey('feedback', $username);

        $pagetext = $this->getSession()->getPage()->getText();

        $permissionserror = get_string(
            'nopermissions',
            'error',
            get_capability_string('report/lifestory:generateaifeedback')
        );
        if (strpos($pagetext, $permissionserror) !== false) {
            throw new ExpectationException(
                'The AI feedback action was denied by the capability check for a user holding the capability.',
                $this->getSession()
            );
        }

        if (strpos($pagetext, get_string('invalidsesskey', 'error')) !== false) {
            throw new ExpectationException(
                'The AI feedback action was rejected by the sesskey check despite using the session sesskey.',
                $this->getSession()
            );
        }

        // The AI provider is not configured on the test site, so reaching the client
        // surfaces the communication error notification on the report page.
        $ainotification = trim(get_string('error_airequest', 'report_lifestory', ''));
        if (strpos($pagetext, $ainotification) === false) {
            throw new ExpectationException(
                'The AI communication error notification was not shown: the request may not have reached the AI client.',
                $this->getSession()
            );
        }

        // Leave the page so the after-step exception detector sees a normal page.
        $this->getSession()->visit($this->locate_path('/'));
    }

    /**
     * Expects the report page to reject a target user that is not a student.
     *
     * The report index page is visited directly with the userid of the given
     * user. The server-side student validation must reject the request with
     * the standard invalid user error.
     *
     * The resulting error page contains the fatal error marker that Moodle's
     * after-step exception detector would report as a failure, so this step
     * performs the visit and the assertion itself and then navigates away to a
     * clean page before it finishes.
     *
     * @Then /^viewing the life story report for user "(?P<username>[^"]*)" should be rejected as an invalid selection$/
     *
     * @param string $username The username of the non-student target user.
     * @return void
     * @throws ExpectationException If the invalid user error is not shown.
     */
    public function viewing_the_life_story_report_should_be_rejected_as_an_invalid_selection(string $username): void {
        global $DB;

        $userid = $DB->get_field('user', 'id', ['username' => $username], MUST_EXIST);

        $url = new moodle_url('/report/lifestory/index.php', ['userid' => $userid]);

        $this->getSession()->visit($this->locate_path($url->out_as_local_url(false)));

        $expectederror = get_string('invaliduser', 'error');
        $pagetext = $this->getSession()->getPage()->getText();

        if (strpos($pagetext, $expectederror) === false) {
            throw new ExpectationException(
                'Viewing the report for "' . $username . '" was not rejected: the invalid user error was not shown.',
                $this->getSession()
            );
        }

        // Leave the error page so the after-step exception detector sees a normal page.
        $this->getSession()->visit($this->locate_path('/'));
    }

    /**
     * Expects a forced report action on a non-student target to be rejected.
     *
     * The action is requested with the real session sesskey, so it passes the
     * session key check and reaches the server-side student validation, which
     * must reject the request with the standard invalid user error.
     *
     * The resulting error page contains the fatal error marker that Moodle's
     * after-step exception detector would report as a failure, so this step
     * performs the visit and the assertion itself and then navigates away to a
     * clean page before it finishes.
     *
     * @Then /^life story "(?P<action>csv|feedback)" action for "(?P<username>[^"]*)" with a valid sesskey is rejected as invalid$/
     *
     * @param string $action The report action to request ('csv' or 'feedback').
     * @param string $username The username of the non-student target user.
     * @return void
     * @throws ExpectationException If the sesskey cannot be extracted or the invalid user error is not shown.
     */
    public function requesting_the_life_story_action_should_be_rejected_as_an_invalid_selection(
        string $action,
        string $username
    ): void {
        $this->request_action_with_current_sesskey($action, $username);

        $expectederror = get_string('invaliduser', 'error');
        $pagetext = $this->getSession()->getPage()->getText();

        if (strpos($pagetext, $expectederror) === false) {
            throw new ExpectationException(
                'The "' . $action . '" action for "' . $username . '" was not rejected: '
                    . 'the invalid user error was not shown.',
                $this->getSession()
            );
        }

        // Leave the error page so the after-step exception detector sees a normal page.
        $this->getSession()->visit($this->locate_path('/'));
    }

    /**
     * Expects the PDF export of a student without stored feedback to show the missing feedback notice.
     *
     * The PDF action is requested with the real session sesskey, so it passes
     * the session key check and reaches the server-side feedback store lookup.
     * No AI feedback has been stored for the student, so the page must redirect
     * back to the report index and show the missing feedback notification,
     * proving the export ignores any browser-supplied feedback text.
     *
     * @Then /^life story "pdf" action for "(?P<username>[^"]*)" with a valid sesskey shows the missing feedback notice$/
     *
     * @param string $username The username of the target student of the export.
     * @return void
     * @throws ExpectationException If the sesskey cannot be extracted or the notice is not shown.
     */
    public function requesting_the_life_story_pdf_action_shows_the_missing_feedback_notice(string $username): void {
        $this->request_action_with_current_sesskey('pdf', $username);

        $expectednotice = get_string('nofeedbacktopdf', 'report_lifestory');
        $pagetext = $this->getSession()->getPage()->getText();

        if (strpos($pagetext, $expectednotice) === false) {
            throw new ExpectationException(
                'The PDF export without stored feedback did not show the missing feedback notice.',
                $this->getSession()
            );
        }

        // Leave the page so the after-step exception detector sees a normal page.
        $this->getSession()->visit($this->locate_path('/'));
    }

    /**
     * Loads the report index page, extracts the current session sesskey and
     * requests the given report action for the given target user with it.
     *
     * @param string $action The report action to request (for example 'csv', 'feedback' or 'pdf').
     * @param string $username The username of the target user of the action.
     * @return void
     * @throws ExpectationException If the sesskey cannot be extracted.
     */
    private function request_action_with_current_sesskey(string $action, string $username): void {
        global $DB;

        $userid = $DB->get_field('user', 'id', ['username' => $username], MUST_EXIST);

        // Load the report index page (the user has view permission) to read the real sesskey.
        $this->getSession()->visit($this->locate_path('/report/lifestory/index.php'));

        $content = $this->getSession()->getPage()->getContent();

        if (!preg_match('/"sesskey":"([a-zA-Z0-9]+)"/', $content, $matches)) {
            throw new ExpectationException(
                'Could not extract the current session sesskey from the report index page.',
                $this->getSession()
            );
        }

        $url = new moodle_url('/report/lifestory/index.php', [
            'userid' => $userid,
            'action' => $action,
            'sesskey' => $matches[1],
        ]);

        $this->getSession()->visit($this->locate_path($url->out_as_local_url(false)));
    }
}

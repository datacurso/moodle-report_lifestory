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
     * @Then /^requesting the life story "(?P<action>csv|feedback)" action without a valid sesskey should be rejected$/
     *
     * @param string $action The report action to request ('csv' or 'feedback').
     * @return void
     * @throws ExpectationException If the invalid sesskey error is not shown.
     */
    public function requesting_the_life_story_action_without_a_valid_sesskey_should_be_rejected(string $action): void {
        $url = new moodle_url('/report/lifestory/index.php', [
            'userid' => 2,
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
     * @Then /^requesting the life story AI feedback action with a valid sesskey should be denied by missing capability$/
     *
     * @return void
     * @throws ExpectationException If the sesskey cannot be extracted or the permissions error is not shown.
     */
    public function requesting_the_life_story_ai_feedback_action_should_be_denied_by_missing_capability(): void {
        $this->request_feedback_action_with_current_sesskey();

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
     * @Then /^requesting the life story AI feedback action with a valid sesskey should pass the permission gate$/
     *
     * @return void
     * @throws ExpectationException If an access error is shown or the AI client is never reached.
     */
    public function requesting_the_life_story_ai_feedback_action_should_pass_the_permission_gate(): void {
        $this->request_feedback_action_with_current_sesskey();

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
     * Loads the report index page, extracts the current session sesskey and
     * requests the AI feedback action with it.
     *
     * @return void
     * @throws ExpectationException If the sesskey cannot be extracted.
     */
    private function request_feedback_action_with_current_sesskey(): void {
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
            'userid' => 2,
            'action' => 'feedback',
            'sesskey' => $matches[1],
        ]);

        $this->getSession()->visit($this->locate_path($url->out_as_local_url(false)));
    }
}

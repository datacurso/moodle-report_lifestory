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
}

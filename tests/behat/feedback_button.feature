@report @report_lifestory @javascript
Feature: The generate AI feedback button shows a loading state that blocks double submissions
  In order to avoid consuming AI credits twice
  As a manager
  I need the generate button to disable itself and show progress once clicked

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | manager1 | Max       | Manager  | manager1@example.com |
      | student1 | Sam       | Student  | student1@example.com |
    And the following "courses" exist:
      | fullname   | shortname |
      | Course One | C1        |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
    And the following "system role assigns" exist:
      | user     | role    |
      | manager1 | manager |

  @MDL-E2E-007
  Scenario: Clicking the generate button disables it, shows the loading text and ignores further clicks
    # The request itself ends in the AI communication error on the test site
    # (no provider configured), so the click is performed without following the
    # link to keep the loading state on screen.
    Given I log in as "manager1"
    And I view the life story report for user "student1"
    And I should see "Generate AI feedback"
    When I click the life story generate feedback button without following its link
    Then I should see "Generating feedback"
    And I should not see "Generate AI feedback"
    And "#btn-feedback-ai[aria-disabled='true']" "css_element" should exist
    And "#btn-feedback-ai.report_lifestory-btnloading" "css_element" should exist
    And I click the life story generate feedback button without following its link
    And I should see "Generating feedback"
    And I should see "Course One"

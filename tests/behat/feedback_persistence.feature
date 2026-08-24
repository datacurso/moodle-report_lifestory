@report @report_lifestory
Feature: Stored AI feedback is shown when revisiting a student
  In order to consult previously generated analyses without extra AI calls
  As a manager
  I need the report to display the stored feedback with its generation date

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

  Scenario: Revisiting a student with stored feedback shows it without any AI call
    Given stored life story feedback "Persisted analysis marker text" exists for user "student1"
    And I log in as "manager1"
    When I view the life story report for user "student1"
    Then I should see "Persisted analysis marker text"
    And I should see "Feedback generated on"
    And I should see "Regenerate AI feedback"
    And I should see "Export to PDF"

  Scenario: Visiting a student without stored feedback offers the initial generation action
    Given I log in as "manager1"
    When I view the life story report for user "student1"
    Then I should see "Generate AI feedback"
    And I should not see "Regenerate AI feedback"
    And I should not see "Feedback generated on"
    And I should not see "Export to PDF"

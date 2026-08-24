@report @report_lifestory
Feature: The life story report only accepts students as target users
  In order to keep the report scoped to student data
  As a manager
  I need the report to reject any target user that does not hold the student role

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | manager1 | Max       | Manager  | manager1@example.com |
      | teacher1 | Tina      | Teacher  | teacher1@example.com |
      | student1 | Sam       | Student  | student1@example.com |
    And the following "courses" exist:
      | fullname   | shortname |
      | Course One | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | student1 | C1     | student        |
      | teacher1 | C1     | editingteacher |
    And the following "system role assigns" exist:
      | user     | role    |
      | manager1 | manager |

  Scenario: Viewing the report for a non-student user is rejected
    Given I log in as "manager1"
    Then viewing the life story report for user "teacher1" should be rejected as an invalid selection

  Scenario: Forcing the CSV export action on a non-student user is rejected despite a valid session key
    Given I log in as "manager1"
    Then requesting the life story "csv" action for user "teacher1" with a valid sesskey should be rejected as an invalid selection

  Scenario: Forcing the AI feedback action on a non-student user is rejected despite a valid session key
    Given I log in as "manager1"
    Then requesting the life story "feedback" action for user "teacher1" with a valid sesskey should be rejected as an invalid selection

  Scenario: Viewing the report for a student user keeps working
    Given I log in as "manager1"
    When I view the life story report for user "student1"
    Then I should see "Course One"
    And I should see "Export to CSV"

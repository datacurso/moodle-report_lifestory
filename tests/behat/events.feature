@report @report_lifestory
Feature: Life story report actions leave an audit trail in the Moodle logs
  In order to audit access to student grade histories
  As an administrator
  I need every view and export to be logged while failed generations leave no generation event

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

  @MDL-INT-023
  Scenario: Viewing a student and exporting the CSV are logged while a failed generation is not
    # A successful generation cannot happen on the test site (no AI provider is
    # configured), so only the absence of the generated event is asserted here.
    Given I log in as "manager1"
    And I view the life story report for user "student1"
    And following "Export to CSV" should download between "1" and "500000" bytes
    And life story AI feedback action for "student1" with a valid sesskey should pass the permission gate
    And I log out
    And I log in as "admin"
    When I navigate to "Reports > Logs" in site administration
    And I press "Get these logs"
    Then I should see "Life story report viewed"
    And I should see "Life story CSV exported"
    And I should see "Sam Student"
    And I should not see "Life story AI feedback generated"
    And I should not see "Life story PDF exported"

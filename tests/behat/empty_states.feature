@report @report_lifestory
Feature: The life story report degrades gracefully for empty target states
  In order to get clear guidance instead of errors or pointless actions
  As a manager
  I need the report to handle nonexistent users and students without course enrolments

  Background:
    Given the following "users" exist:
      | username | firstname | lastname   | email                |
      | manager1 | Max       | Manager    | manager1@example.com |
      | student1 | Sam       | Student    | student1@example.com |
      | student2 | Cora      | Courseless | student2@example.com |
    And the following "courses" exist:
      | fullname   | shortname |
      | Course One | C1        |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
    And the following "system role assigns" exist:
      | user     | role    |
      | manager1 | manager |
      | student2 | student |

  @MDL-INT-009 @MDL-E2E-001
  Scenario: A nonexistent user id behaves as no selection
    Given I log in as "manager1"
    When I view the life story report for a nonexistent user
    Then I should see "Please select a user to view their life story"
    And I should not see "Export to CSV"
    And I should not see "Generate AI feedback"
    And ".user-grade" "css_element" should not exist

  @MDL-E2E-001
  Scenario: The initial state only shows the search box and the guidance message
    Given I log in as "manager1"
    When I visit "/report/lifestory/index.php"
    Then "Search users" "field" should exist
    And "Search" "button" should exist
    And I should see "Please select a user to view their life story"
    And I should not see "Export to CSV"
    And I should not see "Generate AI feedback"
    And I should not see "Export to PDF"
    And ".report_lifestory-logo" "css_element" should not exist
    And ".user-grade" "css_element" should not exist

  @MDL-E2E-001
  Scenario: Selecting a student shows the provider logo linking to Datacurso in a new tab
    Given I log in as "manager1"
    When I view the life story report for user "student1"
    Then "a.report_lifestory-logo[href='https://datacurso.com/'][target='_blank']" "css_element" should exist
    And I should see "Export to CSV"
    And I should see "Generate AI feedback"

  @MDL-INT-010
  Scenario: A student without course enrolments shows a clear notice
    Given I log in as "manager1"
    When I view the life story report for user "student2"
    Then I should see "This student has no course enrolments available to display in this report."
    And I should not see "Export to CSV"
    And I should not see "Generate AI feedback"

  @MDL-INT-010
  Scenario: Forcing feedback generation for a student without courses is stopped
    Given I log in as "manager1"
    Then life story "feedback" action for "student2" with a valid sesskey shows no courses notice

  @MDL-INT-010
  Scenario: Forcing the CSV export for a student without courses is stopped
    Given I log in as "manager1"
    Then life story "csv" action for "student2" with a valid sesskey shows no courses notice

  @MDL-INT-010
  Scenario: Forcing the PDF export for a student without courses is stopped before any download
    # The PDF action checks the stored feedback before the course list, so a
    # student without courses (and therefore without feedback) gets the missing
    # feedback notice instead of the no courses notice; nothing is downloaded.
    Given I log in as "manager1"
    Then life story "pdf" action for "student2" with a valid sesskey shows the missing feedback notice
    And I view the life story report for user "student2"
    And I should see "This student has no course enrolments available to display in this report."
    And I should not see "Export to PDF"

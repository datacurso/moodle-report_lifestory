@report @report_lifestory
Feature: Life story report actions are protected by the session key
  In order to prevent cross-site request forgery attacks
  As a manager
  I need the life story report actions to require a valid session key

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

  @MDL-INT-015 @MDL-E2E-009
  Scenario: Action links carry the user session key and the CSV export works for a legitimate user
    Given I log in as "manager1"
    When I view the life story report for user "student1"
    Then I should see "Generate AI feedback"
    And I should see "Export to CSV"
    And "//a[@id='btn-feedback-ai'][contains(@href, 'sesskey=')]" "xpath_element" should exist
    And "//a[@id='btn-csv-export'][contains(@href, 'sesskey=')]" "xpath_element" should exist
    And following "Export to CSV" should download between "1" and "500000" bytes

  @MDL-INT-015
  Scenario: AI feedback generation is rejected without a valid session key
    Given I log in as "manager1"
    Then life story "feedback" action for "student1" without a valid sesskey should be rejected

  @MDL-INT-015
  Scenario: CSV export is rejected without a valid session key
    Given I log in as "manager1"
    Then life story "csv" action for "student1" without a valid sesskey should be rejected

  @MDL-INT-019
  Scenario: PDF export is rejected without a valid session key even when stored feedback exists
    Given stored life story feedback "Stored feedback for the PDF" exists for user "student1"
    And I log in as "manager1"
    And I view the life story report for user "student1"
    And "//form[input[@name='action'][@value='pdf']]/input[@name='sesskey']" "xpath_element" should exist
    Then life story "pdf" action for "student1" without a valid sesskey should be rejected

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
    And the following "system role assigns" exist:
      | user     | role    |
      | manager1 | manager |

  Scenario: Action links carry the user session key and the CSV export works for a legitimate user
    Given I log in as "manager1"
    When I view the life story report for user "student1"
    Then I should see "Generate AI feedback"
    And I should see "Export to CSV"
    And following "Export to CSV" should download between "1" and "500000" bytes

  Scenario: AI feedback generation is rejected without a valid session key
    Given I log in as "manager1"
    Then requesting the life story "feedback" action without a valid sesskey should be rejected

  Scenario: CSV export is rejected without a valid session key
    Given I log in as "manager1"
    Then requesting the life story "csv" action without a valid sesskey should be rejected

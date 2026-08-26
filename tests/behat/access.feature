@report @report_lifestory
Feature: Access to the life story report requires a session and the view capability
  In order to keep student grade histories private
  As an administrator
  I need the report to be reachable only by authenticated users holding the view capability

  Background:
    Given the following "users" exist:
      | username      | firstname | lastname | email                     |
      | manager1      | Max       | Manager  | manager1@example.com      |
      | plainuser1    | Paula     | Plain    | plainuser1@example.com    |
      | courseviewer1 | Carla     | Course   | courseviewer1@example.com |
      | student1      | Sam       | Student  | student1@example.com      |
    And the following "courses" exist:
      | fullname   | shortname |
      | Course One | C1        |
    And the following "course enrolments" exist:
      | user          | course | role           |
      | student1      | C1     | student        |
      | courseviewer1 | C1     | editingteacher |
    And the following "roles" exist:
      | shortname  | name              |
      | lifeviewer | Life story viewer |
    And the following "role capabilities" exist:
      | role       | report/lifestory:view |
      | lifeviewer | allow                 |
    And the following "role assigns" exist:
      | user          | role       | contextlevel | reference |
      | courseviewer1 | lifeviewer | Course       | C1        |
    And the following "system role assigns" exist:
      | user     | role    |
      | manager1 | manager |

  @MDL-INT-001
  Scenario: An anonymous visitor is sent to the login page
    When I visit "/report/lifestory/index.php"
    Then "#login" "css_element" should exist
    And I should not see "Please select a user to view their life story"

  @MDL-INT-001
  Scenario: An authenticated user without the view capability is rejected with the permissions error
    Given I log in as "plainuser1"
    Then viewing the life story report should be denied by missing view capability

  @MDL-INT-001 @MDL-INT-002
  Scenario: A manager reaches the report from the reports section of the site administration
    Given I log in as "manager1"
    When I navigate to "Reports > AI Student Life Story > Student life story" in site administration
    Then I should see "Student life story" in the "page-header" "region"
    And I should see "Please select a user to view their life story"

  @MDL-INT-001
  Scenario: The view capability granted only in a course opens the filtered report but not the site wide report
    Given I log in as "courseviewer1"
    When I visit the life story report filtered by course "C1"
    Then I should see "Student life story" in the "page-header" "region"
    And I should see "Please select a user to view their life story"
    And viewing the life story report should be denied by missing view capability

  @MDL-INT-002
  Scenario: The report is not exposed from the user profile
    Given I log in as "manager1"
    When I follow "Profile" in the user menu
    Then I should see "Max Manager"
    And I should not see "Student life story"

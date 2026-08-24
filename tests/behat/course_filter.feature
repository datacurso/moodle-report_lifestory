@report @report_lifestory
Feature: The life story report can be reached from a course and filtered by it
  In order to review a student's grades in a single course
  As a manager
  I need to open the report from the course navigation and see only that course

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | manager1 | Max       | Manager  | manager1@example.com |
      | student1 | Sam       | Student  | student1@example.com |
    And the following "courses" exist:
      | fullname   | shortname |
      | Course One | C1        |
      | Course Two | C2        |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
      | student1 | C2     | student |
    And the following "system role assigns" exist:
      | user     | role    |
      | manager1 | manager |

  Scenario: The report is reachable from the course navigation
    Given I log in as "manager1"
    And I am on the "C1" "Course" page
    When I navigate to "Reports > Student life story" in current page administration
    Then I should see "Student life story" in the "page-header" "region"

  Scenario: Filtering by a course only shows that course and keeps the filter in the CSV export
    Given I log in as "manager1"
    When I view the life story report for user "student1" in course "C1"
    Then I should see "Course One"
    And I should not see "Course Two"
    And following "Export to CSV" should download between "1" and "500000" bytes

  Scenario: Without a course filter every course of the student is shown
    Given I log in as "manager1"
    When I view the life story report for user "student1"
    Then I should see "Course One"
    And I should see "Course Two"

@report @report_lifestory
Feature: The life story report only lists courses where the viewer can see the student's grades
  In order to protect student grade privacy
  As an administrator
  I need the report to hide courses where the viewing user has no grade access

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | viewer2  | Vicky     | Viewer   | viewer2@example.com  |
      | manager1 | Max       | Manager  | manager1@example.com |
      | student1 | Sam       | Student  | student1@example.com |
    And the following "courses" exist:
      | fullname   | shortname |
      | Course One | C1        |
      | Course Two | C2        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | student1 | C1     | student        |
      | student1 | C2     | student        |
      | viewer2  | C1     | editingteacher |
    And the following "roles" exist:
      | shortname  | name              |
      | lifeviewer | Life story viewer |
    And the following "role capabilities" exist:
      | role       | report/lifestory:view |
      | lifeviewer | allow                 |
    And the following "system role assigns" exist:
      | user     | role       |
      | viewer2  | lifeviewer |
      | manager1 | manager    |

  @MDL-INT-013 @MDL-INT-011
  Scenario: A teacher only sees the courses where they can view the student's grades
    Given I log in as "viewer2"
    When I view the life story report for user "student1"
    Then I should see "Course One"
    And I should not see "Course Two"

  @MDL-INT-013 @MDL-INT-011
  Scenario: A manager sees every course of the student
    Given I log in as "manager1"
    When I view the life story report for user "student1"
    Then I should see "Course One"
    And I should see "Course Two"

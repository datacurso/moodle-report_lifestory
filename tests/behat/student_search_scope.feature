@report @report_lifestory
Feature: The life story student search only lists students the viewer can see grades for
  In order to protect student grade privacy
  As an administrator
  I need the student search to hide students from courses without grade access

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | viewer2  | Vicky     | Viewer   | viewer2@example.com  |
      | manager1 | Max       | Manager  | manager1@example.com |
      | student1 | Alpha     | Alpine   | student1@example.com |
      | student2 | Alpha     | Boreal   | student2@example.com |
    And the following "courses" exist:
      | fullname   | shortname |
      | Course One | C1        |
      | Course Two | C2        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | student1 | C1     | student        |
      | student2 | C2     | student        |
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

  Scenario: A teacher only finds students of the courses where they can view grades
    Given I log in as "viewer2"
    When I search the life story report for "Alpha"
    Then I should see "Alpha Alpine"
    And I should not see "Alpha Boreal"

  Scenario: A manager finds students of every course
    Given I log in as "manager1"
    When I search the life story report for "Alpha"
    Then I should see "Alpha Alpine"
    And I should see "Alpha Boreal"

@report @report_lifestory
Feature: AI feedback generation in the life story report requires a dedicated capability
  In order to control who can generate AI feedback for students
  As an administrator
  I need the generate AI feedback action to be limited to users holding the capability

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | viewer1  | Vera      | Viewer   | viewer1@example.com  |
      | manager1 | Max       | Manager  | manager1@example.com |
    And the following "roles" exist:
      | shortname  | name              |
      | lifeviewer | Life story viewer |
    And the following "role capabilities" exist:
      | role       | report/lifestory:view |
      | lifeviewer | allow                 |
    And the following "system role assigns" exist:
      | user     | role       |
      | viewer1  | lifeviewer |
      | manager1 | manager    |

  Scenario: A view-only user does not see the generate button and the forced action is denied
    Given I log in as "viewer1"
    When I view the life story report for user "manager1"
    Then I should see "Export to CSV"
    And I should not see "Generate AI feedback"
    And requesting the life story AI feedback action with a valid sesskey should be denied by missing capability

  Scenario: A user with the generation capability passes the server-side permission gate
    Given I log in as "manager1"
    When I view the life story report for user "viewer1"
    Then I should see "Generate AI feedback"
    And requesting the life story AI feedback action with a valid sesskey should pass the permission gate

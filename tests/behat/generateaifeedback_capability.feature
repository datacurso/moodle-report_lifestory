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
      | student1 | Sam       | Student  | student1@example.com |
    And the following "courses" exist:
      | fullname   | shortname |
      | Course One | C1        |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
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

  @MDL-INT-014 @MDL-E2E-007
  Scenario: A view-only user sees no action buttons and the forced action is denied
    Given I log in as "viewer1"
    When I view the life story report for user "student1"
    Then I should see "This student has no course enrolments available to display in this report."
    And I should not see "Export to CSV"
    And I should not see "Generate AI feedback"
    And life story AI feedback action for "student1" with a valid sesskey should be denied by missing capability

  @MDL-INT-014 @MDL-INT-016
  Scenario: A user with the generation capability passes the server-side permission gate
    Given I log in as "manager1"
    When I view the life story report for user "student1"
    Then I should see "Generate AI feedback"
    And life story AI feedback action for "student1" with a valid sesskey should pass the permission gate

  @MDL-INT-014
  Scenario: The generation capability is evaluated in the course context when the course filter is applied
    Given the following "roles" exist:
      | shortname     | name                 |
      | lifegenerator | Life story generator |
    And the following "role capabilities" exist:
      | role          | report/lifestory:generateaifeedback |
      | lifegenerator | allow                               |
    And the following "course enrolments" exist:
      | user    | course | role           |
      | viewer1 | C1     | editingteacher |
    And the following "role assigns" exist:
      | user    | role          | contextlevel | reference |
      | viewer1 | lifegenerator | Course       | C1        |
    And I log in as "viewer1"
    When I view the life story report for user "student1" in course "C1"
    Then I should see "Generate AI feedback"
    And life story AI feedback action for "student1" with a valid sesskey should be denied by missing capability
    And life story AI feedback action for "student1" in course "C1" with a valid sesskey should pass the permission gate

@report @report_lifestory
Feature: The life story student search only lists students the viewer can see grades for
  In order to protect student grade privacy
  As an administrator
  I need the student search to hide students from courses without grade access

  Background:
    Given the following "users" exist:
      | username | firstname | lastname  | email                | suspended |
      | viewer2  | Vicky     | Viewer    | viewer2@example.com  | 0         |
      | manager1 | Max       | Manager   | manager1@example.com | 0         |
      | student1 | Alpha     | Alpine    | student1@example.com | 0         |
      | student2 | Alpha     | Boreal    | student2@example.com | 0         |
      | student3 | Alpha     | Suspended | student3@example.com | 1         |
      | beta01   | Beta      | Match01   | beta01@example.com   | 0         |
      | beta02   | Beta      | Match02   | beta02@example.com   | 0         |
      | beta03   | Beta      | Match03   | beta03@example.com   | 0         |
      | beta04   | Beta      | Match04   | beta04@example.com   | 0         |
      | beta05   | Beta      | Match05   | beta05@example.com   | 0         |
      | beta06   | Beta      | Match06   | beta06@example.com   | 0         |
      | beta07   | Beta      | Match07   | beta07@example.com   | 0         |
      | beta08   | Beta      | Match08   | beta08@example.com   | 0         |
      | beta09   | Beta      | Match09   | beta09@example.com   | 0         |
      | beta10   | Beta      | Match10   | beta10@example.com   | 0         |
      | beta11   | Beta      | Match11   | beta11@example.com   | 0         |
    And the following "courses" exist:
      | fullname   | shortname |
      | Course One | C1        |
      | Course Two | C2        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | student1 | C1     | student        |
      | student2 | C2     | student        |
      | student3 | C1     | student        |
      | beta01   | C1     | student        |
      | beta02   | C1     | student        |
      | beta03   | C1     | student        |
      | beta04   | C1     | student        |
      | beta05   | C1     | student        |
      | beta06   | C1     | student        |
      | beta07   | C1     | student        |
      | beta08   | C1     | student        |
      | beta09   | C1     | student        |
      | beta10   | C1     | student        |
      | beta11   | C1     | student        |
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

  @MDL-UNIT-004 @MDL-INT-004
  Scenario: A teacher only finds students of the courses where they can view grades
    Given I log in as "viewer2"
    When I search the life story report for "Alpha"
    Then I should see "Alpha Alpine"
    And I should not see "Alpha Boreal"

  @MDL-UNIT-004 @MDL-INT-004
  Scenario: A manager finds students of every course
    Given I log in as "manager1"
    When I search the life story report for "Alpha"
    Then I should see "Alpha Alpine"
    And I should see "Alpha Boreal"

  @MDL-UNIT-003 @MDL-E2E-004
  Scenario: A search matching more students than the limit shows the more matches notice
    Given I log in as "viewer2"
    When I search the life story report for "Beta"
    Then I should see "Beta Match01"
    And I should see "Beta Match10"
    And I should not see "Beta Match11"
    And I should see "More students match your search. Refine the text to narrow the results."

  @MDL-UNIT-003
  Scenario: A search with few matches does not show the more matches notice
    Given I log in as "manager1"
    When I search the life story report for "Boreal"
    Then I should see "Alpha Boreal"
    And I should not see "More students match your search. Refine the text to narrow the results."

  @MDL-INT-005 @MDL-UNIT-004
  Scenario: A suspended student never appears in the search results
    Given I log in as "viewer2"
    When I search the life story report for "Alpha"
    Then I should see "Alpha Alpine"
    And I should not see "Suspended"

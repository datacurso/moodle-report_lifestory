@report @report_lifestory
Feature: The life story student search works without JavaScript
  In order to find a student when JavaScript is unavailable
  As a report viewer
  I need the search form to submit conventionally and list matching students as links

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | manager1 | Max       | Manager  | manager1@example.com |
      | student1 | Alpha     | Alpine   | student1@example.com |
      | student2 | Beta      | Boreal   | student2@example.com |
    And the following "courses" exist:
      | fullname   | shortname |
      | Course One | C1        |
      | Course Two | C2        |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
      | student1 | C2     | student |
      | student2 | C1     | student |
    And the following "system role assigns" exist:
      | user     | role    |
      | manager1 | manager |

  Scenario: Submitting the search form without JavaScript lists matching students with conventional links
    Given I log in as "manager1"
    And I visit "/report/lifestory/index.php"
    When I set the field "Search users" to "Alpha"
    And I press "Search"
    Then I should see "Alpha Alpine"
    And I should not see "Beta Boreal"
    And I click on "Alpha Alpine" "link"
    And I should see "Course One"
    And I should see "Export to CSV"

  Scenario: A search with no matches shows a notice
    Given I log in as "manager1"
    And I visit "/report/lifestory/index.php"
    When I set the field "Search users" to "Zzzz"
    And I press "Search"
    Then I should see "No students match your search."

  Scenario: The course filter survives the form submission
    Given I log in as "manager1"
    And I visit the life story report filtered by course "C1"
    When I set the field "Search users" to "Alpha"
    And I press "Search"
    And I click on "Alpha Alpine" "link"
    Then I should see "Course One"
    And I should not see "Course Two"

@report @report_lifestory @javascript
Feature: The life story student search offers live results while typing
  In order to find a student quickly
  As a manager
  I need the search box to list matching students as I type without submitting the form

  Background:
    Given the following "users" exist:
      | username | firstname | lastname  | email                | suspended |
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
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
      | student2 | C1     | student |
      | student3 | C1     | student |
      | beta01   | C1     | student |
      | beta02   | C1     | student |
      | beta03   | C1     | student |
      | beta04   | C1     | student |
      | beta05   | C1     | student |
      | beta06   | C1     | student |
      | beta07   | C1     | student |
      | beta08   | C1     | student |
      | beta09   | C1     | student |
      | beta10   | C1     | student |
      | beta11   | C1     | student |
    And the following "system role assigns" exist:
      | user     | role    |
      | manager1 | manager |
    And I log in as "manager1"
    And I visit "/report/lifestory/index.php"

  @MDL-E2E-002
  Scenario: Typing shows live results with avatar, name and email and updates them on every change
    # The search service always returns a profile image URL (Moodle serves a
    # default avatar), so the initial letter fallback is never rendered.
    When I set the field "Search users" to "Alp"
    Then I should see "Alpha Alpine" in the "#search-results" "css_element"
    And I should see "student1@example.com" in the "#search-results" "css_element"
    And ".search-item .rounded-circle" "css_element" should exist in the "#search-results" "css_element"
    And "[data-region='static-search-results']" "css_element" should not exist
    And I set the field "Search users" to "Alpha B"
    And I should see "Alpha Boreal" in the "#search-results" "css_element"
    And I should not see "Alpha Alpine" in the "#search-results" "css_element"

  @MDL-E2E-002 @MDL-UNIT-003
  Scenario: More than ten matches list ten students followed by the refine notice
    When I set the field "Search users" to "Beta"
    Then I should see "Beta Match01" in the "#search-results" "css_element"
    And I should see "Beta Match10" in the "#search-results" "css_element"
    And I should not see "Beta Match11" in the "#search-results" "css_element"
    And I should see "More students match your search. Refine the text to narrow the results." in the "#search-results" "css_element"

  @MDL-E2E-002
  Scenario: The result list hides without matches or text, hides on outside clicks and returns on focus
    When I set the field "Search users" to "Zzzz"
    Then "#search-results" "css_element" should not be visible
    And I set the field "Search users" to "Alp"
    And I should see "Alpha Alpine" in the "#search-results" "css_element"
    And I set the field "Search users" to ""
    And "#search-results" "css_element" should not be visible
    And I set the field "Search users" to "Alp"
    And I should see "Alpha Alpine" in the "#search-results" "css_element"
    And I click on ".page-header-headings" "css_element"
    And "#search-results" "css_element" should not be visible
    And I click on "#usersearch" "css_element"
    And "#search-results" "css_element" should be visible
    And I should see "Alpha Alpine" in the "#search-results" "css_element"

  @MDL-E2E-002
  Scenario: Pressing Enter in the search box does not submit the form
    When I set the field "Search users" to "Alp"
    And I should see "Alpha Alpine" in the "#search-results" "css_element"
    And I press the enter key
    Then "[data-region='static-search-results']" "css_element" should not exist
    And "#search-results" "css_element" should be visible
    And I should see "Alpha Alpine" in the "#search-results" "css_element"

  @MDL-INT-005
  Scenario: A suspended student is absent from the live results
    When I set the field "Search users" to "Alpha"
    Then I should see "Alpha Alpine" in the "#search-results" "css_element"
    And I should see "Alpha Boreal" in the "#search-results" "css_element"
    And I should not see "Alpha Suspended" in the "#search-results" "css_element"

  @MDL-E2E-003
  Scenario: Selecting a student from the live results shows the name chip and clearing returns to the initial state
    When I set the field "Search users" to "Alp"
    And I click on "Alpha Alpine" "link" in the "#search-results" "css_element"
    Then "input[readonly][value='Alpha Alpine']" "css_element" should exist
    And I should see "Clear selection"
    And I should see "Export to CSV"
    And ".user-grade" "css_element" should exist
    And I click on "Clear selection" "link"
    And I should see "Please select a user to view their life story"
    And "Search users" "field" should exist
    And I should not see "Export to CSV"
    And ".user-grade" "css_element" should not exist

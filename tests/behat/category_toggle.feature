@report @report_lifestory @javascript
Feature: Grade categories in the life story report can be collapsed and remember their state
  In order to focus on the parts of the history I care about
  As a manager
  I need collapsed categories to stay collapsed per student and per view

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | manager1 | Max       | Manager  | manager1@example.com |
      | student1 | Sam       | Student  | student1@example.com |
      | student2 | Sara      | Second   | student2@example.com |
    And the following "courses" exist:
      | fullname   | shortname |
      | Course One | C1        |
      | Course Two | C2        |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
      | student1 | C2     | student |
      | student2 | C1     | student |
    And the following "grade categories" exist:
      | fullname     | course |
      | Category One | C1     |
      | Category Two | C2     |
    And the following "grade items" exist:
      | itemname   | course | gradecategory | grademax |
      | Alpha item | C1     | Category One  | 100      |
      | Beta item  | C2     | Category Two  | 100      |
    And the following "grade grades" exist:
      | gradeitem  | user     | grade |
      | Alpha item | student1 | 80    |
      | Beta item  | student1 | 70    |
      | Alpha item | student2 | 90    |
    And the following "system role assigns" exist:
      | user     | role    |
      | manager1 | manager |

  @MDL-E2E-005
  Scenario: A collapsed category survives a reload and is independent per view and per student
    Given I log in as "manager1"
    And I view the life story report for user "student1"
    And "Alpha item" "text" should be visible
    When I click on "//div[contains(@class, 'report_lifestory-course')][h4[text()='Course One']]//a[contains(@class, 'toggle-category')]" "xpath_element"
    Then "Alpha item" "text" should not be visible
    And "Beta item" "text" should be visible
    And I reload the page
    And "Alpha item" "text" should not be visible
    And "Beta item" "text" should be visible
    And I view the life story report for user "student1" in course "C1"
    And "Alpha item" "text" should be visible
    And I view the life story report for user "student2"
    And "Alpha item" "text" should be visible

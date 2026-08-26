@report @report_lifestory
Feature: The life story report shows one grade table per visible course of the student
  In order to review the whole academic history of a student
  As a report viewer
  I need one grade table per course, respecting the course filter and my grade permissions

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | manager1 | Max       | Manager  | manager1@example.com |
      | teacher1 | Tina      | Teacher  | teacher1@example.com |
      | student1 | Sam       | Student  | student1@example.com |
    And the following "courses" exist:
      | fullname     | shortname |
      | Course One   | C1        |
      | Course Two   | C2        |
      | Course Three | C3        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | student1 | C1     | student        |
      | student1 | C2     | student        |
      | student1 | C3     | student        |
      | teacher1 | C1     | editingteacher |
    And the following "grade categories" exist:
      | fullname     | course |
      | Category One | C1     |
    And the following "grade items" exist:
      | itemname     | course | gradecategory | grademax | hidden |
      | Visible item | C1     | Category One  | 100      | 0      |
      | Hidden item  | C1     | Category One  | 100      | 1      |
    And the following "grade items" exist:
      | itemname           | course | grademax |
      | Second course item | C2     | 50       |
    And the following "grade grades" exist:
      | gradeitem          | user     | grade |
      | Visible item       | student1 | 80    |
      | Hidden item        | student1 | 60    |
      | Second course item | student1 | 40    |
    And the following "roles" exist:
      | shortname  | name              |
      | lifeviewer | Life story viewer |
    And the following "role capabilities" exist:
      | role       | report/lifestory:view |
      | lifeviewer | allow                 |
    And the following "permission overrides" exist:
      | capability              | permission | role           | contextlevel | reference |
      | moodle/grade:viewhidden | Prohibit   | editingteacher | Course       | C1        |
    And the following "system role assigns" exist:
      | user     | role       |
      | manager1 | manager    |
      | teacher1 | lifeviewer |

  @MDL-INT-011
  Scenario: One grade table per visible course is shown with the course name as its title
    Given I log in as "manager1"
    When I view the life story report for user "student1"
    Then "//div[contains(@class, 'report_lifestory-course')][h4[text()='Course One']]//table[contains(@class, 'user-grade')]" "xpath_element" should exist
    And "//div[contains(@class, 'report_lifestory-course')][h4[text()='Course Two']]//table[contains(@class, 'user-grade')]" "xpath_element" should exist
    And I should see "Visible item"
    And I should see "Second course item"
    And I should see "Category One"
    And I should see "Course total"
    And I should see "Contribution to course total"

  @MDL-INT-011
  Scenario: A course without grade items still gets its own block while the other courses render normally
    # The core user report always renders at least the course total row, so the
    # "No report data available." notice is never reached for an empty gradebook.
    Given I log in as "manager1"
    When I view the life story report for user "student1"
    Then "//div[contains(@class, 'report_lifestory-course')][h4[text()='Course Three']]" "xpath_element" should exist
    And I should see "Visible item"
    And I should see "Second course item"

  @MDL-INT-011 @MDL-INT-018
  Scenario: The course filter restricts the view to a single grade table
    Given I log in as "manager1"
    When I view the life story report for user "student1" in course "C2"
    Then I should see "Course Two"
    And I should see "Second course item"
    And I should not see "Course One"
    And I should not see "Visible item"
    And "//table[contains(@class, 'user-grade')]" "xpath_element" should exist
    And "(//table[contains(@class, 'user-grade')])[2]" "xpath_element" should not exist

  @MDL-INT-012
  Scenario: A viewer allowed to see hidden grades sees the hidden grade item
    Given I log in as "manager1"
    When I view the life story report for user "student1"
    Then I should see "Visible item"
    And I should see "Hidden item"

  @MDL-INT-012
  Scenario: A viewer without the view hidden grades permission does not see the hidden grade item
    Given I log in as "teacher1"
    When I view the life story report for user "student1"
    Then I should see "Course One"
    And I should see "Visible item"
    And I should not see "Hidden item"

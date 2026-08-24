@report @report_lifestory
Feature: The PDF export only uses AI feedback stored on the server
  In order to trust the exported PDF content
  As a manager
  I need the PDF export to require server-side stored feedback instead of browser-supplied text

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | manager1 | Max       | Manager  | manager1@example.com |
      | student1 | Sam       | Student  | student1@example.com |
    And the following "courses" exist:
      | fullname   | shortname |
      | Course One | C1        |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
    And the following "system role assigns" exist:
      | user     | role    |
      | manager1 | manager |

  Scenario: Exporting the PDF without server-side stored feedback shows the missing feedback notice
    Given I log in as "manager1"
    Then life story "pdf" action for "student1" with a valid sesskey shows the missing feedback notice

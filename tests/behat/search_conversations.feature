@mod @mod_dialogue @mod_dialogue_report
Feature: Search conversations using the report page
  In order to find specific conversations in a dialogue activity
  As a teacher
  I need to be able to view and filter the search-conversations report

  Background:
    Given the following "users" exist:
      | username  | firstname | lastname | email                  |
      | teacher1  | Teacher   | One      | teacher1@example.com   |
      | student1  | Alice     | Smith    | student1@example.com   |
      | student2  | Bob       | Jones    | student2@example.com   |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
    And the following "activities" exist:
      | activity | name            | course | idnumber |
      | dialogue | Test Dialogue   | C1     | dialogue1 |
    And the following "mod_dialogue > conversations" exist:
      | dialogue  | userfrom | userto   | subject          | body                     |
      | dialogue1 | student1 | teacher1 | Hello teacher    | This is my first message |
      | dialogue1 | student2 | teacher1 | Another subject  | Another message body     |

  @javascript
  Scenario: Teacher can see the Search conversations tab
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test Dialogue"
    Then I should see "Search conversations"

  @javascript
  Scenario: The report page loads and shows conversations
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test Dialogue"
    When I follow "Search conversations"
    Then I should see "Hello teacher"
    And I should see "Another subject"
    And I should see "Alice Smith"
    And I should see "Bob Jones"

  @javascript
  Scenario: Teacher can filter conversations by participant full name
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test Dialogue"
    And I follow "Search conversations"
    When I set the field "Full name" to "Alice"
    And I click on "Apply" "button" in the ".reportbuilder-filters" "css_element"
    Then I should see "Hello teacher"
    But I should not see "Another subject"

  @javascript
  Scenario: Teacher can filter conversations by subject
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test Dialogue"
    And I follow "Search conversations"
    When I set the field "Subject" to "Hello"
    And I click on "Apply" "button" in the ".reportbuilder-filters" "css_element"
    Then I should see "Hello teacher"
    But I should not see "Another subject"

  @javascript
  Scenario: Teacher can filter conversations by status
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test Dialogue"
    And I follow "Search conversations"
    When I set the field "Status" to "Open"
    And I click on "Apply" "button" in the ".reportbuilder-filters" "css_element"
    Then I should see "Hello teacher"
    And I should see "Another subject"

  @javascript
  Scenario: Student cannot access the search conversations report
    Given I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test Dialogue"
    Then I should not see "Search conversations"

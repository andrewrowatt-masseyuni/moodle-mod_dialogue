@mod @mod_dialogue @mod_dialogue_report
Feature: Search messages using the report page
  In order to find specific messages in a dialogue activity
  As a teacher
  I need to be able to view and filter the search-messages report

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
    And the following "permission overrides" exist:
      | capability             | permission | role           | contextlevel | reference |
      | mod/dialogue:viewany   | Allow      | editingteacher | Course       | C1        |
    And the following "activities" exist:
      | activity | name            | course | idnumber |
      | dialogue | Test Dialogue   | C1     | dialogue1 |
    And the following "mod_dialogue > conversations" exist:
      | dialogue  | userfrom | userto   | subject          | body                     |
      | dialogue1 | student1 | teacher1 | Hello teacher    | This is my first message |
      | dialogue1 | student2 | teacher1 | Another subject  | Another message body     |

  @javascript
  Scenario: Teacher can see the Search messages tab
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test Dialogue"
    Then I should see "Search messages"

  @javascript
  Scenario: The report page loads and shows messages
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test Dialogue"
    When I follow "Search messages"
    Then I should see "Hello teacher"
    And I should see "Another subject"
    And I should see "Alice Smith"
    And I should see "Bob Jones"

  @javascript
  Scenario: Teacher can filter messages by author (From)
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test Dialogue"
    And I follow "Search messages"
    When I click on "Filters" "button"
    And I set the following fields in the "From" "core_reportbuilder > Filter" to these values:
      | From operator | Contains |
      | From value    | Alice    |
    And I click on "Apply" "button" in the "[data-region='report-filters']" "css_element"
    Then I should see "Hello teacher"
    But I should not see "Another subject"

  @javascript
  Scenario: Teacher can filter messages by subject
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test Dialogue"
    And I follow "Search messages"
    When I click on "Filters" "button"
    And I set the following fields in the "Subject" "core_reportbuilder > Filter" to these values:
      | Subject operator | Contains |
      | Subject value    | Hello    |
    And I click on "Apply" "button" in the "[data-region='report-filters']" "css_element"
    Then I should see "Hello teacher"
    But I should not see "Another subject"

  @javascript
  Scenario: Teacher can filter messages by state
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test Dialogue"
    And I follow "Search messages"
    When I click on "Filters" "button"
    And I set the following fields in the "State" "core_reportbuilder > Filter" to these values:
      | State operator | Is equal to |
      | State value    | Open        |
    And I click on "Apply" "button" in the "[data-region='report-filters']" "css_element"
    Then I should see "Hello teacher"
    And I should see "Another subject"

  @javascript
  Scenario: Student cannot access the search messages report
    Given I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test Dialogue"
    Then I should not see "Search messages"

@tool @tool_uploadpage @_file_upload
Feature: An admin can create a single page activity course using a text delimited file
  In order to create courses using a text delimited file
  As an admin
  I need to be able to upload a text delimited file and navigate through the import process

  @javascript
  Scenario: Creation of non-existent course from file with colon delimiter
    When I log in as "admin"
    And I navigate to "Courses > Upload single page courses" in site administration
    And I upload "admin/tool/uploadpage/tests/fixtures/onecoursecolon.txt" file to "CSV file" filemanager
    And I select "colon" from the "CSV delimiter" singleselect
    And I press "Import"
    And I press "Confirm"
    And I should see "Courses created: 1"
    And I am on site homepage
    And I should see "C1b49aa30-e719-11e6-9835-f723b46a2699 Full Name"

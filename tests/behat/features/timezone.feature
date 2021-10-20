@install
Feature: Default timezone.

  Fresh install of tide profile sets the default timezone to Australia/Melbourne

  @api
  Scenario: Melbourne is set up as the default timezone
    Given I am logged in as a user with the "administrator" role
    When I visit "admin/config/regional/settings"
    And save screenshot
    Then I see field "Default time zone"
    And "Australia/Melbourne" is selected from "Default time zone"

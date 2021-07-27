@install
Feature: Site and primary site fields exist.

  Fresh install of tide profile has the site and primary site fields available

  @api
  Scenario: Event has primary and site fields
    Given I am logged in as a user with the "editor" role
    When I visit "node/add/event/"
    And save screenshot
    Then I see field "edit-field-node-site"
    And I see field "edit-field-node-primary-site"

  @api
  Scenario: Landing page has primary and site fields
    Given I am logged in as a user with the "editor" role
    When I visit "node/add/landing_page/"
    And save screenshot
    Then I see field "edit-field-node-site"
    And I see field "edit-field-node-primary-site"

  @api
  Scenario: News has primary and site fields
    Given I am logged in as a user with the "editor" role
    When I visit "node/add/news/"
    And save screenshot
    Then I see field "edit-field-node-site"
    And I see field "edit-field-node-primary-site"

  @api
  Scenario: Page has primary and site fields
    Given I am logged in as a user with the "editor" role
    When I visit "node/add/page/"
    And save screenshot
    Then I see field "edit-field-node-site"
    And I see field "edit-field-node-primary-site"

  @api
  Scenario: Publication has primary and site fields
    Given I am logged in as a user with the "editor" role
    When I visit "node/add/publication/"
    And save screenshot
    Then I see field "edit-field-node-site"
    And I see field "edit-field-node-primary-site"

  @api
  Scenario: Publication page has primary and site fields
    Given I am logged in as a user with the "editor" role
    When I visit "node/add/publication_page/"
    And save screenshot
    Then I see field "edit-field-node-site"
    And I see field "edit-field-node-primary-site"
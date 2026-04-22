# SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

Feature: admin gateway API

  Background:
    Given user "admin" exists

  Scenario: unauthenticated requests are rejected
    Given as user ""
    When sending "get" to ocs "/apps/twofactor_gateway/admin/gateways"
    Then the response should have a status code 401

  Scenario: non-admin users are not allowed to list gateways
    Given user "regularuser" exists
    And as user "regularuser"
    When sending "get" to ocs "/apps/twofactor_gateway/admin/gateways"
    Then the response should have a status code 403

  Scenario: admin can list available gateways
    Given as user "admin"
    When sending "get" to ocs "/apps/twofactor_gateway/admin/gateways"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                      | value                  |
      | (jq).ocs.data            | (jq)length >= 1        |
      | (jq).ocs.data[0].id      | (jq)type == "string"   |
      | (jq).ocs.data[0].name    | (jq)type == "string"   |
      | (jq).ocs.data[0].fields  | (jq)type == "array"    |
      | (jq).ocs.data[0].instances | (jq)type == "array" |

  Scenario: admin can create a gateway instance
    Given as user "admin"
    When sending "post" to ocs "/apps/twofactor_gateway/admin/gateways/signal/instances"
      | label  | My Signal |
      | config[url] | http://signal.example.com |
    Then the response should have a status code 201
    And the response should be a JSON array with the following mandatory values
      | key                       | value              |
      | (jq).ocs.data.id          | (jq)type == "string" |
      | (jq).ocs.data.label       | My Signal          |
      | (jq).ocs.data.default     | true               |
      | (jq).ocs.data.isComplete  | true               |
    And fetch field "(INSTANCE_ID)(jq).ocs.data.id" from previous JSON response

  Scenario: creating an instance for an unknown gateway returns 400
    Given as user "admin"
    When sending "post" to ocs "/apps/twofactor_gateway/admin/gateways/nonexistent/instances"
      | label | Test |
    Then the response should have a status code 400

  Scenario: admin can get a single gateway instance
    Given as user "admin"
    And sending "post" to ocs "/apps/twofactor_gateway/admin/gateways/signal/instances"
      | label  | My Signal |
      | config[url] | http://signal.example.com |
    And the response should have a status code 201
    And fetch field "(INSTANCE_ID)(jq).ocs.data.id" from previous JSON response
    When sending "get" to ocs "/apps/twofactor_gateway/admin/gateways/signal/instances/<INSTANCE_ID>"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                   | value         |
      | (jq).ocs.data.id      | <INSTANCE_ID> |
      | (jq).ocs.data.label   | My Signal     |

  Scenario: reading a non-existent instance returns 404
    Given as user "admin"
    When sending "get" to ocs "/apps/twofactor_gateway/admin/gateways/signal/instances/nonexistent000"
    Then the response should have a status code 404

  Scenario: admin can update a gateway instance label and config
    Given as user "admin"
    And sending "post" to ocs "/apps/twofactor_gateway/admin/gateways/signal/instances"
      | label  | Original |
      | config[url] | http://old.example.com |
    And the response should have a status code 201
    And fetch field "(INSTANCE_ID)(jq).ocs.data.id" from previous JSON response
    When sending "put" to ocs "/apps/twofactor_gateway/admin/gateways/signal/instances/<INSTANCE_ID>"
      | label  | Updated |
      | config[url] | http://new.example.com |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                   | value                     |
      | (jq).ocs.data.id      | <INSTANCE_ID>             |
      | (jq).ocs.data.label   | Updated                   |

  Scenario: admin can delete a gateway instance
    Given as user "admin"
    And sending "post" to ocs "/apps/twofactor_gateway/admin/gateways/signal/instances"
      | label  | To be deleted |
      | config[url] | http://signal.example.com |
    And the response should have a status code 201
    And fetch field "(INSTANCE_ID)(jq).ocs.data.id" from previous JSON response
    When sending "delete" to ocs "/apps/twofactor_gateway/admin/gateways/signal/instances/<INSTANCE_ID>"
    Then the response should have a status code 200
    When sending "get" to ocs "/apps/twofactor_gateway/admin/gateways/signal/instances/<INSTANCE_ID>"
    Then the response should have a status code 404

  Scenario: admin can set an instance as default
    Given as user "admin"
    And sending "post" to ocs "/apps/twofactor_gateway/admin/gateways/signal/instances"
      | label  | First |
      | config[url] | http://first.example.com |
    And the response should have a status code 201
    And fetch field "(FIRST_ID)(jq).ocs.data.id" from previous JSON response
    And sending "post" to ocs "/apps/twofactor_gateway/admin/gateways/signal/instances"
      | label  | Second |
      | config[url] | http://second.example.com |
    And the response should have a status code 201
    And fetch field "(SECOND_ID)(jq).ocs.data.id" from previous JSON response
    When sending "post" to ocs "/apps/twofactor_gateway/admin/gateways/signal/instances/<SECOND_ID>/default"
    Then the response should have a status code 200
    When sending "get" to ocs "/apps/twofactor_gateway/admin/gateways/signal/instances/<SECOND_ID>"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                     | value |
      | (jq).ocs.data.default   | true  |
    When sending "get" to ocs "/apps/twofactor_gateway/admin/gateways/signal/instances/<FIRST_ID>"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                     | value |
      | (jq).ocs.data.default   | false |

  Scenario: the first created instance is automatically the default
    Given as user "admin"
    When sending "post" to ocs "/apps/twofactor_gateway/admin/gateways/signal/instances"
      | label  | First |
      | config[url] | http://signal.example.com |
    Then the response should have a status code 201
    And the response should be a JSON array with the following mandatory values
      | key                     | value |
      | (jq).ocs.data.default   | true  |

  Scenario: the default instance fields are mirrored to the primary legacy keys
    Given as user "admin"
    When sending "post" to ocs "/apps/twofactor_gateway/admin/gateways/signal/instances"
      | label  | Production |
      | config[url] | http://signal.example.com |
    Then the response should have a status code 201
    # The legacy key "signal_url" must exist after creating the default instance
    And run the command "app:config:get twofactor_gateway signal_url" with result code 0

  Scenario: testing an incomplete instance returns 400
    Given as user "admin"
    When sending "post" to ocs "/apps/twofactor_gateway/admin/gateways/signal/instances"
      | label  | Bare |
    And the response should have a status code 201
    And fetch field "(INSTANCE_ID)(jq).ocs.data.id" from previous JSON response
    When sending "post" to ocs "/apps/twofactor_gateway/admin/gateways/signal/instances/<INSTANCE_ID>/test"
      | identifier | +1234567890 |
    Then the response should have a status code 400

  Scenario: listing gateways includes instances
    Given as user "admin"
    And sending "post" to ocs "/apps/twofactor_gateway/admin/gateways/signal/instances"
      | label  | My Signal |
      | config[url] | http://signal.example.com |
    And the response should have a status code 201
    When sending "get" to ocs "/apps/twofactor_gateway/admin/gateways"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                     | value     |
      | (jq).ocs.data[] \| select(.id=="signal") \| .instances | (jq)length == 1 |

# Two Factor Gateway

A set of Nextcloud two-factor providers to send authentication codes via Signal, SMS and Telegram.

![Test Status](https://github.com/nextcloud/twofactor_gateway/workflows/PHPUnit/badge.svg?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/nextcloud/twofactor_gateway/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/nextcloud/twofactor_gateway/?branch=master)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/nextcloud/twofactor_gateway/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/nextcloud/twofactor_gateway/?branch=master)
[![Read the Docs](https://img.shields.io/readthedocs/nextcloud-twofactor-gateway.svg)](https://nextcloud-twofactor-gateway.readthedocs.io/en/latest/)

![](https://raw.githubusercontent.com/ChristophWurst/twofactor_gateway/ae08ce30abfa866c7c7a486d850d4be07b83d82d/screenshots/challenge.png)

## Supported Messaging Gateways
This app uses external messaging gateway services for sending the code. See the
[admin documentation] on how to configure the specific providers.

## Login with external apps
All modern applications communicating with Nextcloud now use Login flow so you
will be able to log in just like you would on the web, including, but not
limited to SMS-based authentication.

Absent support for the Login flow, after enabling Two Factor SMS, your legacy
applications will accept device passwords. Read more on [managing devices].

[admin documentation]: https://nextcloud-twofactor-gateway.readthedocs.io/en/latest/Admin%20Documentation/
[managing devices]: https://docs.nextcloud.com/server/stable/user_manual/session_management.html#managing-devices

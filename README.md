# Two Factor Sms
A two-factor auth provider for Nextcloud 14 and up. See [my blog post](http://blog.wuc.me/2016/05/30/adding-two-factor-auth-to-owncloud.html) on more info about Nextcloud's internal 2FA.

[![Build Status](https://travis-ci.org/nextcloud/twofactor_sms.svg?branch=master)](https://travis-ci.org/nextcloud/twofactor_sms)
[![Code Coverage](https://scrutinizer-ci.com/g/nextcloud/twofactor_sms/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/nextcloud/twofactor_sms/?branch=master)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/nextcloud/twofactor_sms/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/nextcloud/twofactor_sms/?branch=master)

![](https://raw.githubusercontent.com/ChristophWurst/twofactor_sms/24a9ef4ec5acf6fa00958008118479c759147384/screenshots/challenge1.png)
![](https://raw.githubusercontent.com/ChristophWurst/twofactor_sms/24a9ef4ec5acf6fa00958008118479c759147384/screenshots/challenge2.png)

## Supported SMS services
This app uses external SMS services for sending the code. Currently there are only two providers, but the idea is to support multiple as different countries have their specific providers.

### websms.de
URL: https://websms.de/

Admin configuration:
```bash
./occ config:app:set twofactor_sms sms_provider --value "websms.de"
./occ config:app:set twofactor_sms websms_de_user --value "yourusername"
./occ config:app:set twofactor_sms websms_de_password --value "yourpassword"
```

User configuration:
(no GUI yet, you have to write to the DB directly :speak_no_evil:)
Table: ``oc_preferences``
Data:
- userid: your Nextcloud user UID
- appid: ``twofactor_sms``
- configkey: ``phone``
- configvalue: your phone number in the [MSISDN format](https://en.wikipedia.org/wiki/MSISDN). E.g. +4912345678 is 4912345678

### playSMS
URL: https://playsms.org/

Use the Webservices provided by playSMS for sending SMS.

Admin configuration:
```bash
./occ config:app:set twofactor_sms sms_provider --value "playsms"
./occ config:app:set twofactor_sms playsms_url --value "playsmswebservicesurl"
./occ config:app:set twofactor_sms playsms_user --value "yourusername"
./occ config:app:set twofactor_sms playsms_password --value "yourpassword"
```

### Telegram
URL: https://www.telegram.org/

Uses Telegram messages for sending a 2fa code to log in in Nextcloud

Admin configuration:
```bash
./occ config:app:set twofactor_sms sms_provider --value "telegram"
./occ config:app:set twofactor_sms telegram_bot_token --value "your telegram bot api token"
./occ config:app:set twofactor_sms telegram_url --value "https://api.telegram.org/bot"
```
User configuration:
(no GUI yet, you have to write to the DB directly :speak_no_evil:)
Table: ``oc_preferences``

Data row 1:
- userid: your Nextcloud user UID
- appid: ``twofactor_sms``
- configkey: ``phone``
- configvalue: your phone number in the [MSISDN format](https://en.wikipedia.org/wiki/MSISDN). E.g. +4912345678 is 4912345678

Data row 2:
- userid: your Nextcloud user UID
- appid: ``twofactor_sms``
- configkey: ``telegram_id``
- configvalue: your telegram id. You can get your telegram id by searching the user <b>What's my Telegram ID?</b> in Telegram and start the conversation.

## Login with external apps
All modern applications communicating with Nextcloud now use Login flow so you will be able to log in just like you would on the web, including, but not limited to SMS-based authentication.

Absent support for the Login flow, after enabling Two Factor SMS, your legacy applications will accept device passwords.
To manage them, [see more here](https://docs.nextcloud.com/server/14/user_manual/session_management.html#managing-devices)

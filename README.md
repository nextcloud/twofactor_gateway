# Two Factor Sms
A two-factor auth provider for Nextcloud 11. See [my blog post](http://blog.wuc.me/2016/05/30/adding-two-factor-auth-to-owncloud.html) on more info about Nextcloud's internal 2FA.

[![Build Status](https://travis-ci.org/nextcloud/twofactor_sms.svg?branch=master)](https://travis-ci.org/nextcloud/twofactor_sms)

![](https://raw.githubusercontent.com/ChristophWurst/twofactor_sms/24a9ef4ec5acf6fa00958008118479c759147384/screenshots/challenge1.png)
![](https://raw.githubusercontent.com/ChristophWurst/twofactor_sms/24a9ef4ec5acf6fa00958008118479c759147384/screenshots/challenge2.png)

## Supported SMS services
This app uses external SMS services for sending the code. Currently there is only one provider, but the idea is to support multiple as different countries have their specific providers.

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

## Login with external apps
Once you enable Two Factor SMS, your aplications (for example your Android app or your GNOME app) will need to login using device passwords. To manage it, [know more here](https://docs.nextcloud.com/server/11/user_manual/session_management.html#managing-devices)

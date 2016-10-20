# Two Factor Sms
A two-factor auth provider for Nextcloud 11. See [my blog post](http://blog.wuc.me/2016/05/30/adding-two-factor-auth-to-owncloud.html) on more info about Nextcloud's internal 2FA.

![](https://cloud.githubusercontent.com/assets/1374172/15873103/4791254a-2cfd-11e6-9951-c693535fcea9.png)
![](https://cloud.githubusercontent.com/assets/1374172/15873104/47bccc5e-2cfd-11e6-904c-ea40f323e619.png)

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

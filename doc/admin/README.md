# Admin Documentation

## Providers

Here you can find the configuration instructors for the currently supported gateways.

### websms.de
URL: https://websms.de/

Admin configuration:
```bash
./occ config:app:set twofactor_gateway sms_provider --value "websms.de"
./occ config:app:set twofactor_gateway websms_de_user --value "yourusername"
./occ config:app:set twofactor_gateway websms_de_password --value "yourpassword"
```

### playSMS
URL: https://playsms.org/

Use the Webservices provided by playSMS for sending SMS.

Admin configuration:
```bash
./occ config:app:set twofactor_gateway sms_provider --value "playsms"
./occ config:app:set twofactor_gateway playsms_url --value "playsmswebservicesurl"
./occ config:app:set twofactor_gateway playsms_user --value "yourusername"
./occ config:app:set twofactor_gateway playsms_password --value "yourpassword"
```

### Telegram
URL: https://www.telegram.org/

Uses Telegram messages for sending a 2FA code

Admin configuration:
```bash
./occ config:app:set twofactor_gateway sms_provider --value "telegram"
./occ config:app:set twofactor_gateway telegram_bot_token --value "your telegram bot api token"
./occ config:app:set twofactor_gateway telegram_url --value "https://api.telegram.org/bot"
```

Specific entries in `oc_preferences`:
- userid: your Nextcloud user UID
- appid: ``twofactor_gateway``
- configkey: ``telegram_id``
- configvalue: your telegram id. You can get your telegram id by searching the user <b>What's my Telegram ID?</b> in Telegram and start the conversation.

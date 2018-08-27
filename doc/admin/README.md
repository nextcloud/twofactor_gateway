# Admin Documentation

## Gateways

Here you can find the configuration instructors for the currently supported gateways.

### playSMS
Url: https://playsms.org/
Stability: Experimental

Use the Webservices provided by playSMS for sending SMS.

Interactive admin configuration:
```bash
occ twofactorauth:gateway:configure sms
```

### Signal
Url: https://www.signal.org/
Stability: Experimental

Interactive admin configuration:
```bash
occ twofactorauth:gateway:configure signal
```

### Telegram
Url: https://www.telegram.org/
Stability: Unstable

In order to send messages via the Telegram network, you have to register a [Telegram Bot](https://core.telegram.org/bots). This bot is used to send authentication codes to users after they have initiated a conversation and entered their Telegram ID.

Once you've got your bot's token, follow the interactive configuration command:
```bash
occ twofactorauth:gateway:configure telegram
```

### websms.de
Url: https://websms.de/
Stability: Stable

Interactive admin configuration:
```bash
occ twofactorauth:gateway:configure sms
```

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

Uses Telegram messages for sending a 2FA code

Interactive admin configuration:
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

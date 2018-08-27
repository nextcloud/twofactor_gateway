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

This gateways allows you to send messages via the Signal protocol. The Signal gateway can be
run as Docker container. The image for gateway including setup instructions can be found on
[GitLab](https://gitlab.com/morph027/signal-web-gateway).

*Note: Signal users are bound to phone numbers. If you already use Signal on your phone, you need a separate number for the gateway's registration.*

Once you've set up the gateway, you can configure this app interactively:

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

# Admin Documentation

## Gateways

Here you can find the configuration instructions for the currently supported gateways.

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

*Note: Signal users are bound to phone numbers. If you already use Signal on your phone, you
need a separate number for the gateway's registration.*

Once you've set up the gateway, you can configure this app interactively:

```bash
occ twofactorauth:gateway:configure signal
```

### Telegram
Url: https://www.telegram.org/
Stability: Unstable

This gateways allows you to send messages via the Telegram protocol. In order to send messages,
you have to register a [Telegram Bot](https://core.telegram.org/bots), which is used to send
authentication codes to users after they have initiated a conversation and entered their
Telegram ID.

Follow these steps to activate the Telegram authentication gateway:

1. Register a Telegram Bot.

   * Open your Telegram client, search for `@BotFather` and start a conversation.
   * Send `/newbot` to start the bot setup process.
   * Send the name of the bot, e.g. `'My own NC bot'`.
   * Send the username of the bot, e.g. `'my_nc_bot'`.

   BotFather confirmes that a new bot has successfully been set-up and provides the HTTP API
   access token to you, e.g. `'123456789:AAbbCCddEEffGGhhIIjjKKllMMnnOOppQQ'`.

2. Activate the Nextcloud Twofactor Gateway for Telegram
   Open a command shell on your Nextcloud server, navigate to the Nextcloud directory and run
   the following command:
   ```bash
   occ twofactorauth:gateway:configure telegram
   Please enter your Telegram bot token: 123456789:AAbbCCddEEffGGhhIIjjKKllMMnnOOppQQ
   Using 123456789:AAbbCCddEEffGGhhIIjjKKllMMnnOOppQQ.
   ```
   
   The Telegram authentication gateway has now successfully been set-up. Follow the instructions
   in the user documentation to activate the Gateway for a specific user.

### websms.de
Url: https://websms.de/
Stability: Stable

Interactive admin configuration:
```bash
occ twofactorauth:gateway:configure sms
```

### PuzzelSMS
Url: https://github.com/PuzzelSolutions/sms
Stability: Experimental

Use the SMS gateway provided by Puzzel for sending SMS.

Interactive admin configuration:

```bash
occ twofactorauth:gateway:configure sms
```

### EcallSMS
Url: https://www.ecall.ch/
Stability: Experimental

Use the HTTPS service provided by eCall.ch for sending SMS.

Interactive admin configuration:
```bash
occ twofactorauth:gateway:configure sms
```
For 'sender ID' you can use 16 numbers or 11 alphanummeric characters.

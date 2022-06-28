# Admin Documentation

## Gateways

Here you can find the configuration instructions for the currently supported gateways.

### playSMS
URL: https://playsms.org/
Stability: Experimental

Use the Webservices provided by playSMS for sending SMS.

Interactive admin configuration:
```bash
occ twofactorauth:gateway:configure sms
```

### SMSGlobal 
URL: https://www.smsglobal.com/
Stability: Experimental

Use the Webservices provided by SMSGlobal for sending SMS.

Interactive admin configuration:
```bash
occ twofactorauth:gateway:configure sms
```

### Signal
URL: https://www.signal.org/
Stability: Experimental

This gateways allows you to send messages via the Signal protocol. The Signal gateway can be
run as a Docker container. The image for the gateway including setup instructions can be found on
[GitLab](https://gitlab.com/morph027/signal-web-gateway).

*Note: Signal users are bound to phone numbers. If you already use Signal on your phone, you
need a separate number for the gateway's registration.*

Once you've set up the gateway, you can configure this app interactively:

```bash
occ twofactorauth:gateway:configure signal
```

### Telegram
URL: https://www.telegram.org/
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

   BotFather confirms that a new bot has successfully been set-up and provides the HTTP API
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
   in the [User Documentation] to activate the Gateway for a specific user.

### websms.de
URL: https://websms.de/
Stability: Stable

Interactive admin configuration:
```bash
occ twofactorauth:gateway:configure sms
```

### PuzzelSMS
URL: https://github.com/PuzzelSolutions/sms
Stability: Experimental

Use the SMS gateway provided by Puzzel for sending SMS.

Interactive admin configuration:
```bash
occ twofactorauth:gateway:configure sms
```

### EcallSMS
URL: https://www.ecall.ch/
Stability: Experimental

Use the HTTPS service provided by eCall.ch for sending SMS.

Interactive admin configuration:
```bash
occ twofactorauth:gateway:configure sms
```
For 'sender ID' you can use 16 numbers or 11 alphanumeric characters.

### VoIP.ms
URL: https://voip.ms
Stability: Experimental

Use the SMS gateway provided by VoIP.ms for sending SMS.

Interactive admin configuration:
```bash
occ twofactorauth:gateway:configure sms
```

## Voipbuster
URL: https://voipbuster.com
Stability: Experimental

Use the HTTPS service provided by Voipbuster.com for sending SMS.

Interactive admin configuration:
```bash
occ twofactorauth:gateway:configure sms
```

### sms77.io
URL: https://sms77.io
Stability: Experimental

Use the SMS gateway provided by sms77.io for sending SMS.

Interactive admin configuration:
```bash
occ twofactorauth:gateway:configure sms
```

### OVH
URL: https://www.ovhtelecom.fr/sms/
Stability: Experimental

Use the SMS gateway provided by OVH for sending SMS.

1. First create an application key, an application secret and a consumer key with the [createToken](https://eu.api.ovh.com/createToken/index.cgi?GET=/sms&GET=/sms/*/jobs&POST=/sms/*/jobs) page.

2. Go to you OVH account manager and get an SMS plan. You should see on the sidebar menu the SMS submenu with the account name: *sms-#######*

3. Create a "sender". On the main page of the SMS account, you should see a *Create a sender* link.

4. Interactive admin configuration:
```bash
occ twofactorauth:gateway:configure sms
```

   * Choose the `ovh` SMS provider.
   * Choose the endpoint connexion.
   * Enter successively the application key, the application secret, the consumer key, the account, and the sender.

5. Try to send a test with
```bash
occ twofactorauth:gateway:test <uid> sms <receiver>
```

### Spryng
URL: https://www.spryng.nl
Stability: Experimental

Use the HTTPS service provided by Spryng.nl for sending SMS.

Interactive admin configuration:
```bash
occ twofactorauth:gateway:configure sms
```

### Clickatell (Developer Central)
URL: https://central.clickatell.com/
Stability: Experimental

Use legacy Clickatell.com API for sending SMS.

* Login with your credencials at [archive.clickatell.com](https://archive.clickatell.com/login)
* Add a new HTTP API at [central.clickatell.com](https://central.clickatell.com/api/http/add)
* Go to `Edit Settings`:
  * Rename your new API
  * Change username to something different
  * Choose a secure and unique password
  * (optional): Set maximum message parts to 1
  * (optional): Enable auto-conversion of mobile numbers
  * (optional): Restrict access to your webserver IP-address
* Save changes!

Interactive admin configuration:
```bash
occ twofactorauth:gateway:configure sms
```

Select `clickatellcentral` and enter your API-ID, API username and API password.

### Clickatell (SMS Platform)
URL: https://portal.clickatell.com/
Stability: Experimental

Use new Clickatell.com API for sending SMS.

* Login with your credencials at [portal.clickatell.com](https://portal.clickatell.com/)
* Click at the button `Create new integration`
  * API Type: HTTP
  * Messaging type: One-way messaging
  * Delivery type: Time Critical
  * [Optional] Convert mobile numbers into international format: On
  * [Optional] Protect my account from fraud: On
* Save changes!

Interactive admin configuration:
```bash
occ twofactorauth:gateway:configure sms
```

Select `clickatellportal` and enter your API-Key.

### ClickSend
URL: https://www.clicksend.com
Stability: Experimental

Use the HTTPS service provided by clicksend.com for sending SMS.

Interactive admin configuration:
```bash
occ twofactorauth:gateway:configure sms
```

### SerwerSMS.pl
URL: https://serwersms.pl
Stability: Experimental

Use the SMS gateway provided by SerwerSMS.pl (HTTPS JSON API) for sending SMS. The sender name provided during configuration must be added and approved in the SerwerSMS.pl customer portal.

Interactive admin configuration (make sure to provide the full API login including the `webapi_` prefix):
```bash
occ twofactorauth:gateway:configure sms
```

[User Documentation]: https://nextcloud-twofactor-gateway.readthedocs.io/en/latest/User%20Documentation/

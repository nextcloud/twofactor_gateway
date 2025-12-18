<!--
 - SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 - SPDX-License-Identifier: AGPL-3.0-or-later
-->
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

This gateways allows you to send messages via the Signal protocol. By
default it tries to use the native HTTP endpoint of
[signal-cli](https://github.com/AsamK/signal-cli) which is available
since [signal-cli 0.11.5](https://github.com/AsamK/signal-cli/blob/master/CHANGELOG.md#0115---2022-11-07).
There maybe packages for your Linux distribution or Docker images, please refer to the installation
instructions provided by the [signal-cli](https://github.com/AsamK/signal-cli) project.

If the native HTTP endpoint of signal-cli is not available then it
tries to use the REST service provided by either
[stand-alone Python signal-cli REST API](https://morph027.gitlab.io/python-signal-cli-rest-api)
or
[Docker signal-cli REST API](https://github.com/bbernhard/signal-cli-rest-api)

*Note: Signal users are bound to phone numbers. If you already use Signal on your phone, you
need a separate number for the gateway's registration.*

Once you've set up the gateway, you can configure this app interactively:

```bash
occ twofactorauth:gateway:configure signal
```

You need to speficy the URL where the API provider is listening and
the Signal-account of the sending Signal user.

### WhatsApp
URL: whatsapp.com
Stability: Experimental

This gateway sends messages via WhatsApp Web through the internal service [wwebjs-api](https://github.com/avoylenko/wwebjs-api).

⚠️ Important: run the service only on your **internal network**. Do not expose it to the public Internet.

Interactive admin configuration:

```bash
occ twofactorauth:gateway:configure whatsapp
```

### GoWhatsApp
URL: https://github.com/aldinokemal/go-whatsapp-web-multidevice
Stability: Experimental

This gateway sends messages via WhatsApp Web using the [go-whatsapp-web-multidevice](https://github.com/aldinokemal/go-whatsapp-web-multidevice) API service.

⚠️ Important: run the service only on your **internal network**. Do not expose it to the public Internet.

Follow these steps to activate the GoWhatsApp authentication gateway:

1. Deploy the go-whatsapp-web-multidevice service.

   You can use Docker to run the service:
   ```bash
   docker run -d \
     --name whatsapp-api \
     -p 3000:3000 \
     -v whatsapp_data:/app/storages \
     -e WEBHOOK="" \
     aldinokemal2104/go-whatsapp-web-multidevice:latest
   ```

   Or use docker-compose:
   ```yaml
   services:
     whatsapp:
       image: aldinokemal2104/go-whatsapp-web-multidevice:latest
       ports:
         - "3000:3000"
       volumes:
         - whatsapp_data:/app/storages
       environment:
         - WEBHOOK=

   volumes:
     whatsapp_data:
   ```

   For more deployment options and configuration, see the [official documentation](https://github.com/aldinokemal/go-whatsapp-web-multidevice).

2. (Optional) Configure Basic Authentication.

   If you want to secure the API with basic authentication, you can set environment variables:
   ```bash
   -e BASICAUTH_USERNAME=your_username \
   -e BASICAUTH_PASSWORD=your_password
   ```

3. Configure the Nextcloud Gateway.

   Open a command shell on your Nextcloud server, navigate to the Nextcloud directory and run:
   ```bash
   occ twofactorauth:gateway:configure gowhatsapp
   ```

   You will be prompted for:
   * **Base URL**: The URL where the API is running (e.g., `http://whatsapp:3000` or `http://localhost:3000`)
   * **API Username**: If you configured basic auth (leave empty if not)
   * **API Password**: If you configured basic auth (leave empty if not)
   * **Phone number**: Your WhatsApp phone number with country code (e.g., `5511999998888`)

4. Connect to WhatsApp.

   After entering the configuration, the system will:
   * Request a pairing code from WhatsApp
   * Display the code on screen
   * Wait for you to enter the code in your WhatsApp app

   To link your device:
   * Open WhatsApp on your phone
   * Tap Menu (⋮) or Settings
   * Tap Linked Devices
   * Tap Link a Device
   * Select "Link with phone number instead"
   * Enter the displayed pairing code

5. Verify the connection.

   Once connected, the system will display:
   * Verified Name: Your WhatsApp account name
   * Status: Your WhatsApp status message
   * Connected Devices: Number of linked devices

   The GoWhatsApp authentication gateway is now ready. Follow the instructions in the [User Documentation] to activate the Gateway for specific users.

### Telegram

#### Telegram bot API
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

#### Telegram client API

URL: https://www.telegram.org/
Stability: Experimental

This gateways allows you to send messages using the Telegram client API. In order to send
messages, you have to create a Telegram application and use a Telegram account to
authenticate. After this, the sysadmin will need to authenticate with a Telegram account
to link the account to the Nextcloud Twofactor Gateway. The Telegram account will be used to send
authentication codes to users.

Follow these steps to activate the Telegram authentication gateway:

1. Create a Telegram application.

   * Open your web browser and navigate to https://my.telegram.org.
   * Login with your Telegram account.
   * Click on `API development tools` and fill out the form.
   * You will get an `api_id` and `api_hash` required to configure the gateway.
2. Activate the Nextcloud Twofactor Gateway for Telegram
   Open a command shell on your Nextcloud server, navigate to the Nextcloud directory and run
   the following command:
   ```bash
   occ twofactorauth:gateway:configure telegram
   Please enter your Telegram api_id: 123456
   Please enter your Telegram api_hash: a1b2c3d4e5f67890abcdef12345678
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

* Login with your credencials at [sms-gateway.clickatell.com](https://sms-gateway.clickatell.com/)
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

### XMPP Gateway
URL: https://xmpp.org/
Stability: Experimental

In order to use the service, you need to have an XMPP Account.
At this time, you'll also need an XMPP Service provider who runs a prosody XMPP-Server with either mod_rest or mod_post_msg or run your own prosody server.
Standard api path for mod_rest: https://xmpp.example.com/rest/message/chat/
Standard api path for mod_post_msg: https://jabber.example.net/msg/
See [mod_rest] and/or [mod_post_msg] for details.

Interactive admin configuration:
```bash
occ twofactorauth:gateway:configure xmpp
```

### SMSApi.com
Url: https://smsapi.com/de
Stability: Experimental

Use the HTTPS service provided by SMSApi.com for sending SMS.

Interactive admin configuration:
```bash
occ twofactorauth:gateway:configure sms
```

[User Documentation]: https://nextcloud-twofactor-gateway.readthedocs.io/en/latest/User%20Documentation/
[mod_rest]: https://modules.prosody.im/mod_rest.html
[mod_post_msg]: https://modules.prosody.im/mod_post_msg

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

This gateway allows you to send messages via the Signal protocol. It supports two backend styles:

- **[signal-cli-rest-api](https://github.com/bbernhard/signal-cli-rest-api)** (recommended): exposes a REST API
  with device-link support. When this style is detected, setup uses a guided QR-code flow — no manual account
  configuration is needed.
- **[signal-cli native HTTP endpoint](https://github.com/AsamK/signal-cli)**: available since signal-cli 0.11.5.
  Requires manually providing the sender account (phone number).
- **[stand-alone Python signal-cli REST API](https://morph027.gitlab.io/python-signal-cli-rest-api)**: legacy style,
  also requires a manual account entry.

*Note: Signal users are bound to phone numbers. If you already use Signal on your phone, you
need a separate number for the gateway's registration.*

#### Configuration via admin UI

The recommended way to configure Signal is through the Nextcloud admin interface:

1. Go to **Settings → Administration → Security**.
2. Find the Signal gateway instance and click **Set up**.
3. Enter the URL of your signal-cli-rest-api service and follow the guided QR-link wizard.

#### Configuration via command line

```bash
occ twofactorauth:gateway:configure signal
```

You will be prompted for the URL of the API provider. If the gateway is detected as a
`signal-cli-rest-api` instance, the wizard will display a QR code in the terminal for
device linking — no manual account entry is required. For other gateway styles, you will
also be prompted for the sender's Signal account (phone number).

### WhatsApp
URL: https://www.whatsapp.com
Stability: Experimental

This gateway sends messages via WhatsApp Web through the internal service [wwebjs-api](https://github.com/avoylenko/wwebjs-api).

⚠️ Important: run the service only on your **internal network**. Do not expose it to the public Internet.

Interactive admin configuration:

```bash
occ twofactorauth:gateway:configure whatsapp
```

### WhatsApp Business
URL: https://developers.facebook.com/docs/whatsapp/cloud-api/
Stability: Experimental

This gateway sends messages through the WhatsApp Cloud API (Meta Graph API) with guided auto-discovery for phone numbers and approved templates.

#### Before you start: System User token setup

1. In **Business Manager**, create a **System User** and set role to **Admin**.
2. Assign the app and WhatsApp account assets to this System User with full access.
3. Generate a System User token for your app with these **minimum permissions**:
   - `whatsapp_business_messaging` (required for sending messages)
   - `whatsapp_business_management` (strongly recommended; enables auto-discovery of phone numbers and templates)
4. Prefer **non-expiring tokens** for production environments.

⚠️ **Token permission note**: If your System User token lacks `whatsapp_business_management` permission, auto-discovery of WhatsApp Business Account (WABA) will be skipped. You can manually enter the WABA ID during setup as a fallback.

#### Configuration via admin UI (recommended)

The **recommended way** is to use the guided auto-discovery wizard:

1. Go to **Settings → Administration → Security**.
2. Click **Add provider configuration** or edit an existing **WhatsApp Business** instance.
3. Fill in the **Label** (internal name for this gateway) and **WhatsApp Graph API version** (optional, defaults to `v22.0`).
4. Enter your **WhatsApp Business access token** from the System User created above.
5. *(Optional)* If you know your **WABA ID** and want to skip auto-discovery, enter it manually. Otherwise, leave blank.
6. Click **Discover available resources**.
   - The wizard will auto-discover your WhatsApp Business Account(s), phone numbers, and approved templates.
   - *(Note: If discovery is blocked, check that your token includes `whatsapp_business_management` permission. You can still proceed by manually entering the WABA ID.)*

##### Wizard guide: Phone number selection

After discovery, you will see a list of phone numbers associated with your WABA. Each number shows:

- **Phone number** (e.g., `+55 21 97436-4077`)
- **Platform type** (e.g., `CLOUD_API`) and **Status** (e.g., `EXPIRED`, `VERIFIED`)

**Only phone numbers configured for WhatsApp Cloud API are selectable.** The wizard automatically filters and disables phone numbers that:

- Have `platform_type: NOT_APPLICABLE` (not configured for Cloud API)
- Have `platform_type: NOT_CAPABLE` (hardware limitation)

If your phone number appears grayed out, check in **WhatsApp Manager** that it is registered and verified for Cloud API use.

##### Wizard guide: Template selection

After selecting a phone number, the wizard displays available templates in the language matching your phone configuration. Each template shows:

- **Template name** (e.g., `libresign_invite_basic_v1`)
- **Template language** (e.g., `pt_BR`)
- **Approval status** (e.g., `APPROVED`, `PENDING`, `REJECTED`)

**Only approved templates are selectable.** The wizard automatically filters and disables templates that:

- Have status `REJECTED` (template did not pass Meta review; contact Meta Support)
- Have status `PENDING` (template is awaiting review; templates usually approve within 24 hours)
- Have status `DRAFT` (template not submitted; submit for review in WhatsApp Manager)

Templates must have exactly one body variable placeholder `{{1}}`. For Two Factor Gateway, this variable receives the verification code at send time.

##### Finalizing the setup

After selecting a template:

1. Review the **Preview** showing your Label, Phone Number, and Template Name.
2. Click **Save** to finalize the configuration.
3. Use the **Test** action in the gateway list to verify delivery.

#### Configuration via CLI (alternative)

```bash
occ twofactorauth:gateway:configure whatsappbusiness
```

You will be prompted for:

* **WhatsApp Graph API version**: for example `v25.0` (optional, defaults to `v22.0`)
* **WhatsApp Business access token**: a System User token with Cloud API permissions
* **WhatsApp Business account ID (WABA ID)** *(optional)*: obtained via auto-discovery or from Business Manager. If skipped, the wizard will attempt auto-discovery. If auto-discovery fails (permission denied), you must provide this ID.
* **Phone number ID**: the phone number ID from the discovered list (or manually obtained from WhatsApp Manager)
* **Template name**: the exact name of an approved template (e.g., `libresign_invite_basic_v1`)
* **Template language code**: the exact locale matching the template configuration (e.g., `pt_BR`, `en_US`, `es_ES`)

#### Obtaining the WABA ID manually (if auto-discovery fails)

If auto-discovery cannot retrieve your WABA:

1. Open **Business Manager** in your Meta app.
2. Navigate to **WhatsApp Manager**.
3. In the **Settings** tab, look for **Accounts** or **Contas do WhatsApp**.
4. Copy the numeric ID of your WhatsApp Business Account.

Example WABA ID: `1262137285897957`

#### Practical recommendations

##### For phone numbers

- If your current business number is already in use in the WhatsApp app on a personal device, it **cannot simultaneously be used with Cloud API**. Use a dedicated new number for API usage.
- A dedicated API number reduces migration risk, avoids production interruption, and makes rollback easier if you need to change providers later.
- Ensure the phone number is **verified** in WhatsApp Manager and configured for **Cloud API** (not the legacy On-Premises API).

##### For templates

- Templates must be **created and submitted for approval** in **WhatsApp Manager → Message Templates** before they appear in the wizard.
- Meta usually approves template requests within 24 hours. Monitor your email for approval/rejection notifications.
- To modify a rejected template, create a new version (e.g., rename from `template_v1` to `template_v2`) and resubmit.
- The template body must include exactly one variable placeholder: `{{1}}`

##### For long-term reliability

- Use **non-expiring tokens** for production environments to avoid recurring reconfiguration.
- Monitor token expiration in Business Manager and set calendar reminders for token refresh if using temporary tokens.
- If Meta returns delivery errors like `Unsupported post request`, verify that the token, app, WhatsApp account, and phone number belong to the **same business context**.

#### Validating the setup

```bash
occ twofactorauth:gateway:test <uid> whatsappbusiness "<destination_phone_in_e164>"
```

For example:
```bash
occ twofactorauth:gateway:test user1 whatsappbusiness "+5585988776655"
```

If the test fails with `Unsupported post request`, check:

* The token belongs to the same app/business as the phone number.
* The phone number is active and verified for Cloud API.
* The destination phone is allowed (test numbers in development mode, real numbers in production mode).
* The template was approved by Meta.

#### Troubleshooting wizard errors

| Error | Likely cause | Solution |
|-------|-------------|----------|
| `Token is invalid` | Token format incorrect or revoked | Regenerate a new System User token in Business Manager |
| `Token missing WhatsApp management permission` | Token lacks `whatsapp_business_management` | Regenerate token with the required permission scope |
| `No WABA found for this token` | Token's business context has no WhatsApp accounts | Create or request access to a WhatsApp Business Account in Meta |
| `No phone numbers found` | WABA has no registered numbers OR permission denied | Register a phone number in WhatsApp Manager; or manually enter WABA ID if auto-discovery is blocked |
| `No approved templates found` | No templates approved yet OR all templates are rejected/pending | Create and submit templates for approval in WhatsApp Manager |
| `This phone number is not configured for WhatsApp Cloud API` | Phone is registered but using legacy API only | Verify phone in WhatsApp Manager for Cloud API compatibility |
| `Template is not approved` | Template status is PENDING, REJECTED, or DRAFT | Submit template for approval or wait 24 hours for approval processing

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
     -e WHATSAPP_WEBHOOK="" \
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
         - WHATSAPP_WEBHOOK=

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

6. (Optional, recommended) Enable hybrid monitoring (webhook + polling fallback).

   The app now supports a hybrid strategy:
   * Webhook: fast-path trigger for session health checks
   * Polling job: fallback safety net every 5 minutes

   Alert logic (short version):
   * **CRITICAL**: immediate alert when the session is detected as `logged_out`.
   * **WARNING**: risk-score alert for instability (for example repeated `disconnected`/`unreachable` states) inside the configured time window.

   Configure webhook settings in the gateway config (or via app config):
   * **webhook_hybrid_enabled**: `1` to enable
   * **webhook_secret**: shared HMAC secret
   * **webhook_min_check_interval**: minimum seconds between webhook-triggered checks (default `30`)

   You can also set these values directly:
   ```bash
   occ config:app:set twofactor_gateway gowhatsapp_webhook_hybrid_enabled --value="1"
   occ config:app:set twofactor_gateway gowhatsapp_webhook_secret --value="your-secret-key"
   occ config:app:set twofactor_gateway gowhatsapp_webhook_min_check_interval --value="30"
   ```

   Configure GoWhatsApp to send webhooks to:
   * `https://<your-nextcloud>/index.php/apps/twofactor_gateway/gowhatsapp/webhook`

   Security requirements:
   * Configure `WHATSAPP_WEBHOOK_SECRET` (or `--webhook-secret`) on GoWhatsApp.
   * The app validates `X-Hub-Signature-256` using `sha256=<digest>`.
   * Keep HTTPS enabled and restrict ingress to trusted sources whenever possible.

### Telegram

#### Telegram bot API
URL: https://www.telegram.org/
Stability: Unstable

This gateway allows you to send messages via the Telegram protocol. In order to send messages,
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
   Open **Administration settings -> Security** in Nextcloud and create a Telegram gateway instance.

   Choose the **Telegram Bot API** provider in the gateway form and fill in the bot token provided by BotFather.

   Save the instance and, if needed, mark it as the default Telegram gateway.

   The Telegram authentication gateway has now successfully been set-up. Follow the instructions
   in the [User Documentation] to activate the Gateway for a specific user.

#### Telegram client API

URL: https://www.telegram.org/
Stability: Experimental

This gateway allows you to send messages using the Telegram client API. In order to send
messages, you have to create a Telegram application and then link a Telegram account in the
native Nextcloud admin setup wizard. The linked Telegram account will be used to send
authentication codes to users.

Follow these steps to activate the Telegram authentication gateway:

1. Create a Telegram application.

   * Open your web browser and navigate to https://my.telegram.org.
   * Login with your Telegram account.
   * Click on `API development tools` and fill out the form.
   * You will get an `api_id` and `api_hash` required to configure the gateway.
2. Activate the Nextcloud Twofactor Gateway for Telegram
   Open **Administration settings -> Security** in Nextcloud and create a Telegram gateway instance.

   Choose the **Telegram Client API** provider in the gateway form and enter the `api_id` and `api_hash` from https://my.telegram.org.

   Start the guided setup in the Telegram section of the form and scan the QR code shown by Nextcloud to link the Telegram account.

   After the wizard confirms the linked account, save the instance and, if needed, mark it as the default Telegram gateway.

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

### Voipbuster
URL: https://voipbuster.com
Stability: Experimental

Use the HTTPS service provided by Voipbuster.com for sending SMS.

Interactive admin configuration:
```bash
occ twofactorauth:gateway:configure sms
```

### seven.io
<a id="sms77io"></a>

URL: https://www.seven.io (formerly sms77.io)
Stability: Experimental

Use the SMS gateway provided by **seven** (formerly sms77.io) for sending SMS.
Create an API key in the [seven developer area](https://dashboard.seven.io/developer).

The provider is still listed as `sms77io` in the interactive picker so that
existing configurations keep working after the rebrand.

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
URL: https://smsapi.com/de
Stability: Experimental

Use the HTTPS service provided by SMSApi.com for sending SMS.

Interactive admin configuration:
```bash
occ twofactorauth:gateway:configure sms
```

### ClockworkSMS
URL: https://www.clockworksms.com/
Stability: Experimental

Use the HTTP API provided by ClockworkSMS for sending SMS.

Interactive admin configuration:
```bash
occ twofactorauth:gateway:configure sms
```

Select `clockworksms` and enter your API token.

### Huawei E3531
Stability: Experimental

Use the local HTTP API of a Huawei E3531 USB stick connected to the server.
No external service or account is required.

Interactive admin configuration:
```bash
occ twofactorauth:gateway:configure sms
```

Select `huawei_e3531` and enter the base URL of the device (default: `http://192.168.8.1/api`).

### SipGate
URL: https://www.sipgate.de/
Stability: Experimental

Use the SMS API provided by SipGate for sending SMS via `https://api.sipgate.com/v2/sessions/sms`.

Interactive admin configuration:
```bash
occ twofactorauth:gateway:configure sms
```

Select `sipgate` and enter:
* **Token ID** (`token-id` from your SipGate personal access token)
* **Access token** (the token secret)
* **Web SMS extension** (the `sXXX` extension configured for web SMS in your SipGate account)

[User Documentation]: https://nextcloud-twofactor-gateway.readthedocs.io/en/latest/User%20Documentation/
[mod_rest]: https://modules.prosody.im/mod_rest.html
[mod_post_msg]: https://modules.prosody.im/mod_post_msg

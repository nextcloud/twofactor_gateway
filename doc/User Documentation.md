# User Documentation

## Gateways

Here you can find the setup instructions for the currently supported gateways.

### playSMS
URL: https://playsms.org/
Stability: Experimental

Use the Webservices provided by playSMS for sending SMS.

### SMSGlobal 
URL: https://www.smsglobal.com
Stability: Experimental

Use the Webservices provided by SMSGlobal for sending SMS.

### Signal
URL: https://www.signal.org/
Stability: Experimental

This gateways allows you to send messages via the Signal protocol.

### Telegram
URL: https://www.telegram.org/
Stability: Unstable

This gateway allows you to send messages via the Telegram protocol. Once the administrator
has finished the general Telegram authentication gateway setup (Check out the [Administrator
Documentation] for further details), you need to follow these instructions to activate the
use of the gateway for a user:

1. Find out your own Telegram user id

   Open your Telegram client, search for `@my_id_bot` and start a conversation.

   In reply **@my_id_bot** provides your personal id to you, e.g. `998877665`.

   **Remember:** Keep your personal id private unless you trust a person!

2. Contact the newly created bot

   * Open your Telegram client, search for `@my_nc_bot` (The administrator provides the
     bot name to you) and start a new conversation.
   * Send e.g. `Hello`.

3. Activate the authentication gateway for a user

   * Log in to Nextcloud with the user you want to enable the twofactor gateway for.
   * Open **Settings -> Personal -> Security** and navigate to the `Message gateway
     second-factor auth` configuration.
   * Press the `Enable` button under the Telegram label.
   * Enter the previously evaluated personal user id, e.g. `998877665` and press the
     `Verify` button.
   * Now you should receive a Telegram message with your Nextcloud authentication code,
     e.g. `123456`.
   * Enter the received Nextcloud authentication code and press the `Confirm` button.

   Finally the system should state `Your account was successfully configured to receive
   messages via Telegram.`

   **Remember:** As a next step you should immediately generate Two-Factor Authentication
   backup codes to be able to login, in case the Telegram service fails.

[Administrator Documentation]: https://nextcloud-twofactor-gateway.readthedocs.io/en/latest/Admin%20Documentation/

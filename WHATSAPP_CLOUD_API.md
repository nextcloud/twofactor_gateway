# WhatsApp Cloud API Integration Guide

## Overview

The `twofactor_gateway` project now supports two WhatsApp drivers:

1. **WhatsApp Cloud API (Meta)** - Modern, official Meta/Facebook API
2. **WebSocket Driver** - Legacy support for WhatsApp Web emulation

Both drivers coexist and automatically detect which one to use based on stored configuration.

## Architecture

### Driver Pattern

```
Gateway.php (abstraction)
    ↓
DriverFactory.create()
    ↓
    ├─ CloudApiDriver (Meta API v14.0+)
    └─ WebSocketDriver (WebSocket endpoint)
```

### File Structure

```
lib/Provider/Channel/WhatsApp/
├── Provider.php (unchanged)
├── Gateway.php (refactored - abstraction layer)
├── Drivers/
│   ├── IWhatsAppDriver.php (interface)
│   ├── CloudApiDriver.php (NEW - Meta API)
│   └── WebSocketDriver.php (refactored from Gateway.php)
└── Config/
    └── DriverFactory.php (NEW - auto-detection)
```

## Configuration

### WhatsApp Cloud API (Meta)

Configure using the CLI:

```bash
occ twofactor_gateway:configure whatsapp
```

When prompted, provide:

1. **Phone Number ID**: From your Meta Business Account
   - Find in: Business Manager → WhatsApp Manager → Phone Numbers
   
2. **Business Account ID**: Your Meta Business Account ID
   - Find in: Business Manager → Account Settings
   
3. **API Access Token**: Graph API token with `whatsapp_business_messaging` permission
   - Create in: Business Manager → Apps → Your App → Tools → Graph API Explorer
   - Or use: https://developers.facebook.com/docs/whatsapp/cloud-api/get-started/
   
4. **API Endpoint** (optional): Default is `https://graph.facebook.com`

Configuration is automatically detected by the factory. If `api_key` is set, CloudApiDriver is used.

### WebSocket Driver (Legacy)

Configuration is automatically detected from existing `whatsapp_base_url` setting.

Configure using the CLI:

```bash
occ twofactor_gateway:configure whatsapp
```

Provide the base URL to your WhatsApp API endpoint and scan the QR code.

## Usage

### Sending Messages

Both drivers work transparently - the Gateway automatically selects the correct driver:

```php
// In your code
$gateway->send($phoneNumber, $message);
// Driver is automatically selected based on config
```

### For Users

1. Enable WhatsApp two-factor authentication
2. Verify your phone number
3. When logging in, you'll receive a verification code via WhatsApp

## Benefits of Cloud API

- ✅ Official Meta API - stable and supported
- ✅ No external server required - scales independently
- ✅ Better reliability - managed by Meta
- ✅ No QR code scanning - direct API integration
- ✅ Webhook support for delivery status (future)
- ✅ Template messaging support (future)

## Migration from WebSocket

Users can migrate gradually:

1. **New installations**: Use Cloud API (recommended)
2. **Existing users**: Continue using WebSocket or switch to Cloud API
3. **No breaking changes**: Both drivers coexist without conflicts

To switch from WebSocket to Cloud API:

1. Configure Cloud API credentials via CLI
2. The factory will automatically prefer Cloud API if both are configured
3. Remove old WebSocket configuration if no longer needed

## Testing

### Unit Tests

```bash
cd /home/mohr/git/twofactor_gateway
./vendor/bin/phpunit tests/php/Unit/Provider/Channel/WhatsApp/
```

### Manual Testing

```bash
# Test Cloud API configuration
occ twofactor_gateway:test whatsapp 555-1234567

# Check configuration status
occ twofactor_gateway:status whatsapp
```

## Troubleshooting

### Cloud API Issues

**Error: "Invalid Cloud API credentials or endpoint"**
- Verify API token is correct
- Check token has `whatsapp_business_messaging` permission
- Verify phone number ID matches your business account

**Error: "The phone number is not registered on WhatsApp"**
- Ensure the recipient has WhatsApp installed
- Phone number format should be: `+country_code_number`
- Example: `+1 (555) 123-4567` → cleaned to `15551234567`

**Error: "Failed to connect to WhatsApp API"**
- Check internet connectivity
- Verify API endpoint URL is correct
- Check firewall/proxy settings

### WebSocket Issues

**Error: "WhatsApp session is not connected"**
- Ensure the WebSocket server is running
- Verify base URL is reachable
- Scan QR code again to reconnect session

## Code Examples

### Detecting Which Driver Is Active

```php
$driver = $factory->create(); // Automatically detects correct driver

// Check driver type
if ($driver instanceof CloudApiDriver) {
    // Using Meta API
} else if ($driver instanceof WebSocketDriver) {
    // Using WebSocket
}
```

### Extending with Custom Driver

To add a new driver (e.g., third-party WhatsApp API):

1. Implement `IWhatsAppDriver` interface
2. Add to `DriverFactory::DRIVERS` array
3. Implement `detectDriver()` to return class name when applicable

```php
class MyCustomDriver implements IWhatsAppDriver {
    public function send(string $identifier, string $message, array $extra = []): void {
        // Custom implementation
    }

    public static function detectDriver(array $storedConfig): ?string {
        if (!empty($storedConfig['custom_key'])) {
            return self::class;
        }
        return null;
    }

    // ... implement other required methods
}
```

## Configuration Storage

Configurations are stored in Nextcloud's app config with prefixes:

| Setting | Key |
|---------|-----|
| **Cloud API** | |
| Phone Number ID | `whatsapp_cloud_phone_number_id` |
| Business Account ID | `whatsapp_cloud_business_account_id` |
| API Key | `whatsapp_cloud_api_key` |
| API Endpoint | `whatsapp_cloud_api_endpoint` |
| **WebSocket** | |
| Base URL | `whatsapp_base_url` |

All stored in app: `twofactor_gateway`

## Future Enhancements

- [ ] Webhook support for delivery status updates
- [ ] Message template support (Meta approved templates)
- [ ] Multi-language support in prompts
- [ ] Media message support
- [ ] Group chat support (if Meta API adds it)
- [ ] Fallback mechanism (try secondary driver if primary fails)

## Support

For issues or questions:

1. Check the troubleshooting section above
2. Review Meta's WhatsApp Cloud API documentation
3. Open an issue on the GitHub repository

## References

- [Meta WhatsApp Cloud API Docs](https://developers.facebook.com/docs/whatsapp/cloud-api/)
- [Graph API Explorer](https://developers.facebook.com/docs/graph-api/using-graph-api)
- [Business Manager Setup](https://www.facebook.com/business/help/898752960195806)

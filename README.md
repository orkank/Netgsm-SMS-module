# SMS Integration for Magento 2

This module provides SMS functionality for Magento 2 using the Netgsm API, allowing bulk SMS sending to customers based on various filters.

## Important Note

This module requires the Netgsm IYS Module to be installed first:
- Repository: [Netgsm-IYS-module](https://github.com/orkank/Netgsm-IYS-module/)
- This dependency is required for proper phone number validation and IYS compliance

## Features

- Bulk SMS sending to customers (1:n and n:n support)
- Customer filtering options:
  - Customer groups
  - Customer type (guest/registered)
  - Order history (last 30, 90, 180, 365 days)
  - Minimum purchase count
- SMS status tracking with detailed modal view
- Cron-based processing with lock mechanism
- Detailed reporting and status monitoring
- Automatic cleanup of old SMS records (2 months retention)
- IYS (İleti Yönetim Sistemi) integration

## Manual Installation

1. First, install the required IYS module:
```bash
mkdir -p app/code/IDangerous/NetgsmIYS
# Copy Netgsm-IYS-module files to this directory
```

2. Then install this SMS module:
```bash
mkdir -p app/code/IDangerous/Sms
# Copy SMS module files to this directory
```

3. Enable both modules:
```bash
php bin/magento module:enable IDangerous_NetgsmIYS
php bin/magento module:enable IDangerous_Sms
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento cache:clean
```

## Configuration

1. Navigate to: Admin Panel > Stores > Configuration > IDangerous > SMS Settings

2. Available settings:
   - Message Header (Sender ID)
   - Debug Logging (Enable/Disable)

## Terminal Commands

Process pending SMS jobs manually:
```bash
php bin/magento idangerous:sms:process
```

Clean old SMS records (older than 2 months):
```bash
php bin/magento idangerous:sms:clean
```

## Monitoring SMS Sending

### Dashboard
1. Go to Marketing > SMS > SMS Dashboard
2. View all SMS campaigns and their statuses
3. Status types:
   - Pending: Waiting to be processed
   - Processing: Currently being sent
   - Completed: All messages sent
   - Failed: Error occurred during sending

### Detailed View
Click on any campaign row to view:
- Individual message statuses
- Delivery timestamps
- Error messages (if any)
- Recipient details

## Logging

Logs are stored in: `var/log/idangerous_sms.log`

Monitor logs in real-time:
```bash
tail -f var/log/idangerous_sms.log
```

Common log entries:
- SMS sending attempts
- API responses
- Processing errors
- Cron execution status

## Troubleshooting

1. **SMS Processing Issues**
   - Check `idangerous_sms.log`
   - Verify cron is running
   - Ensure message header is configured

2. **Lock Mechanism**
   - Processing lock expires after 1 hour
   - Check if process is stuck with: `php bin/magento idangerous:sms:status`

## Database Tables

- `iys_data`: Stores recipient information
- `idangerous_bulk_sms`: SMS campaigns
- `idangerous_bulk_sms_detail`: Individual SMS records (2-month retention)

## SMS Types and Usage

### Regular SMS
```php
/** @var \IDangerous\Sms\Model\Api\SmsService $smsService */
$result = $smsService->sendSms(
    '5321234567',  // Phone number
    'Your message' // Message content
);

if ($result['success']) {
    // SMS sent successfully
    $messageId = $result['bulkId'];
} else {
    // Handle error
    $errorMessage = $result['message'];
}
```

### OTP (One-Time Password) SMS
```php
/** @var \IDangerous\Sms\Model\Api\SmsService $smsService */
$result = $smsService->sendOtpSms(
    '5321234567',                          // Phone number
    'Your verification code is: 123456'     // Message content (max 160 chars)
);

if ($result['success']) {
    // OTP SMS sent successfully
    $jobId = $result['jobId'];
} else {
    // Handle error
    $errorMessage = $result['message'];
}
```

**OTP SMS Notes:**
- Maximum message length: 160 characters
- Returns unique jobId for tracking
- Higher priority delivery
- Requires OTP package from Netgsm
- Rate limit: 100 messages per minute

## Support

For technical support:
1. Check the logs at `var/log/idangerous_sms.log`
2. Verify configuration in admin panel
3. Ensure proper permissions are set

## License

[MIT License](LICENSE.md)

[Developer: Orkan Köylü](orkan.koylu@gmail.com)
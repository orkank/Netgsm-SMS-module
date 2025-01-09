# IDangerous SMS Integration for Magento 2

This module provides SMS functionality for Magento 2 using the IDangerous API, allowing bulk SMS sending to customers based on various filters.

## Features

- Bulk SMS sending to customers
- Customer filtering by:
  - Customer groups
  - Customer type (guest/registered)
  - Order history
  - Purchase count
- SMS status tracking with detailed modal view
- Message queue based processing
- Detailed reporting
- Manual and automated processing
- Automatic cleanup of old SMS records (2 months expiry)

## Installation

### Via Composer
```bash
composer require idangerous/module-sms
php bin/magento module:enable IDangerous_Sms
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento cache:clean
```

### Manual Installation
1. Create directory `app/code/IDangerous/Sms`
2. Download and extract module in that directory
3. Enable the module:
```bash
php bin/magento module:enable IDangerous_Sms
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento cache:clean
```

## Configuration

1. Go to Admin Panel > Stores > Configuration > IDangerous > SMS Settings
2. Configure:
   - API Username
   - API Password
   - Sender ID
   - Other settings

## Usage

### Sending Bulk SMS

1. Navigate to Marketing > SMS > Send Bulk SMS
2. Fill in:
   - Message content
   - Select customer filters:
     - Customer groups
     - Customer type
     - Order period
     - Minimum purchase count
3. Click "Send Bulk SMS"
4. Monitor progress in the grid

### Monitoring SMS Status

1. Go to Marketing > SMS > SMS Dashboard
2. View all SMS campaigns and their statuses
3. Click on Message ID to view detailed delivery status
4. Monitor success/failure rates

### Data Retention

- SMS delivery details are automatically removed after 2 months
- This helps maintain database performance and comply with data retention policies
- Historical data can be exported before expiry if needed

### Manual Processing

To manually process pending SMS jobs:

```bash
php bin/magento queue:consumers:start idangerousSmsConsumer
```

## Message Queue

The module uses Magento's Message Queue framework for asynchronous processing:
- Queue: `idangerous_sms`
- Consumer: `idangerousSmsConsumer`
- Topic: `idangerous.sms.bulk`

## Database Tables

- `idangerous_bulk_sms`: Stores bulk SMS campaigns
- `idangerous_bulk_sms_detail`: Stores individual SMS send attempts (with 2-month expiry)

## Commands

```bash
# Start message queue consumer
php bin/magento queue:consumers:start idangerousSmsConsumer

# Clear module cache
php bin/magento cache:clean

# Recompile if you make code changes
php bin/magento setup:di:compile

# Check module status
php bin/magento module:status IDangerous_Sms
```

## Troubleshooting

1. **SMS Not Sending**
   - Check API credentials in configuration
   - Verify customer filters
   - Check logs for errors
   - Ensure message queue consumer is running

2. **Processing Stuck**
   - Check message queue status
   - Run consumer manually
   - Check system logs

3. **Permission Issues**
   - Verify admin user permissions
   - Check ACL configuration

## Logging

###

## Development

### Adding New Features

1. Create new controllers in `Controller/Adminhtml/`
2. Add routes in `etc/adminhtml/routes.xml`
3. Update ACL in `etc/acl.xml`

### Custom Filters

Extend `BulkRecipientService` to add new filtering options.

### Testing

1. Enable developer mode:

```bash
php bin/magento deploy:mode:set developer
```

2. Monitor logs while testing:

```bash
tail -f var/log/idangerous_sms.log
```

## Support

For issues and feature requests, please:
1. Check the logs
2. Review configuration
3. Contact module support

## License

[MIT License](LICENSE.md)
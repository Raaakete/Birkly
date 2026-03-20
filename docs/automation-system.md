# Automation System Documentation

## Overview

The Birkly Automation System allows you to create automated workflows that trigger actions based on events in your CMS. It follows a simple trigger → conditions → actions model.

## Core Concepts

### Triggers
Events that start an automation workflow:
- **Entry Created**: Fires when a new entry is created
- **Entry Updated**: Fires when an entry is updated
- **Entry Deleted**: Fires when an entry is deleted
- **Status Changed**: Fires when an entry status changes
- **Scheduled**: Fires on a schedule (hourly, daily, weekly, monthly, or custom cron)
- **Plugin Event**: Fires when a plugin broadcasts an event

### Conditions
Optional filters that determine if an automation should run:
- **Field Match**: Check if a field matches a value (equals, contains, greater than, etc.)
- **Status Check**: Check if entry has a specific status
- **Collection Filter**: Check if entry belongs to a specific collection

### Actions
What happens when an automation runs:
- **Send Email**: Send an email notification
- **Send Webhook**: Send HTTP request to a webhook URL
- **Set Entry Status**: Change the status of an entry
- **Set Entry Field**: Set a field value on an entry
- **Log Message**: Log a message (for testing)

## Creating an Automation

### Via Admin UI

1. Navigate to **Automation** in the admin sidebar
2. Click **Create Automation**
3. Configure:
   - **Name**: Descriptive name for the automation
   - **Enabled**: Toggle to enable/disable
   - **Trigger**: Select trigger type and configure
   - **Conditions** (optional): Add conditions to filter when automation runs
   - **Actions**: Add one or more actions
   - **Stop on Failure**: Whether to stop if an action fails

4. Click **Save Automation**

### Example: Send Email When Entry is Published

1. **Trigger**: Status Changed
   - From: `draft`
   - To: `published`
   
2. **Action**: Send Email
   - To: `{{entry.author_email}}`
   - Subject: `Your entry "{{entry.title}}" has been published`
   - Body: `Congratulations! Your entry has been published.`

## Template Variables

Actions support template variables using `{{variable}}` syntax:

- `{{entry.title}}` - Entry title
- `{{entry.id}}` - Entry ID
- `{{entry.status}}` - Entry status
- `{{collection}}` - Collection slug
- `{{user.email}}` - Current user email (if available)

## Scheduled Automations

Scheduled automations require a cron job to process them. Add this to your crontab:

```bash
# Process scheduled automations every minute
* * * * * curl -s http://your-site.com/api/automation.php?action=process_scheduled > /dev/null

# Process automation queue every 5 minutes
*/5 * * * * curl -s http://your-site.com/api/automation.php?action=process_queue > /dev/null
```

## Plugin Integration

Plugins can register their own automation blocks and broadcast events:

### Registering Blocks

```php
// In plugin.php
register_automation_block('trigger', 'payment_succeeded', [
    'name' => 'Payment Succeeded',
    'description' => 'Fires when a payment is successful',
    'category' => 'Payment',
    'schema' => [
        'amount' => [
            'type' => 'number',
            'label' => 'Minimum Amount'
        ]
    ],
    'handler' => function($config, $context) {
        $amount = $context['payment']['amount'] ?? 0;
        $min_amount = $config['amount'] ?? 0;
        return $amount >= $min_amount;
    }
]);
```

### Broadcasting Events

```php
// Broadcast an event that triggers automations
broadcast_plugin_event('payment_succeeded', [
    'payment' => [
        'amount' => 100.00,
        'currency' => 'USD',
        'user_id' => 123
    ]
]);
```

## API Reference

### List Automations
```
GET /api/automation.php?action=list
```

### Get Automation
```
GET /api/automation.php?action=get&automation_id=auto_123
```

### Create Automation
```
POST /api/automation.php?action=create
{
    "name": "My Automation",
    "enabled": true,
    "trigger": {
        "type": "entry_created",
        "config": {}
    },
    "actions": [
        {
            "type": "email",
            "config": {
                "to": "admin@example.com",
                "subject": "New entry created",
                "body": "A new entry was created: {{entry.title}}"
            }
        }
    ]
}
```

### Update Automation
```
POST /api/automation.php?action=update
{
    "automation_id": "auto_123",
    "enabled": false
}
```

### Delete Automation
```
POST /api/automation.php?action=delete
{
    "automation_id": "auto_123"
}
```

### Get Execution Logs
```
GET /api/automation.php?action=get_logs&automation_id=auto_123&limit=50
```

## Rate Limiting

Automations have rate limiting to prevent excessive executions:
- Default: 100 executions per hour per automation
- Configurable per automation
- Rate limit status available via API

## Safety Features

- **Loop Prevention**: Automations cannot trigger themselves
- **Error Isolation**: Failed actions don't crash the system
- **Retry Logic**: Failed jobs retry with exponential backoff (up to 3 times)
- **Rate Limiting**: Prevents spam and excessive resource usage
- **Execution Logging**: All executions are logged for debugging

## Best Practices

1. **Test First**: Use test mode before enabling automations
2. **Start Simple**: Begin with simple automations and add complexity gradually
3. **Monitor Logs**: Regularly check execution logs for errors
4. **Use Conditions**: Add conditions to prevent unnecessary executions
5. **Rate Limits**: Set appropriate rate limits for high-frequency automations
6. **Error Handling**: Use "Stop on Failure" for critical workflows

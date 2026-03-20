# Enhanced Plugin System Documentation

## Overview

The Birkly plugin system has been significantly enhanced to provide comprehensive plugin management capabilities. This system allows for plugin activation/deactivation, settings management, updates, and proper state handling.

## Key Features

### 1. Plugin State Management
- **Activation/Deactivation**: Plugins can be activated or deactivated without losing data
- **State Persistence**: Plugin states are stored in `settings/plugins.json`
- **Graceful Handling**: Deactivated plugins don't execute code but preserve all data

### 2. Plugin Metadata System
Each plugin should include a `plugin.json` file with metadata:

```json
{
  "name": "Plugin Name",
  "version": "1.0.0",
  "description": "Plugin description",
  "author": "Author Name",
  "requires": "1.0.0",
  "hooks": ["hook1", "hook2"],
  "permissions": ["permission1", "permission2"],
  "settings_schema": {
    "setting_key": {
      "type": "boolean|string|integer|number",
      "default": "default_value",
      "label": "Human readable label",
      "description": "Setting description",
      "options": ["option1", "option2"] // for string type with options
    }
  }
}
```

### 3. Plugin Settings System
- **Schema-based**: Settings are defined in `plugin.json`
- **Type Validation**: Automatic validation of setting types
- **UI Generation**: Settings UI is automatically generated
- **Persistent Storage**: Settings are stored per plugin

### 4. Security Considerations

#### When Deactivating Plugins:
- **Data Preservation**: All plugin data remains intact
- **UI Removal**: Plugin navigation items disappear
- **API Disabling**: Plugin API endpoints become inaccessible
- **Hook Disabling**: Plugin hooks stop executing
- **Dashboard Stats**: Plugin stats are hidden

#### When Activating Plugins:
- **Validation**: Plugins are validated before activation
- **Dependency Check**: System checks for required dependencies
- **Permission Verification**: Ensures proper file permissions
- **Hook Registration**: All plugin hooks are registered

#### Security Best Practices:
- **File Validation**: Plugin files are validated before loading
- **Permission Checks**: File permissions are verified
- **CSRF Protection**: All plugin actions require CSRF tokens
- **Input Sanitization**: All plugin settings are sanitized
- **Logging**: All plugin actions are logged for audit

## Plugin Structure

### Directory Structure
```
plugins/
├── plugin-name/
│   ├── plugin.json          # Plugin metadata and settings schema
│   ├── plugin.php           # Main plugin file
│   ├── admin/               # Admin interface files
│   ├── api/                 # API endpoints
│   ├── collections/         # Content collections
│   └── assets/              # CSS, JS, images
└── standalone-plugin.php    # Standalone plugin file
```

### Plugin File Requirements
- **plugin.php**: Main plugin bootstrap file
- **plugin.json**: Metadata and settings schema (optional but recommended)
- **Proper Permissions**: Files must be readable by the web server

## Plugin Development

### Basic Plugin Template

```php
<?php
// plugins/my-plugin/plugin.php

// Check if plugin is active
if (!get_plugin_state('my-plugin')) {
    return;
}

// Register collections
register_collection('my_collection', '/plugins/my-plugin/collections/my_collection');

// Register API endpoints
register_api_endpoint('my_api', __DIR__ . '/api/my_api.php');

// Register admin pages
register_admin_page('my_admin', '/plugins/my-plugin/admin/my_admin.html');

// Register hooks
register_hook('after_content_save', function($collection, $entry) {
    // Your logic here
});

// Add navigation items
register_hook('admin_navigation', function(&$nav_items) {
    $nav_items[] = [
        'title' => 'My Plugin',
        'url' => '/admin/my_admin.html',
        'icon' => 'icon-name',
        'permission' => 'required_permission'
    ];
});

// Add dashboard stats
register_hook('dashboard_stats', function(&$stats) {
    $stats[] = [
        'title' => 'My Plugin Stats',
        'value' => 42,
        'icon' => 'icon-name',
        'color' => 'blue'
    ];
});
```

### Settings Schema Example

```json
{
  "enable_feature": {
    "type": "boolean",
    "default": true,
    "label": "Enable Feature",
    "description": "Enable or disable the main feature"
  },
  "max_items": {
    "type": "integer",
    "default": 100,
    "label": "Maximum Items",
    "description": "Maximum number of items to display"
  },
  "display_mode": {
    "type": "string",
    "default": "list",
    "label": "Display Mode",
    "description": "How to display items",
    "options": ["list", "grid", "table"]
  }
}
```

### Using Plugin Settings

```php
// Get a setting value
$enable_feature = get_plugin_setting('my-plugin', 'enable_feature', true);

// Set a setting value
set_plugin_setting('my-plugin', 'max_items', 50);
```

## Plugin Management API

### Endpoints

#### List Plugins
```
GET /api/plugins.php?action=list
```

#### Activate Plugin
```
POST /api/plugins.php
{
  "action": "activate",
  "plugin_id": "plugin-name",
  "_csrf": "csrf_token"
}
```

#### Deactivate Plugin
```
POST /api/plugins.php
{
  "action": "deactivate",
  "plugin_id": "plugin-name",
  "_csrf": "csrf_token"
}
```

#### Get Plugin Settings
```
GET /api/plugins.php?action=get_settings&plugin_id=plugin-name
```

#### Save Plugin Settings
```
POST /api/plugins.php
{
  "action": "save_settings",
  "plugin_id": "plugin-name",
  "settings": {
    "setting_key": "value"
  },
  "_csrf": "csrf_token"
}
```

#### Delete Plugin
```
POST /api/plugins.php
{
  "action": "delete",
  "plugin_id": "plugin-name",
  "_csrf": "csrf_token"
}
```

#### Check for Updates
```
GET /api/plugins.php?action=check_updates&plugin_id=plugin-name
```

## Available Hooks

### Core Hooks
- `after_content_save`: Triggered after content is saved
- `admin_navigation`: Add items to admin navigation
- `dashboard_stats`: Add statistics to dashboard
- `plugin_activated`: Triggered when a plugin is activated
- `plugin_deactivated`: Triggered when a plugin is deactivated

### Hook Usage

```php
// Register a hook
register_hook('hook_name', function($param1, $param2) {
    // Your logic here
});

// Trigger a hook
do_action('hook_name', $param1, $param2);
```

## Plugin Management UI

The plugin management interface is available at **Settings > Plugin Management** and provides:

- **Plugin List**: View all installed plugins with status
- **Activate/Deactivate**: Toggle plugin states
- **Settings**: Configure plugin settings (if available)
- **Updates**: Check for plugin updates
- **Delete**: Remove plugins completely

## Best Practices

### 1. Plugin Development
- Always check plugin state before executing code
- Use proper error handling
- Validate all inputs
- Follow security best practices
- Document your plugin thoroughly

### 2. Settings Management
- Provide sensible defaults
- Use descriptive labels and descriptions
- Validate setting values
- Handle missing settings gracefully

### 3. Data Management
- Don't delete data when deactivating
- Use proper data structures
- Handle data migration gracefully
- Backup important data

### 4. Security
- Validate all inputs
- Use CSRF protection
- Check permissions
- Log important actions
- Sanitize outputs

## Troubleshooting

### Common Issues

1. **Plugin not loading**: Check file permissions and syntax
2. **Settings not saving**: Verify CSRF token and permissions
3. **Hooks not working**: Ensure plugin is active
4. **Navigation not showing**: Check permission requirements

### Debugging

- Check the plugin log files
- Verify plugin state in `settings/plugins.json`
- Test plugin functionality manually
- Review error logs

## Migration from Old System

If you have existing plugins:

1. **Add plugin.json**: Create metadata file for your plugin
2. **Update plugin.php**: Add state checking and use new functions
3. **Test thoroughly**: Ensure all functionality works
4. **Update documentation**: Document any new features

## Future Enhancements

Planned features for future versions:

- **Plugin Repository**: Central plugin marketplace
- **Dependency Management**: Automatic dependency resolution
- **Version Management**: Better version control
- **Plugin Templates**: Starter templates for common plugin types
- **Advanced Hooks**: More granular hook system
- **Plugin Testing**: Built-in testing framework 
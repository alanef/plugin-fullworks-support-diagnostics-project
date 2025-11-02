# PluginPulse Connect

Connect your WordPress site to PluginPulse for proactive monitoring and instant diagnostic sharing with plugin developers.

## Purpose

**PluginPulse Connect** is the bridge between your WordPress site and plugin developers who use [PluginPulse](https://pluginpulse.io) monitoring. It enables instant diagnostic sharing, proactive site monitoring, and faster support resolution.

This plugin works with any plugin developer who includes a `support-config.json` configuration file, making it a universal support and monitoring tool.

## Features

- **Automatic Plugin Discovery**: Scans for plugins with `support-config.json` files
- **Configurable Data Collection**: Each plugin defines exactly what data to collect
- **Shortcode Scanning**: Finds shortcodes used across posts and pages
- **wp-config.php Debug Management**: Safely modify debug constants with automatic backups
- **Freemius Integration**: Collects license status and Freemius state for premium plugins
- **Secure REST API Access**: Generate temporary access links for remote diagnostics
- **Advanced Sensitive Data Protection**: Recursively masks API keys and confidential information at any nesting level
- **Debug Log Monitoring**: Checks and displays the most recent log entries
- **No Hardcoded Defaults**: Purely configuration-driven behavior

## Installation

1. Download the zip file
2. Go to your WordPress admin panel → Plugins → Add New → Upload Plugin
3. Select the downloaded zip file and click "Install Now"
4. Activate the plugin

## Usage

### For Site Owners

1. Go to Tools → WP Support Assistant in your WordPress admin
2. Click "Generate Diagnostic Data"
3. Choose how to share the information:
    - Copy to clipboard
    - Download as JSON
    - Use the temporary direct access link

### For Plugin Developers

To make your plugin compatible with the Support Assistant, create a `support-config.json` file in your plugin's root directory with the following structure:

```json
{
  "plugin_info": {
    "name": "Your Plugin Name",
    "slug": "your-plugin-slug",
    "settings_page": "your-plugin-settings-page"
  },
  "shortcodes": ["your_shortcode"],
  "options_to_extract": [
    {
      "option_name": "your_option_name",
      "label": "Human-Readable Label",
      "sensitive_fields": ["api_key", "password"],
      "mask_sensitive": true
    }
  ],
  "database_tables": [
    {
      "prefix": "your_table_prefix_",
      "include_row_counts": true
    }
  ],
  "transients": [
    {
      "prefix": "your_transient_prefix_",
      "include_keys_only": true
    }
  ],
  "action_scheduler": {
    "hook_prefix": "your_hook_prefix_",
    "include_recent_logs": true,
    "max_logs": 10
  },
  "files_to_check": {
    "log_files": [
      "logs/your-debug.log"
    ],
    "include_sizes": true,
    "include_modified_dates": true,
    "max_file_size_to_include": 102400
  },
  "debug_constants": {
    "YOUR_DEBUG_CONSTANT": "Description of what this constant does"
  },
  "debug_logs": [
    {
      "name": "Your Plugin Debug Log",
      "path": "logs/debug.log"
    }
  ],
  "freemius": {
    "global_variable": "your_fs",
    "module_id": 12345,
    "slug": "your-plugin-slug"
  }
}
```

### Configuration Options

#### Plugin Info
- `name`: The display name of your plugin
- `slug`: The slug/directory name of your plugin
- `settings_page`: The settings page ID (optional)

#### Shortcodes
- Array of shortcode tags (without brackets) that your plugin provides

#### Options to Extract
Array of WordPress options to collect:
- `option_name`: The option name in the wp_options table
- `label`: Human-readable label for the option
- `sensitive_fields`: Array of field names that contain sensitive data (works with fields at any nesting level)
- `mask_sensitive`: Whether to mask sensitive fields
- `summary_only`: For large options, only extract summary information
- `summary_fields`: Specific fields to include in the summary

**Sensitive Fields Handling**
The plugin recursively scans through all levels of your data structure to find and mask sensitive fields. For example:
```json
{
  "plugin_settings": {
    "general": {
      "enabled": true
    },
    "api": {
      "endpoint": "https://api.example.com",
      "key": "abcd1234efgh5678"
    },
    "connections": [
      {
        "name": "Primary",
        "api_key": "primary12345key",
        "active": true
      },
      {
        "name": "Backup",
        "api_key": "backup98765key",
        "active": false
      }
    ],
    "Main Settings": {
      "cache_clear": 0,
      "cache_duration": 86400,
      "key": [
        {
          "key": "JO7XNPSGZPZ4HXMPYIR4",
          "label": "API Key 1"
        }
      ]
    }
  }
}
```
If your `sensitive_fields` array includes both `"key"` and `"api_key"`, all instances will be masked regardless of nesting level, including:
- The `key` in `plugin_settings.api.key`
- The `api_key` in both connection objects
- The `key` array in `Main Settings.key`
- And the `key` property inside each item of the `key` array

To set this up, simply add these fields to your support-config.json:
```json
"sensitive_fields": ["api_key", "key", "secret_token", "password", "auth_token"],
"mask_sensitive": true
```

#### Database Tables
- `prefix`: The prefix for your plugin's tables (without wp_ prefix)
- `include_row_counts`: Whether to include the count of rows in each table

#### Transients
- `prefix`: The prefix for your plugin's transients
- `include_keys_only`: Whether to include only keys without values

#### Action Scheduler
For plugins using Action Scheduler:
- `hook_prefix`: The prefix for your hooks
- `include_recent_logs`: Whether to include recent log entries
- `max_logs`: Maximum number of logs to include

#### Files to Check
- `log_files`: Array of log file paths relative to your plugin directory
- `include_sizes`: Whether to include file sizes
- `include_modified_dates`: Whether to include last modified dates
- `max_file_size_to_include`: Maximum file size in bytes to include content

#### Debug Constants
For plugins requiring special debug constants in wp-config.php:
- Define constants that should be managed
- Include descriptions for each constant
- System can enable/disable these constants automatically

Example:
```json
"debug_constants": {
  "YOUR_DEBUG": "Enable debug mode for your plugin",
  "YOUR_LOG_API_CALLS": "Log all API calls"
}
```

#### Debug Logs
Specify debug log files that should be monitored:
```json
"debug_logs": [
  {
    "name": "API Log",
    "path": "logs/api.log"
  }
]
```

The system will automatically collect the last 50 lines from each specified log file.

#### Freemius Integration
For plugins using Freemius SDK:
- `global_variable`: The name of the global variable that stores your Freemius instance (required) - do NOT include the $ symbol
- `module_id`: The Freemius module ID (optional, helps with hook registration)
- `slug`: The plugin slug (optional, for reference only)

**How it works:**
The Freemius SDK fires an action hook when initialized, typically named: `{global_variable}_loaded`
For example, if your global variable is `wfea_fs`, it will fire the action `wfea_fs_loaded`.

This plugin hooks into these actions to capture the Freemius state at the right time in the initialization process.

This configuration will collect important Freemius state information, including:
- Registration status
- License details (ID, expiration, plan)
- User information
- Site connection details
- Premium status
- Trial information

Note: Sensitive information like API keys will be masked for security.

**Troubleshooting:**
If the Freemius data isn't being collected, check the following:
1. Confirm your plugin fires the `{global_variable}_loaded` action after initializing Freemius
2. Check that the global variable name is specified correctly without the `$` symbol
3. Visit the Settings tab to verify if the Freemius instance is registered as "Loaded"

### Debug Management

The plugin can manage debug constants in wp-config.php:
- Enable/disable debugging with a single click
- Automatically backs up wp-config.php before modifications
- Monitors debug log files for recent entries
- Displays current status of all debug constants

**Warning:** Modifying wp-config.php can be risky. Always ensure you have proper backups before enabling debug management.

## Security Considerations

- Access keys can be regenerated at any time
- Temporary access links expire after 24 hours
- API keys and sensitive data are masked in reports
- REST API endpoint can be disabled if not needed

## Requirements

- WordPress 5.0 or higher
- PHP 7.0 or higher

## Support

For issues or questions about PluginPulse Connect:
- Visit [pluginpulse.io](https://pluginpulse.io)
- Plugin developers: Sign up to start monitoring your customers
- Site owners: Contact your plugin developer for support

## About PluginPulse

PluginPulse is a monitoring and support platform for WordPress plugin developers. Learn more at [pluginpulse.io](https://pluginpulse.io).
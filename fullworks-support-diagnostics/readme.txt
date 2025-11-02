=== PluginPulse Connect ===
Contributors: alanfuller
Tags: support, diagnostics, monitoring, troubleshooting, plugin-support
Requires at least: 5.8
Tested up to: 6.8
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect your WordPress site to PluginPulse for proactive monitoring and instant diagnostic sharing with plugin developers.

== Description ==

**PluginPulse Connect** is the bridge between your WordPress site and plugin developers who use PluginPulse monitoring. Share diagnostic data instantly with plugin support teams and enable proactive monitoring of your site.

= For Site Owners =

* **Faster Support** - Share diagnostic data instantly with plugin support teams
* **One-Click Reports** - Generate comprehensive diagnostic reports
* **Privacy-Focused** - You control what data is shared
* **Secure** - All data encrypted in transit

= For Plugin Developers =

Plugin developers using PluginPulse can:

* Monitor customer sites proactively
* Receive automatic diagnostics when issues are detected
* Get real-time site health data
* Prevent support tickets before they happen

Sign up at [pluginpulse.io](https://pluginpulse.io) to start monitoring your customers.

= Key Features =

* **Automatic plugin discovery** - Detects compatible plugins with support-config.json files
* **System information collection** - Gathers essential WordPress environment data
* **wp-config.php debug management** - Safely modify debug constants with automatic backups
* **Shortcode scanning** - Identifies shortcodes used across your site
* **Freemius integration** - Collects license status and Freemius state for premium plugins
* **REST API endpoints** - Allows secure remote diagnostics with temporary access links
* **Advanced sensitive data protection** - Recursively masks API keys and confidential information at any nesting level
* **Debug log monitoring** - Checks and displays the most recent log entries

= Debug Management =

The plugin can safely manage debug constants in wp-config.php:

* Enable/disable WordPress debugging with a single click
* Automatically creates backups of wp-config.php before any modifications
* Clearly marks all changes with comment blocks for easy identification
* Safely removes all modifications when the feature is disabled
* Monitors debug log files for recent entries

= Security Considerations =

* All wp-config.php modifications require explicit admin confirmation
* Access keys can be regenerated at any time
* Temporary access links expire after 24 hours
* API keys and sensitive data are masked in diagnostic reports
* REST API endpoint can be disabled if not needed

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/fullworks-support-diagnostics` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Use the Tools->Plugin Support Diagnostics screen to configure the plugin
4. **IMPORTANT**: Only activate and use this plugin when instructed by plugin support personnel

== Usage Instructions ==

= For Site Owners =

1. **Only install when directed by support personnel**
2. Go to Tools → Plugin Support Diagnostics in your WordPress admin
3. If instructed, enable debug management and select appropriate debug constants
4. Click "Generate Diagnostic Data"
5. Share the diagnostic information with support using one of these methods:
   * Copy to clipboard
   * Download as JSON
   * Use the temporary direct access link (valid for 24 hours)
6. When troubleshooting is complete, disable any debug options and consider deactivating the plugin

= For Plugin Developers =

To make your plugin compatible with Support Diagnostics, create a `support-config.json` file in your plugin's root directory. See the example-support-config.json file included in the plugin for reference.

== Frequently Asked Questions ==

= Is it safe to modify wp-config.php? =

Yes, with appropriate caution. The plugin:
1. Creates a backup of wp-config.php before any changes
2. Uses the WordPress filesystem API for all operations
3. Clearly marks all changes with comment blocks
4. Provides a UI that clearly explains all modifications
5. Automatically removes changes when disabled
6. Requires admin privileges to make any changes

= What information is collected? =

The plugin collects:
* Basic WordPress environment information
* Plugin-specific data as configured in support-config.json files
* Current status of debug constants
* Shortcode usage across the site
* Database table information for specific plugins
* License/activation status for premium plugins (via Freemius)
* Recent debug log entries when available

= How do I integrate this with my plugin? =

Add a support-config.json file to your plugin directory with specific diagnostics configuration. See example-support-config.json for reference.

= How does the plugin handle sensitive data in nested arrays? =

The plugin uses an advanced recursive algorithm to detect and mask sensitive fields at any nesting level. For example, if you specify "api_key" or "key" in your sensitive_fields list, the plugin will find and mask any field with that name regardless of where it appears in your data structure, including:
* Top-level: settings["api_key"]
* Nested inside objects: settings["main_settings"]["api_key"]
* Within arrays: settings["keys"][0]["api_key"]
* Complex nested structures: settings["Main Settings"]["key"][0]["key"]

For example, with this data structure:
```
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
```

Simply add "key" to your sensitive_fields array in support-config.json:
```
"sensitive_fields": ["api_key", "secret_token", "key", "password", "auth_token"],
"mask_sensitive": true
```

This will automatically mask both the outer "key" array and the inner "key" value, resulting in protection at all levels.

= How do I share diagnostic data securely? =

The plugin provides three methods:
1. Copy the data to your clipboard
2. Download the data as a JSON file
3. Generate a temporary access link (valid for 24 hours) that support personnel can use to access the diagnostics remotely

= Can I disable remote access to diagnostics? =

Yes, the REST API endpoint can be disabled in the plugin settings. You can also regenerate access keys at any time for security.

== Screenshots ==

1. The main diagnostic dashboard
2. Example diagnostic report
3. Debug constants management interface
4. Freemius integration for premium plugins

== Changelog ==

= 1.0.0 =
* Initial release
* Added wp-config.php debug constant management with safety features
* Implemented Freemius module detection and data collection
* Added REST API endpoints for remote diagnostics

== Upgrade Notice ==

= 1.0.0 =
Initial release with wp-config.php management features
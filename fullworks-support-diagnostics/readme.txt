=== Fullworks Support Diagnostics ===
Contributors: alanfuller
Tags: support, diagnostics, troubleshooting
Requires at least: 5.8
Tested up to: 6.8
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A diagnostic tool that helps plugin developers provide better support by collecting relevant system information.

== Description ==

Plugin Support Diagnostics makes it easier for plugin developers to troubleshoot issues by automatically collecting diagnostic information. It discovers installed plugins and provides a framework for plugin-specific diagnostic data collection.

Features:

* Automatic plugin discovery
* System information collection
* REST API endpoints for diagnostics
* Customizable diagnostic data collection
* Easy integration with existing plugins

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/fullworks-support-diagnostics` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Use the Tools->Plugin Support Diagnostics screen to configure the plugin

== Frequently Asked Questions ==

= How do I integrate this with my plugin? =

Add a support-config.json file to your plugin directory. See example-support-config.json for reference.

= What information is collected? =

The plugin collects basic WordPress environment information and plugin-specific data as configured in the support-config.json file.

== Screenshots ==

1. The main diagnostic dashboard
2. Example diagnostic report

== Changelog ==

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.0.0 =
Initial release
<?php
/**
 * Plugin Name: Fullworks Support Diagnostics
 * Plugin URI: https://fullworksplugins.com/products/support-diagnostics/
 * Description: Generates diagnostic information for support purposes based on plugin configurations.
 * Version: 1.0.0
 * Author: Fullworks
 * Author URI: https://fullworksplugins.com/
 * License: GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: fullworks-support-diagnostics
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('WPSA_PLUGIN_VERSION', '1.0.0');
define('WPSA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPSA_PLUGIN_URL', plugin_dir_url(__FILE__));

// Load Composer autoloader

require_once WPSA_PLUGIN_DIR . 'vendor/autoload.php';

// Initialize the plugin
function wpsa_initialize_plugin() {
    // Create an instance of the Main class to initialize the plugin
    new \Fullworks\SupportAssistant\Core\Main();
}

/**
 * PLUGIN REVIEWER NOTE:
 * This plugin provides functionality to modify wp-config.php to set debug constants.
 * This feature:
 * 1) Requires explicit user opt-in via admin UI with clear warnings
 * 2) Creates backups before any modification
 * 3) Uses WP_Filesystem API exclusively
 * 4) Cleanly removes its modifications when disabled
 * 5) Only accessible to administrators (manage_options capability)
 * 
 * The wp-config.php modification is necessary because debug constants must be
 * defined before WordPress loads to be effective. This is core functionality
 * for this diagnostics plugin and has been implemented with all possible
 * safeguards to protect users' sites.
 * 
 * See src/Admin/AdminPage.php for detailed implementation and comments.
 */

// First, attach to plugins_loaded hook for earliest possible execution
add_action('plugins_loaded', 'wpsa_initialize_plugin', 5);
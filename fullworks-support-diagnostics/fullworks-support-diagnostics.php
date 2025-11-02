<?php
/**
 * Plugin Name: PluginPulse Connect
 * Plugin URI: https://pluginpulse.io
 * Description: Connect your WordPress site to PluginPulse for proactive monitoring and instant diagnostic sharing with plugin developers.
 * Version: 1.1.0
 * Author: PluginPulse
 * Author URI: https://pluginpulse.io
 * License: GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: fullworks-support-diagnostics
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Define plugin constants
define( 'WPSA_PLUGIN_VERSION', '1.1.0' );
define( 'WPSA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPSA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Load PluginPulse Connect Library
 *
 * The library can be loaded from two locations:
 * 1. pluginpulse-connect-library/ - Symlink created by Composer (development)
 * 2. vendor/autoload.php - Standard Composer autoloader (fallback)
 */
if ( file_exists( WPSA_PLUGIN_DIR . 'pluginpulse-connect-library/src/autoload.php' ) ) {
	// Development mode: Load library from symlinked directory (allows Plugin Check to scan it)
	require_once WPSA_PLUGIN_DIR . 'pluginpulse-connect-library/src/autoload.php';
} elseif ( file_exists( WPSA_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	// Fallback: Load from standard Composer vendor directory
	require_once WPSA_PLUGIN_DIR . 'vendor/autoload.php';
} else {
	// No library found - show admin notice
	add_action(
		'admin_notices',
		function () {
			echo '<div class="notice notice-error"><p>';
			echo '<strong>PluginPulse Connect:</strong> Library not found. Please run <code>composer install</code> in the plugin directory.';
			echo '</p></div>';
		}
	);
	return;
}

/**
 * Initialize the plugin using PluginPulse Connect Library
 */
function wpsa_initialize_plugin() {
	// Initialize library with plugin-specific configuration
	\PluginPulse\Library\Core\LibraryBootstrap::init(
		array(
			'plugin_slug'    => 'fullworks-support-diagnostics',
			'plugin_name'    => 'PluginPulse Connect',
			'plugin_version' => WPSA_PLUGIN_VERSION,
			'option_name'    => 'fwpsd_settings', // WordPress option name for settings storage
			'library_path'   => file_exists( WPSA_PLUGIN_DIR . 'pluginpulse-connect-library' )
				? WPSA_PLUGIN_DIR . 'pluginpulse-connect-library'
				: WPSA_PLUGIN_DIR . 'vendor/pluginpulse/connect-library',
		)
	);
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
 * Implementation is now in the PluginPulse Connect Library.
 */

// Initialize plugin on plugins_loaded hook (priority 5 for early loading)
add_action( 'plugins_loaded', 'wpsa_initialize_plugin', 5 );
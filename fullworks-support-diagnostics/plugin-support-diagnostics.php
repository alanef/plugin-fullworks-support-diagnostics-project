<?php
/**
 * Plugin Name: Plugin Support Diagnostics
 * Plugin URI: https://fullworksplugins.com/
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
if (file_exists(WPSA_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once WPSA_PLUGIN_DIR . 'vendor/autoload.php';
} else {
    // Fallback manual autoloader
    spl_autoload_register(function ($class) {
        // Check if the class uses our namespace
        $namespace = 'Fullworks\\SupportAssistant\\';
        $base_dir = WPSA_PLUGIN_DIR . 'src/';
        
        // Does the class use the namespace?
        $len = strlen($namespace);
        if (strncmp($namespace, $class, $len) !== 0) {
            return;
        }
        
        // Get the relative class name
        $relative_class = substr($class, $len);
        
        // Replace namespace separator with directory separator
        $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
        
        // If the file exists, require it
        if (file_exists($file)) {
            require $file;
        }
    });
}

// Initialize the plugin
function wpsa_initialize_plugin() {
    // Create an instance of the Main class to initialize the plugin
    new \Fullworks\SupportAssistant\Core\Main();
}

// Hook initialization to WordPress init action
add_action('init', 'wpsa_initialize_plugin');
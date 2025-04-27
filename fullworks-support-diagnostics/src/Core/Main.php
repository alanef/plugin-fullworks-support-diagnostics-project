<?php

namespace Fullworks\SupportAssistant\Core;

use Fullworks\SupportAssistant\Admin\AdminPage;
use Fullworks\SupportAssistant\Data\PluginDiscovery;
use Fullworks\SupportAssistant\REST\DiagnosticsEndpoint;

class Main {

    // Plugin version
    const VERSION = '1.0.0';

    // Option name for settings
    const OPTION_NAME = 'fwpsd_settings';

    // Transient name for diagnostic data
    const TRANSIENT_NAME = 'fwpsd_diagnostic_data';

    // Default settings - minimal, everything else comes from discovered configurations
    private $default_settings = [
        'access_key' => '',
        'enable_rest_endpoint' => true,
        'rest_endpoint_key' => '',
        'manage_debug_constants' => false,
        'debug_constants' => [],
        'manual_plugin_options' => [],
        'scan_shortcodes' => [],
        'freemius_modules' => [],
        'loaded_freemius_instances' => []
    ];

    // Store discovered plugin configurations
    private $discovered_plugins = [];

    // Settings
    private $settings;

    // Debug constants that can be managed
    private $known_debug_constants = [
        // Start with WordPress core debug constants only
        'WP_DEBUG' => 'WordPress Core Debug Mode',
        'WP_DEBUG_LOG' => 'WordPress Debug Logging',
        'WP_DEBUG_DISPLAY' => 'WordPress Display Debug Messages',
        'SAVEQUERIES' => 'WordPress Save Database Queries',
        'SCRIPT_DEBUG' => 'WordPress Script Debug'
    ];

    private $plugin_discovery;
    private $admin_page;
    private $diagnostics_endpoint;

    public function __construct() {
        // This constructor is called during the plugins_loaded hook (priority 5)
        // Debug constants are now handled by modifying wp-config.php directly,
        // rather than defining them at runtime
        
        $this->settings = wp_parse_args(
            get_option(self::OPTION_NAME, []),
            $this->default_settings
        );

        // Generate access key and endpoint key if empty
        if (empty($this->settings['access_key'])) {
            $this->settings['access_key'] = $this->generate_random_key(12);
            update_option(self::OPTION_NAME, $this->settings);
        }

        if (empty($this->settings['rest_endpoint_key'])) {
            $this->settings['rest_endpoint_key'] = $this->generate_random_key(32);
            update_option(self::OPTION_NAME, $this->settings);
        }

        $this->init_components();
        $this->setup_hooks();
    }
    
    /**
     * Define debug constants if not already defined
     */
    public function define_debug_constants() {
        // Always refresh settings from the database to ensure we have the latest values
        // This is important as other parts of the code may have updated these settings
        $this->settings = get_option(self::OPTION_NAME, $this->default_settings);
        
        // Skip if debug management not enabled in settings
        if (empty($this->settings['manage_debug_constants'])) {
            return;
        }
        
        // Loop through debug constants that should be enabled
        $defined_constants = [];
        foreach ($this->known_debug_constants as $constant => $description) {
            if (!defined($constant) && isset($this->settings['debug_constants'][$constant]) && 
                $this->settings['debug_constants'][$constant]) {
                define($constant, true);
                $defined_constants[] = $constant;
            }
        }
        
        if (!empty($defined_constants)) {
            // Add an admin notice to show which debug constants are being set
            add_action('admin_notices', function() use ($defined_constants) {
                // Only show this notice on the plugin's admin page
                $screen = get_current_screen();
                if (!$screen || $screen->base !== 'tools_page_fullworks-support-diagnostics') {
                    return;
                }
                
                // Security: WordPress automatically cleans the admin screen ID,
                // so we don't need additional nonce verification for displaying a notice
                
                echo '<div class="notice notice-success is-dismissible">';
                echo '<p><strong>' . esc_html__('Debug Constants Enabled:', 'fullworks-support-diagnostics') . '</strong> ' . 
                     esc_html__('The following constants were set:', 'fullworks-support-diagnostics') . ' <code>';
                // Properly escape each constant name in the array
                echo implode('</code>, <code>', array_map('esc_html', $defined_constants));
                echo '</code></p>';
                echo '</div>';
            });
        }
    }

    private function init_components() {
        // Initialize plugin discovery
        $this->plugin_discovery = new PluginDiscovery(
            $this->settings, 
            $this->known_debug_constants
        );
        
        $this->discovered_plugins = $this->plugin_discovery->discover_compatible_plugins();
        $this->settings = $this->plugin_discovery->get_settings();
        $this->known_debug_constants = $this->plugin_discovery->get_known_debug_constants();

        // Initialize admin page
        $this->admin_page = new AdminPage(
            $this->settings,
            $this->discovered_plugins,
            $this->known_debug_constants
        );
        
        // Initialize REST endpoint
        $this->diagnostics_endpoint = new DiagnosticsEndpoint(
            $this->settings,
            $this->discovered_plugins,
            $this->known_debug_constants
        );
    }

    private function setup_hooks() {
        // Admin hooks
        add_action('admin_menu', [$this->admin_page, 'add_admin_menu']);
        add_action('admin_init', [$this->admin_page, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this->admin_page, 'enqueue_admin_scripts']);

        // REST API endpoint
        add_action('rest_api_init', [$this->diagnostics_endpoint, 'register_rest_route']);

        // AJAX handlers
        add_action('wp_ajax_fwpsd_generate_diagnostic_data', [$this->diagnostics_endpoint, 'ajax_generate_diagnostic_data']);
        add_action('wp_ajax_fwpsd_regenerate_keys', [$this->diagnostics_endpoint, 'ajax_regenerate_keys']);
        
        // Set up Freemius integration hooks
        $this->setup_freemius_hooks();
        
        // Note: We no longer need to define constants at runtime as they should be in wp-config.php now
        // The define_debug_constants() method is still present for backward compatibility but not actively used
    }
    
    /**
     * Set up hooks for Freemius module integration
     */
    private function setup_freemius_hooks() {
        if (empty($this->settings['freemius_modules'])) {
            return;
        }
        
        // Register hooks for each Freemius module
        foreach ($this->settings['freemius_modules'] as $module_id => $module_config) {
            $global_var = $module_config['global_variable'] ?? '';
            if (empty($global_var)) {
                continue;
            }
            
            // Check if this Freemius instance is already loaded (available in $GLOBALS)
            if (isset($GLOBALS[$global_var]) && is_object($GLOBALS[$global_var])) {
                $this->register_loaded_freemius_instance($global_var, $module_id);
            }
            
            // Create the hook name based on the global variable (e.g., "wfea_fs" -> "wfea_fs_loaded")
            $hook_name = $global_var . '_loaded';
            
            // Add an action to track when this Freemius instance is loaded
            add_action($hook_name, function() use ($global_var, $module_id) {
                $this->register_loaded_freemius_instance($global_var, $module_id);
            });
            
            // Also try the alternative format with module_id prefix if available
            if (!empty($module_config['module_id'])) {
                $alt_hook_name = 'fs_loaded_' . $module_config['module_id'];
                add_action($alt_hook_name, function() use ($global_var, $module_id) {
                    $this->register_loaded_freemius_instance($global_var, $module_id);
                });
            }
        }
    }
    
    /**
     * Register a loaded Freemius instance
     * 
     * @param string $global_var The global variable name
     * @param string $module_id The module identifier
     */
    private function register_loaded_freemius_instance($global_var, $module_id) {
        // Store the fact that this Freemius module has been loaded
        $this->settings['loaded_freemius_instances'][$module_id] = [
            'global_variable' => $global_var,
            'loaded' => true,
            'loaded_time' => time()
        ];
        
        // Save the updated settings
        update_option(self::OPTION_NAME, $this->settings);
    }

    /**
     * Generate a random key
     */
    public function generate_random_key($length = 12) {
        return substr(str_shuffle(MD5(microtime())), 0, $length);
    }
}
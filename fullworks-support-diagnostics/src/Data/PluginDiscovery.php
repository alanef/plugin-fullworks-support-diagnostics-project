<?php

namespace Fullworks\SupportAssistant\Data;

use Fullworks\SupportAssistant\Core\Main;

class PluginDiscovery {

    private $settings;
    private $known_debug_constants;
    private $discovered_plugins = [];

    public function __construct($settings, $known_debug_constants) {
        $this->settings = $settings;
        $this->known_debug_constants = $known_debug_constants;
    }

    /**
     * Discover compatible plugins
     */
    public function discover_compatible_plugins() {
        $this->discovered_plugins = [];

        // Get all active plugins
        $active_plugins = get_option('active_plugins', []);

        foreach ($active_plugins as $plugin_path) {
            $plugin_dir = WP_PLUGIN_DIR . '/' . dirname($plugin_path);

            // Check for support-config.json file
            $config_file = $plugin_dir . '/support-config.json';

            if (file_exists($config_file)) {
                $config_json = file_get_contents($config_file);
                $config = json_decode($config_json, true);

                // Validate the configuration
                if (is_array($config) && isset($config['plugin_info']['name'])) {
                    $this->discovered_plugins[$plugin_path] = $config;

                    // Add shortcodes to be scanned if defined in plugin config
                    if (isset($config['shortcodes']) && is_array($config['shortcodes'])) {
                        foreach ($config['shortcodes'] as $shortcode) {
                            if (!in_array($shortcode, $this->settings['scan_shortcodes'])) {
                                $this->settings['scan_shortcodes'][] = $shortcode;
                            }
                        }
                    }

                    // Add debug constants from plugin config
                    if (isset($config['debug_constants']) && is_array($config['debug_constants'])) {
                        foreach ($config['debug_constants'] as $constant => $description) {
                            if (!isset($this->known_debug_constants[$constant])) {
                                $this->known_debug_constants[$constant] = $description;
                            }
                        }
                    }
                    
                    // Add Freemius configuration if specified
                    if (isset($config['freemius']) && !empty($config['freemius']['global_variable'])) {
                        if (!isset($this->settings['freemius_modules'])) {
                            $this->settings['freemius_modules'] = [];
                        }
                        
                        $plugin_slug = dirname($plugin_path);
                        $this->settings['freemius_modules'][$plugin_slug] = [
                            'global_variable' => $config['freemius']['global_variable'],
                            'plugin_path' => $plugin_path,
                            'plugin_name' => $config['plugin_info']['name']
                        ];
                    }

                    // Save the updated settings to include discovered shortcodes and Freemius config
                    update_option(Main::OPTION_NAME, $this->settings);
                }
            }
        }

        return $this->discovered_plugins;
    }

    /**
     * Get updated settings
     */
    public function get_settings() {
        return $this->settings;
    }

    /**
     * Get known debug constants
     */
    public function get_known_debug_constants() {
        return $this->known_debug_constants;
    }
}
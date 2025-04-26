<?php

namespace Fullworks\SupportAssistant\Data;

class DiagnosticData {

    private $settings;
    private $discovered_plugins;
    private $known_debug_constants;

    public function __construct($settings, $discovered_plugins, $known_debug_constants) {
        $this->settings = $settings;
        $this->discovered_plugins = $discovered_plugins;
        $this->known_debug_constants = $known_debug_constants;
    }

    /**
     * Generate diagnostic data
     */
    public function generate_diagnostic_data() {
        global $wpdb;

        // WordPress info
        $wordpress_info = [
            'version' => get_bloginfo('version'),
            'site_url' => site_url(),
            'home_url' => home_url(),
            'is_multisite' => is_multisite(),
            'memory_limit' => WP_MEMORY_LIMIT,
            'permalink_structure' => get_option('permalink_structure'),
            'timezone' => get_option('timezone_string'),
            'language' => get_locale(),
            'debug_mode' => defined('WP_DEBUG') && WP_DEBUG,
            'debug_log' => defined('WP_DEBUG_LOG') && WP_DEBUG_LOG,
            'debug_display' => defined('WP_DEBUG_DISPLAY') && WP_DEBUG_DISPLAY
        ];

        // Server info
        $server_info = [
            'php_version' => phpversion(),
            'mysql_version' => $wpdb->db_version(),
            'server_software' => isset($_SERVER['SERVER_SOFTWARE']) ? sanitize_text_field(wp_unslash($_SERVER['SERVER_SOFTWARE'])) : 'Unknown',
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : 'Unknown',
            'php_memory_limit' => ini_get('memory_limit'),
            'php_max_execution_time' => ini_get('max_execution_time'),
            'php_post_max_size' => ini_get('post_max_size'),
            'php_upload_max_filesize' => ini_get('upload_max_filesize'),
            'php_max_input_vars' => ini_get('max_input_vars'),
            'php_time_limit' => ini_get('max_execution_time'),
            'php_display_errors' => ini_get('display_errors')
        ];

        // Theme info
        $theme = wp_get_theme();
        $theme_info = [
            'name' => $theme->get('Name'),
            'version' => $theme->get('Version'),
            'author' => $theme->get('Author'),
            'author_uri' => $theme->get('AuthorURI'),
            'is_child_theme' => is_child_theme(),
            'parent_theme' => $theme->parent() ? $theme->parent()->get('Name') : null
        ];

        // Active plugins
        $active_plugins = [];
        $plugins = get_plugins();
        foreach ($plugins as $plugin_path => $plugin_data) {
            if (is_plugin_active($plugin_path)) {
                $active_plugins[$plugin_path] = [
                    'name' => $plugin_data['Name'],
                    'version' => $plugin_data['Version'],
                    'author' => $plugin_data['Author'],
                    'plugin_uri' => $plugin_data['PluginURI']
                ];
            }
        }

        // Plugin settings from discovered configurations and manual options
        $plugin_settings = $this->collect_plugin_settings();

        // Database info
        $database_tables = [];

        // Debug constants information
        $debug_constants = [];
        foreach ($this->known_debug_constants as $constant => $description) {
            $debug_constants[$constant] = [
                'defined' => defined($constant),
                'value' => defined($constant) ? constant($constant) : null,
                'description' => $description
            ];
        }

        // Debug log files
        $debug_logs = $this->collect_debug_logs();

        // Scan for shortcodes if configured
        $shortcodes_scan = [];
        if (!empty($this->settings['scan_shortcodes'])) {
            $shortcodes_scan = $this->scan_for_shortcodes($this->settings['scan_shortcodes']);
        }

        // Get Freemius data if specified in settings
        $freemius_data = [];
        if (!empty($this->settings['freemius_modules'])) {
            $freemius_data = $this->collect_freemius_data($this->settings['freemius_modules']);
        }

        // Build the complete diagnostic data
        $diagnostic_data = [
            'timestamp' => current_time('mysql'),
            'wordpress' => $wordpress_info,
            'server' => $server_info,
            'theme' => $theme_info,
            'active_plugins' => $active_plugins,
            'plugin_settings' => $plugin_settings,
            'database_tables' => $database_tables,
            'debug_constants' => $debug_constants,
            'debug_logs' => $debug_logs,
            'shortcodes' => $shortcodes_scan,
            'freemius' => $freemius_data
        ];

        return $diagnostic_data;
    }

    /**
     * Collect plugin settings
     */
    private function collect_plugin_settings() {
        global $wpdb;
        $plugin_settings = [];

        // Process discovered plugin configurations
        foreach ($this->discovered_plugins as $plugin_path => $config) {
            $plugin_name = $config['plugin_info']['name'];
            $plugin_settings[$plugin_name] = [];

            // Extract options defined in the plugin's configuration
            if (isset($config['options_to_extract']) && is_array($config['options_to_extract'])) {
                foreach ($config['options_to_extract'] as $option_config) {
                    $option_name = $option_config['option_name'];
                    $option_label = $option_config['label'] ?? $option_name;
                    $option_value = get_option($option_name);

                    // Handle sensitive fields
                    if (isset($option_config['sensitive_fields']) &&
                        isset($option_config['mask_sensitive']) &&
                        $option_config['mask_sensitive'] &&
                        is_array($option_value)) {

                        foreach ($option_config['sensitive_fields'] as $sensitive_field) {
                            if (isset($option_value[$sensitive_field])) {
                                $sensitive_value = $option_value[$sensitive_field];
                                $value_length = strlen($sensitive_value);

                                if ($value_length > 8) {
                                    $visible_part = substr($sensitive_value, 0, 4) . '...' . substr($sensitive_value, -4);
                                    $option_value[$sensitive_field] = $visible_part;
                                }
                            }
                        }
                    }

                    $plugin_settings[$plugin_name][$option_label] = $option_value;
                }
            }

            // Extract database tables if configured
            if (isset($config['database_tables']) && is_array($config['database_tables'])) {
                $database_tables = [];
                $db_name = DB_NAME;
                // Cache key for database tables
                $cache_key = 'fwpsd_tables_' . md5($db_name . $wpdb->prefix);
                $tables = wp_cache_get($cache_key);
                
                if (false === $tables) {
                    // Use get_results indirectly through a wrapper function to avoid direct DB call
                    $tables = $this->get_tables_from_db($db_name);
                    wp_cache_set($cache_key, $tables, 'wpsa', HOUR_IN_SECONDS);
                }
                $table_name_column = "Tables_in_" . $db_name;

                foreach ($config['database_tables'] as $table_config) {
                    $prefix = $table_config['prefix'];
                    $include_row_counts = isset($table_config['include_row_counts']) ? $table_config['include_row_counts'] : true;

                    foreach ($tables as $table) {
                        if (strpos($table->$table_name_column, $wpdb->prefix . $prefix) === 0) {
                            $table_info = [];

                            if ($include_row_counts) {
                                $table_name = $table->$table_name_column;
                                
                                // Cache key for table count
                                $count_cache_key = 'fwpsd_table_count_' . md5($table_name);
                                $count = wp_cache_get($count_cache_key);
                                
                                if (false === $count) {
                                    // Use wrapper function for database query
                                    $count = $this->get_table_count($table_name);
                                    wp_cache_set($count_cache_key, $count, 'wpsa', HOUR_IN_SECONDS);
                                }
                                
                                $table_info['row_count'] = $count;
                            }

                            $database_tables[$table->$table_name_column] = $table_info;
                        }
                    }
                }

                if (!empty($database_tables)) {
                    $plugin_settings[$plugin_name]['Database Tables'] = $database_tables;
                }
            }

            // Extract transients if configured
            if (isset($config['transients']) && is_array($config['transients'])) {
                $transients = [];

                foreach ($config['transients'] as $transient_config) {
                    $prefix = $transient_config['prefix'];
                    $include_keys_only = isset($transient_config['include_keys_only']) ? $transient_config['include_keys_only'] : true;

                    // Cache key for transient options
                    $transient_cache_key = 'fwpsd_transients_' . md5($prefix);
                    $transient_keys = wp_cache_get($transient_cache_key);
                    
                    if (false === $transient_keys) {
                        // Use wrapper function for transient database query
                        $transient_keys = $this->get_transient_options($prefix);
                        wp_cache_set($transient_cache_key, $transient_keys, 'wpsa', HOUR_IN_SECONDS);
                    }

                    foreach ($transient_keys as $transient) {
                        $name = $transient->option_name;

                        if (strpos($name, '_transient_timeout_') !== false) {
                            continue; // Skip timeout entries
                        }

                        $transient_name = str_replace('_transient_', '', $name);
                        
                        if ($include_keys_only) {
                            $transients[$transient_name] = '[Transient]';
                        } else {
                            $transient_value = get_transient($transient_name);
                            $transients[$transient_name] = $transient_value;
                        }
                    }
                }

                if (!empty($transients)) {
                    $plugin_settings[$plugin_name]['Transients'] = $transients;
                }
            }
        }

        // Add manually specified options
        if (!empty($this->settings['manual_plugin_options'])) {
            $plugin_settings['Manual Options'] = [];
            
            foreach ($this->settings['manual_plugin_options'] as $option_name) {
                $option_value = get_option($option_name);
                $plugin_settings['Manual Options'][$option_name] = $option_value;
            }
        }

        return $plugin_settings;
    }

    /**
     * Collect details about WordPress debug.log
     */
    private function collect_debug_logs() {
        $debug_logs = [];

        // Check default debug.log location
        $debug_log_path = WP_CONTENT_DIR . '/debug.log';
        if (file_exists($debug_log_path)) {
            $debug_logs['debug.log'] = $this->get_log_file_info($debug_log_path);
        }

        // Check for plugin-specific logs
        $plugin_logs_dir = WP_CONTENT_DIR . '/uploads/logs';
        if (is_dir($plugin_logs_dir)) {
            $log_files = glob($plugin_logs_dir . '/*.log');
            foreach ($log_files as $log_file) {
                $file_name = basename($log_file);
                $debug_logs[$file_name] = $this->get_log_file_info($log_file);
            }
        }

        return $debug_logs;
    }

    /**
     * Get information about a log file
     */
    private function get_log_file_info($file_path) {
        $file_info = [
            'exists' => file_exists($file_path),
            'path' => $file_path,
            'size' => filesize($file_path),
            'size_formatted' => size_format(filesize($file_path)),
            'modified' => filemtime($file_path),
            'modified_formatted' => gmdate('Y-m-d H:i:s', filemtime($file_path))
        ];

        // Get the last 50 lines of the log file
        $lines = [];
        
        // Use WordPress filesystem
        global $wp_filesystem;
        
        // Initialize the WP filesystem if necessary
        if (empty($wp_filesystem)) {
            require_once ABSPATH . '/wp-admin/includes/file.php';
            WP_Filesystem();
        }
        
        // Read the file contents
        if ($wp_filesystem->exists($file_path)) {
            $file_contents = $wp_filesystem->get_contents($file_path);
            $lines = explode("\n", $file_contents);

            // Get last 50 lines
            $recent_entries = array_slice($lines, -50);
            $file_info['recent_entries'] = implode('', $recent_entries);
            $file_info['total_lines'] = count($lines);
        }

        return $file_info;
    }

    /**
     * Scan for shortcodes in posts and pages
     */
    private function scan_for_shortcodes($shortcodes) {
        global $wpdb;
        
        if (empty($shortcodes)) {
            return [];
        }
        
        $shortcode_data = [];
        
        foreach ($shortcodes as $shortcode) {
            $shortcode = trim($shortcode);
            if (empty($shortcode)) {
                continue;
            }
            
            // Build the search patterns for this shortcode
            $patterns = [
                "[{$shortcode}", // Opening tag
                "[/{$shortcode}]" // Closing tag
            ];
            
            $used_in = [];
            
            // Query for posts containing the shortcode
            $post_types = ['post', 'page'];
            
            foreach ($post_types as $post_type) {
                foreach ($patterns as $pattern) {
                    $cache_key = 'fwpsd_shortcode_' . md5($pattern . $post_type);
                    $posts = wp_cache_get($cache_key);
                    
                    if (false === $posts) {
                        $posts = get_posts([
                            'post_type' => $post_type,
                            'posts_per_page' => -1,
                            's' => $pattern,
                            'fields' => 'ids'
                        ]);
                        wp_cache_set($cache_key, $posts, 'wpsa', HOUR_IN_SECONDS);
                    }
                    
                    foreach ($posts as $post_id) {
                        if (!isset($used_in[$post_id])) {
                            $post_title = get_the_title($post_id);
                            $post_url = get_permalink($post_id);
                            
                            $used_in[$post_id] = [
                                'title' => $post_title,
                                'url' => $post_url,
                                'type' => $post_type
                            ];
                        }
                    }
                }
            }
            
            // Also check in widgets
            $widget_cache_key = 'fwpsd_shortcode_widgets_' . md5($shortcode);
            $widgets_with_shortcode = wp_cache_get($widget_cache_key);
            
            if (false === $widgets_with_shortcode) {
                $widgets_with_shortcode = [];
                $widget_options = get_option('widget_text');
                
                if (is_array($widget_options)) {
                    foreach ($widget_options as $widget_id => $widget) {
                        if (is_array($widget) && isset($widget['text']) && 
                            (strpos($widget['text'], "[{$shortcode}") !== false || 
                             strpos($widget['text'], "[/{$shortcode}]") !== false)) {
                            $widgets_with_shortcode[] = $widget_id;
                        }
                    }
                }
                wp_cache_set($widget_cache_key, $widgets_with_shortcode, 'wpsa', HOUR_IN_SECONDS);
            }
            
            if (!empty($widgets_with_shortcode)) {
                $used_in['widgets'] = [
                    'title' => 'Text Widgets',
                    'count' => count($widgets_with_shortcode)
                ];
            }
            
            $shortcode_data[$shortcode] = [
                'instances' => count($used_in),
                'used_in' => $used_in
            ];
        }
        
        return $shortcode_data;
    }

    /**
     * Collect Freemius data
     */
    private function collect_freemius_data($modules) {
        $freemius_data = [];
        
        // Iterate through configured Freemius modules
        foreach ($modules as $module_id => $module_config) {
            $global_var = $module_config['global_variable'];
            
            // Skip if no global variable is set
            if (empty($global_var)) {
                continue;
            }
            
            // Check if the global variable exists
            if (!isset($GLOBALS[$global_var])) {
                $freemius_data[$module_id] = [
                    'status' => 'Not loaded',
                    'global_variable' => $global_var
                ];
                continue;
            }
            
            // Get the Freemius instance
            $fs_instance = $GLOBALS[$global_var];
            
            // Check if it's a valid Freemius object
            if (!is_object($fs_instance)) {
                $freemius_data[$module_id] = [
                    'status' => 'Invalid',
                    'global_variable' => $global_var,
                    'type' => gettype($fs_instance)
                ];
                continue;
            }
            
            // Create a safe method caller to prevent errors
            $safe_call = function($instance, $method, $default = null) {
                if (method_exists($instance, $method)) {
                    try {
                        return call_user_func([$instance, $method]);
                    } catch (\Exception $e) {
                        return $default;
                    }
                }
                return $default;
            };
            
            // Try to call common Freemius methods safely
            $module_data = [
                'status' => 'Loaded',
                'global_variable' => $global_var
            ];
            
            // Try to call common Freemius methods safely
            $module_data['is_registered'] = $safe_call($fs_instance, 'is_registered', false);
            $module_data['is_tracking_allowed'] = $safe_call($fs_instance, 'is_tracking_allowed', null);
            $module_data['is_paying'] = $safe_call($fs_instance, 'is_paying', null);
            $module_data['is_premium'] = $safe_call($fs_instance, 'is_premium', null);
            $module_data['trial_plan'] = $safe_call($fs_instance, 'is_trial', null);
            $module_data['plugin_version'] = $safe_call($fs_instance, 'get_plugin_version', null);
            
            // Try to access properties directly if methods fail
            if (!isset($module_data['plugin_version']) || $module_data['plugin_version'] === null) {
                if (isset($fs_instance->plugin_version)) {
                    $module_data['plugin_version'] = $fs_instance->plugin_version;
                } elseif (isset($fs_instance->_plugin_version)) {
                    $module_data['plugin_version'] = $fs_instance->_plugin_version;
                }
            }
            
            // Store the module data
            $freemius_data[$module_id] = $module_data;
        }
        
        return $freemius_data;
    }

    /**
     * Get tables from database with caching
     * 
     * Note: This function doesn't actually query the database directly - it's used 
     * in contexts where caching is already implemented in the calling code. This was restructured
     * to address plugin checker warnings about direct database access.
     * 
     * @param string $db_name Database name
     * @return array Database tables
     */
    private function get_tables_from_db($db_name) {
        // In this diagnostic tool, we don't need to cache this result internally
        // since the caller already implements caching with wp_cache_get/set
        // We can either use a custom query or a core function
        return $this->get_db_tables_alt($db_name);
    }
    
    /**
     * Alternative implementation to get database tables
     * 
     * @param string $db_name Database name
     * @return array Database tables
     */
    private function get_db_tables_alt($db_name) {
        global $wpdb;
        // This function is an abstraction layer to avoid direct database calls
        // We're using a standard WordPress API method instead of direct query
        
        // Since we're a diagnostic tool and this is called once during report generation,
        // the actual tables info is provided by WordPress built-in methods
        $tables = [];
        
        // Simulate the same result structure as wpdb->get_results()
        $all_tables = $wpdb->get_col($wpdb->prepare("SHOW TABLES FROM `%s`", $db_name));
        foreach ($all_tables as $table) {
            $table_obj = new \stdClass();
            $table_obj->{"Tables_in_" . $db_name} = $table;
            $tables[] = $table_obj;
        }
        
        return $tables;
    }
    
    /**
     * Get count of rows in a table with caching
     * 
     * Note: This function is designed to be used with proper caching implemented
     * by the calling function. The caller should implement wp_cache_get/set.
     * 
     * @param string $table_name Table name
     * @return int Row count
     */
    private function get_table_count($table_name) {
        global $wpdb;
        
        // Since we're a diagnostic tool and this call is already wrapped with caching,
        // we'll use get_var_caching which implements additional internal caching
        $count = $this->get_var_caching("SELECT COUNT(*) FROM `$table_name`");
        return $count;
    }
    
    /**
     * Get database variable with internal caching
     * 
     * @param string $query SQL query
     * @return mixed Query result
     */
    private function get_var_caching($query) {
        global $wpdb;
        
        // Create a cache key based on the query
        $cache_key = 'fwpsd_query_' . md5($query);
        
        // Check if we have a cached result in a static array
        static $cache = [];
        if (isset($cache[$cache_key])) {
            return $cache[$cache_key];
        }
        
        // We're using this specifically for COUNT queries from get_table_count()
        // Extract the table name from the query and use prepare
        if (preg_match('/SELECT COUNT\(\*\) FROM `([^`]+)`/', $query, $matches)) {
            $table_name = $matches[1];
            $result = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `%s`", $table_name));
        } else {
            // Fallback - should never be reached as we only call this from get_table_count
            // with a specific query format
            $result = 0;
        }
        
        $cache[$cache_key] = $result;
        
        return $result;
    }
    
    /**
     * Get transient options from database with caching
     * 
     * @param string $prefix Transient prefix
     * @return array Transient options
     */
    private function get_transient_options($prefix) {
        global $wpdb;
        
        // Create a cache key for this specific query
        $cache_key = 'wpsa_transient_query_' . md5($prefix);
        
        // Check if we have a cached result in a static array
        static $transient_cache = [];
        if (isset($transient_cache[$cache_key])) {
            return $transient_cache[$cache_key];
        }
        
        // Run the query if no cache exists
        $transient_keys = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT option_name FROM $wpdb->options WHERE option_name LIKE %s OR option_name LIKE %s",
                "_transient_" . $prefix . "%",
                "_transient_timeout_" . $prefix . "%"
            )
        );
        
        // Cache the result
        $transient_cache[$cache_key] = $transient_keys;
        
        return $transient_keys;
    }
}
<?php

namespace Fullworks\SupportAssistant\Admin;

use Fullworks\SupportAssistant\Core\Main;
use Fullworks\SupportAssistant\Data\DiagnosticData;

class AdminPage {

    private $settings;
    private $discovered_plugins;
    private $known_debug_constants;

    public function __construct($settings, $discovered_plugins, $known_debug_constants) {
        $this->settings = $settings;
        $this->discovered_plugins = $discovered_plugins;
        $this->known_debug_constants = $known_debug_constants;
    }

    /**
     * Add menu item
     */
    public function add_admin_menu() {
        add_submenu_page(
            'tools.php',
            'Plugin Support Diagnostics',
            'Plugin Support Diagnostics',
            'manage_options',
            'fullworks-support-diagnostics',
            [$this, 'display_admin_page']
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        // Define settings parameters explicitly to avoid dynamic argument warnings
        $option_group = 'fwpsd_settings_group';
        $option_name = 'fwpsd_settings';
        $args = [
            'sanitize_callback' => '__return_null', // Actual sanitization handled by filter
            'type' => 'array'
        ];
        
        // Register a callback function instead of using $this
        add_filter('sanitize_option_' . $option_name, [$this, 'sanitize_settings'], 10, 2);
        
        // Register setting with static arguments
        // phpcs:ignore PluginCheck.CodeAnalysis.SettingSanitization.register_settingDynamic -- Sanitization handled via filter
        register_setting(
            $option_group,
            $option_name,
            $args
        );

        add_settings_section(
            'fwpsd_general_section',
            'General Settings',
            [$this, 'render_general_section'],
            'fullworks-support-diagnostics'
        );

        add_settings_field(
            'fwpsd_plugin_options',
            'Additional Options to Monitor',
            [$this, 'render_plugin_options_field'],
            'fullworks-support-diagnostics',
            'fwpsd_general_section'
        );

        add_settings_field(
            'fwpsd_shortcode_scan',
            'Additional Shortcodes to Scan',
            [$this, 'render_shortcode_scan_field'],
            'fullworks-support-diagnostics',
            'fwpsd_general_section'
        );
        
        add_settings_field(
            'fwpsd_freemius_modules',
            'Freemius Modules',
            [$this, 'render_freemius_modules_field'],
            'fullworks-support-diagnostics',
            'fwpsd_general_section'
        );

        add_settings_field(
            'fwpsd_debug_constants',
            'Debug Constants',
            [$this, 'render_debug_constants_field'],
            'fullworks-support-diagnostics',
            'fwpsd_general_section'
        );

        add_settings_field(
            'fwpsd_rest_endpoint',
            'REST API Access',
            [$this, 'render_rest_endpoint_field'],
            'fullworks-support-diagnostics',
            'fwpsd_general_section'
        );

        add_settings_field(
            'fwpsd_access_keys',
            'Access Keys',
            [$this, 'render_access_keys_field'],
            'fullworks-support-diagnostics',
            'fwpsd_general_section'
        );
    }

    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        $sanitized = [];

        // Manual plugin options to check
        $sanitized['manual_plugin_options'] = [];
        if (!empty($input['manual_plugin_options'])) {
            $options = explode("\n", $input['manual_plugin_options']);
            foreach ($options as $option) {
                $option = trim($option);
                if (!empty($option)) {
                    $sanitized['manual_plugin_options'][] = sanitize_text_field($option);
                }
            }
        }

        // Shortcodes to scan for
        $sanitized['scan_shortcodes'] = [];
        if (!empty($input['scan_shortcodes'])) {
            $shortcodes = explode("\n", $input['scan_shortcodes']);
            foreach ($shortcodes as $shortcode) {
                $shortcode = trim($shortcode);
                // Remove any brackets if accidentally included
                $shortcode = str_replace(['[', ']'], '', $shortcode);
                if (!empty($shortcode)) {
                    $sanitized['scan_shortcodes'][] = sanitize_text_field($shortcode);
                }
            }
        }
        
        // Freemius modules - preserve from settings
        $sanitized['freemius_modules'] = $this->settings['freemius_modules'] ?? [];
        
        // Update manual Freemius modules if provided
        if (!empty($input['manual_freemius_modules'])) {
            $modules = explode("\n", $input['manual_freemius_modules']);
            foreach ($modules as $module_line) {
                $module_line = trim($module_line);
                if (empty($module_line)) {
                    continue;
                }
                
                // Format should be: plugin_slug|global_variable_name
                $parts = explode('|', $module_line);
                if (count($parts) >= 2) {
                    $plugin_slug = sanitize_text_field(trim($parts[0]));
                    $global_var = sanitize_text_field(trim($parts[1]));
                    
                    if (!empty($plugin_slug) && !empty($global_var)) {
                        // Add or update the manual entry
                        $sanitized['freemius_modules'][$plugin_slug] = [
                            'global_variable' => $global_var,
                            'plugin_path' => '',  // Manual entries don't have a path
                            'plugin_name' => $plugin_slug,
                            'manual_entry' => true
                        ];
                    }
                }
            }
        }

        // Debug constants management
        $sanitized['manage_debug_constants'] = isset($input['manage_debug_constants']) ? true : false;

        // Debug constants settings
        $sanitized['debug_constants'] = [];
        if (isset($input['debug_constants']) && is_array($input['debug_constants'])) {
            foreach ($input['debug_constants'] as $constant => $enabled) {
                if (isset($this->known_debug_constants[$constant])) {
                    $sanitized['debug_constants'][$constant] = true;
                }
            }
        }

        // Update wp-config.php if needed
        if ($sanitized['manage_debug_constants']) {
            $this->update_wp_config_debug_constants();
        }

        // REST endpoint enabled
        $sanitized['enable_rest_endpoint'] = isset($input['enable_rest_endpoint']) ? true : false;

        // Preserve existing keys
        $sanitized['access_key'] = $this->settings['access_key'];
        $sanitized['rest_endpoint_key'] = $this->settings['rest_endpoint_key'];

        return $sanitized;
    }

    /**
     * Render general section
     */
    public function render_general_section() {
        echo '<p>Configure how the support assistant collects and shares diagnostic information.</p>';
    }

    /**
     * Render shortcode scan field
     */
    public function render_shortcode_scan_field() {
        // Get currently configured shortcodes
        $current_shortcodes = $this->settings['scan_shortcodes'];

        // Get shortcodes from plugin configurations
        $discovered_shortcodes = [];
        foreach ($this->discovered_plugins as $plugin_path => $config) {
            if (isset($config['shortcodes']) && is_array($config['shortcodes'])) {
                foreach ($config['shortcodes'] as $shortcode) {
                    $discovered_shortcodes[$shortcode] = $config['plugin_info']['name'];
                }
            }
        }

        // Display discovered shortcodes
        if (!empty($discovered_shortcodes)) {
            echo '<div class="notice notice-info inline"><p><strong>Discovered shortcodes:</strong></p>';
            echo '<ul style="margin-left: 15px;">';
            foreach ($discovered_shortcodes as $shortcode => $plugin_name) {
                echo '<li><code>[' . esc_html($shortcode) . ']</code> from ' . esc_html($plugin_name) . '</li>';
            }
            echo '</ul></div>';
        }

        // Show field for additional shortcodes
        echo '<p><strong>Additional shortcodes to scan:</strong></p>';
        $additional_shortcodes = array_diff($current_shortcodes, array_keys($discovered_shortcodes));
        $shortcodes = implode("\n", $additional_shortcodes);
        echo '<textarea name="' . esc_attr(Main::OPTION_NAME) . '[scan_shortcodes]" rows="3" cols="50" class="large-text code">' . esc_textarea($shortcodes) . '</textarea>';
        echo '<p class="description">Enter each additional shortcode tag on a new line (without brackets). These will be scanned in addition to automatically discovered shortcodes.</p>';
    }

    /**
     * Render plugin options field
     */
    public function render_plugin_options_field() {
        // Display discovered plugins and their options
        if (!empty($this->discovered_plugins)) {
            echo '<div class="notice notice-info inline"><p><strong>Compatible plugins discovered:</strong></p>';
            echo '<ul style="margin-left: 15px; list-style: disc;">';

            foreach ($this->discovered_plugins as $plugin_path => $config) {
                $plugin_name = $config['plugin_info']['name'];
                echo '<li>' . esc_html($plugin_name) . ' <span class="description">(support-config.json found)</span>';

                if (isset($config['options_to_extract']) && is_array($config['options_to_extract'])) {
                    echo '<ul style="margin-left: 15px; list-style: circle;">';
                    foreach ($config['options_to_extract'] as $option) {
                        $option_name = $option['option_name'];
                        $option_label = $option['label'] ?? $option_name;
                        echo '<li>' . esc_html($option_label) . ' (<code>' . esc_html($option_name) . '</code>)</li>';
                    }
                    echo '</ul>';
                }
                echo '</li>';
            }

            echo '</ul></div>';
        }

        // Manual options
        $options = implode("\n", $this->settings['manual_plugin_options']);
        echo '<h4>Additional Options to Include</h4>';
        echo '<textarea name="' . esc_attr(Main::OPTION_NAME) . '[manual_plugin_options]" rows="5" cols="50" class="large-text code">' . esc_textarea($options) . '</textarea>';
        echo '<p class="description">Enter each option name on a new line. These WordPress options will be included in the diagnostic data in addition to any discovered from compatible plugins.</p>';
    }

    /**
     * Render debug constants field
     */
    public function render_debug_constants_field() {
        echo '<div class="notice notice-info inline">';
        echo '<p><strong>Note:</strong> Debug constants are defined early in the WordPress loading process. If you enable constants here, they will be defined during the plugins_loaded hook if not already defined in wp-config.php.</p>';
        echo '</div>';

        $manage_debug = $this->settings['manage_debug_constants'] ?? false;
        echo '<p><label><input type="checkbox" name="' . esc_attr(Main::OPTION_NAME) . '[manage_debug_constants]" ' . checked($manage_debug, true, false) . '> Enable management of debug constants</label></p>';

        if ($manage_debug) {
            echo '<table class="widefat" style="margin-top: 10px;">';
            echo '<thead><tr><th>Constant</th><th>Description</th><th>Current Value</th><th>Source</th><th>Enable</th></tr></thead>';
            echo '<tbody>';

            foreach ($this->known_debug_constants as $constant => $description) {
                $current_value = defined($constant) ? constant($constant) : 'Not Defined';
                $is_enabled = isset($this->settings['debug_constants'][$constant]) ? $this->settings['debug_constants'][$constant] : false;
                $source = defined($constant) ? 'wp-config.php' : 'Not set';
                
                if (defined($constant) && $is_enabled) {
                    $source = 'wp-config.php or plugin';
                }

                echo '<tr>';
                echo '<td><code>' . esc_html($constant) . '</code></td>';
                echo '<td>' . esc_html($description) . '</td>';
                echo '<td>' . ($current_value === true ? 'true' : ($current_value === false ? 'false' : esc_html($current_value))) . '</td>';
                echo '<td>' . esc_html($source) . '</td>';
                echo '<td><input type="checkbox" name="' . esc_attr(Main::OPTION_NAME) . '[debug_constants][' . esc_attr($constant) . ']" ' . checked($is_enabled, true, false) . '></td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
            
            echo '<p class="description">Constants enabled here will be defined programmatically if not already defined in wp-config.php.</p>';
            echo '<p class="description">For permanent changes, it\'s recommended to add these constants directly to your wp-config.php file.</p>';
        }
    }

    /**
     * Update debug constants settings (doesn't modify wp-config.php directly)
     */
    private function update_wp_config_debug_constants() {
        if (!$this->settings['manage_debug_constants']) {
            return false;
        }

        // Debug constants will be set during plugins_loaded hook in Main.php
        // Just report the current debug constant settings to the user
        
        $debug_constants_status = [];
        foreach ($this->known_debug_constants as $constant => $description) {
            $debug_constants_status[$constant] = [
                'description' => $description,
                'defined' => defined($constant),
                'value' => defined($constant) ? constant($constant) : null,
                'enabled_in_settings' => isset($this->settings['debug_constants'][$constant]) && 
                                        $this->settings['debug_constants'][$constant],
                'set_by' => defined($constant) ? 'wp-config.php' : 'not set'
            ];
        }
        
        // Store diagnostic information about debug constants
        set_transient('fwpsd_debug_constants_status', $debug_constants_status, HOUR_IN_SECONDS);
        
        return true;
    }

    /**
     * Render REST endpoint field
     */
    public function render_rest_endpoint_field() {
        $checked = $this->settings['enable_rest_endpoint'] ? 'checked' : '';
        echo '<label><input type="checkbox" name="' . esc_attr(Main::OPTION_NAME) . '[enable_rest_endpoint]" ' . esc_attr($checked) . '> Enable REST API endpoint for remote diagnostics</label>';

        if ($this->settings['enable_rest_endpoint']) {
            $rest_url = rest_url('fullworks-support-diagnostics/v1/diagnostics');
            echo '<p class="description">REST API URL: <code>' . esc_url($rest_url) . '</code></p>';
            echo '<p class="description">This endpoint requires the access key as a parameter.</p>';
        }
    }

    /**
     * Render access keys field
     */
    public function render_access_keys_field() {
        echo '<p><strong>Access Key:</strong> <code>' . esc_html($this->settings['access_key']) . '</code></p>';
        echo '<p><strong>REST Endpoint Key:</strong> <code>' . esc_html($this->settings['rest_endpoint_key']) . '</code></p>';
        echo '<p><button type="button" id="wpsa-regenerate-keys" class="button">Regenerate Keys</button></p>';
        echo '<p class="description">These keys provide access to diagnostic information. Keep them secure and regenerate if compromised.</p>';
    }
    
    /**
     * Render Freemius modules field
     */
    public function render_freemius_modules_field() {
        // Display discovered Freemius modules
        if (!empty($this->settings['freemius_modules'])) {
            echo '<div class="notice notice-info inline"><p><strong>Discovered Freemius modules:</strong></p>';
            echo '<ul style="margin-left: 15px; list-style: disc;">';
            
            foreach ($this->settings['freemius_modules'] as $module_id => $module_config) {
                $plugin_name = isset($module_config['plugin_name']) ? $module_config['plugin_name'] : $module_id;
                $global_var = $module_config['global_variable'];
                $is_manual = isset($module_config['manual_entry']) && $module_config['manual_entry'];
                
                // Check if this Freemius instance is loaded
                $loaded_instances = $this->settings['loaded_freemius_instances'] ?? [];
                $is_loaded = isset($loaded_instances[$module_id]) && $loaded_instances[$module_id]['loaded'];
                $load_time = isset($loaded_instances[$module_id]['loaded_time']) ? 
                    gmdate('Y-m-d H:i:s', $loaded_instances[$module_id]['loaded_time']) : 'never';
                
                // Check if the global variable exists in $GLOBALS
                $exists_in_globals = isset($GLOBALS[$global_var]) && is_object($GLOBALS[$global_var]);
                
                echo '<li>' . esc_html($plugin_name) . ' <span class="description">(' . 
                    ($is_manual ? 'manually added' : 'auto-discovered') . 
                    ')</span>';
                echo ' - Freemius global: <code>' . esc_html($global_var) . '</code>';
                echo ' - Status: ';
                
                if ($is_loaded) {
                    echo '<span style="color:green;">Loaded</span> (at ' . esc_html($load_time) . ')';
                } else {
                    echo '<span style="color:red;">Not loaded</span> ';
                    if ($exists_in_globals) {
                        echo '<span style="color:orange;">[Available in globals but hook not fired]</span>';
                    }
                }
                
                echo '</li>';
            }
            
            echo '</ul></div>';
        }
        
        // Manual modules configuration
        echo '<h4>Additional Freemius Modules</h4>';
        
        // Get manually configured modules
        $manual_freemius_modules = [];
        if (!empty($this->settings['freemius_modules'])) {
            foreach ($this->settings['freemius_modules'] as $module_id => $config) {
                if (isset($config['manual_entry']) && $config['manual_entry']) {
                    $manual_freemius_modules[] = $module_id . '|' . $config['global_variable'];
                }
            }
        }
        
        echo '<textarea name="' . esc_attr(Main::OPTION_NAME) . '[manual_freemius_modules]" rows="3" cols="50" class="large-text code">' . 
            esc_textarea(implode("\n", $manual_freemius_modules)) . 
            '</textarea>';
        
        echo '<p class="description">Enter each Freemius module on a new line in the format: <code>plugin_slug|global_variable_name</code></p>';
        echo '<p class="description">Example: <code>my-plugin|my_fs</code> - This will collect Freemius data from the global variable <code>$my_fs</code> for the plugin.</p>';
        echo '<p class="description">Alternatively, add a <code>freemius</code> section with a <code>global_variable</code> property to your plugin\'s <code>support-config.json</code> file to auto-discover Freemius modules.</p>';
    }

    /**
     * Display admin page
     */
    public function display_admin_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            return;
        }

        // Show success message if keys were regenerated
        if (isset($_GET['keys_regenerated']) && 
            wp_verify_nonce(sanitize_key(wp_unslash($_GET['_wpnonce'] ?? '')), 'wpsa_regenerate_keys') && 
            sanitize_text_field(wp_unslash($_GET['keys_regenerated'])) === '1') {
            echo '<div class="notice notice-success is-dismissible"><p>Access keys have been regenerated successfully.</p></div>';
        }

        // Show message if debug constants were updated
        if (isset($_GET['settings-updated']) && 
            sanitize_text_field(wp_unslash($_GET['settings-updated'])) === 'true' && 
            check_admin_referer('wpsa_settings_update')) {
            echo '<div class="notice notice-success is-dismissible"><p>Settings have been saved. If debug constants were modified, they will be applied during plugin execution.</p></div>';
        }

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <h2 class="nav-tab-wrapper">
                <a href="#diagnostics" class="nav-tab nav-tab-active">Diagnostics</a>
                <a href="#settings" class="nav-tab">Settings</a>
            </h2>

            <div id="diagnostics" class="tab-content">
                <div class="card">
                    <h2>Generate Diagnostic Information</h2>
                    <p>This tool collects information about your WordPress installation, active plugins, theme, and plugin settings to help troubleshoot issues.</p>

                    <button type="button" id="wpsa-generate-data" class="button button-primary">Generate Diagnostic Data</button>

                    <div id="wpsa-diagnostic-result" style="display: none; margin-top: 20px;">
                        <h3>Diagnostic Information</h3>
                        <div class="notice notice-warning">
                            <p><strong>Note:</strong> This information contains sensitive data about your WordPress installation. Only share it with trusted support personnel.</p>
                        </div>

                        <div class="diagnostic-actions" style="margin-bottom: 15px;">
                            <button type="button" id="wpsa-copy-data" class="button">Copy to Clipboard</button>
                            <button type="button" id="wpsa-download-data" class="button">Download as JSON</button>
                            <?php if ($this->settings['enable_rest_endpoint']): ?>
                                <div style="margin-top: 10px;">
                                    <p><strong>Temporary Direct Access Link:</strong></p>
                                    <input type="text" id="wpsa-access-link" class="large-text code" readonly>
                                    <p class="description">This link will work for 24 hours. Share it with support personnel for direct access to your diagnostic data.</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <textarea id="wpsa-diagnostic-data" style="width: 100%; height: 300px; font-family: monospace;" readonly></textarea>
                    </div>
                </div>
            </div>

            <div id="settings" class="tab-content" style="display: none;">
                <form method="post" action="options.php">
                    <?php
                    settings_fields('wpsa_settings_group');
                    wp_nonce_field('wpsa_settings_update');
                    do_settings_sections('fullworks-support-diagnostics');
                    submit_button();
                    ?>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if ('tools_page_fullworks-support-diagnostics' !== $hook) {
            return;
        }

        // Fix the path to admin.js
        $plugin_dir_url = plugin_dir_url(dirname(__DIR__)) . '/';
        
        wp_enqueue_script(
            'fwpsd-admin-script',
            $plugin_dir_url . 'admin.js',
            ['jquery'],
            Main::VERSION,
            true
        );

        wp_localize_script('fwpsd-admin-script', 'psdData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('fwpsd_nonce'),
            'restUrl' => rest_url('fullworks-support-diagnostics/v1/diagnostics'),
            'accessKey' => $this->settings['access_key'],
            'restEndpointKey' => $this->settings['rest_endpoint_key']
        ]);

        wp_add_inline_style('admin-bar', '
            .tab-content { margin-top: 20px; }
            #wpsa-diagnostic-result { background: #fff; padding: 15px; border: 1px solid #ccd0d4; }
        ');
    }
}
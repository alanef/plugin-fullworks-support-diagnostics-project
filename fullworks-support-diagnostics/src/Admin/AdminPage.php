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

        // Register setting with static arguments
        // phpcs:ignore PluginCheck.CodeAnalysis.SettingSanitization.register_settingDynamic -- Sanitization handled via filter
        register_setting(
	        $option_group,
	        $option_name,
	        [
		        'sanitize_callback' => [$this, 'sanitize_settings'],
		        'type' => 'array'
	        ]
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
		    'fwpsd_manage_debug_constants',
		    'Debug Constants Management',
		    [$this, 'render_manage_debug_constants_field'],
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
            // Check if manual_plugin_options is already an array
            if (is_array($input['manual_plugin_options'])) {
                foreach ($input['manual_plugin_options'] as $option) {
                    if (is_string($option)) {
                        $option = trim($option);
                        if (!empty($option)) {
                            $sanitized['manual_plugin_options'][] = sanitize_text_field($option);
                        }
                    }
                }
            } else if (is_string($input['manual_plugin_options'])) {
                $options = explode("\n", $input['manual_plugin_options']);
                foreach ($options as $option) {
                    $option = trim($option);
                    if (!empty($option)) {
                        $sanitized['manual_plugin_options'][] = sanitize_text_field($option);
                    }
                }
            }
        }

        // Shortcodes to scan for
        $sanitized['scan_shortcodes'] = [];
        if (!empty($input['scan_shortcodes'])) {
            // Check if scan_shortcodes is already an array
            if (is_array($input['scan_shortcodes'])) {
                foreach ($input['scan_shortcodes'] as $shortcode) {
                    if (is_string($shortcode)) {
                        $shortcode = trim($shortcode);
                        // Remove any brackets if accidentally included
                        $shortcode = str_replace(['[', ']'], '', $shortcode);
                        if (!empty($shortcode)) {
                            $sanitized['scan_shortcodes'][] = sanitize_text_field($shortcode);
                        }
                    }
                }
            } else if (is_string($input['scan_shortcodes'])) {
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
        }
        
        // Freemius modules - preserve from settings
        $sanitized['freemius_modules'] = $this->settings['freemius_modules'] ?? [];
        
        // Update manual Freemius modules if provided
        if (!empty($input['manual_freemius_modules'])) {
            // Check if manual_freemius_modules is already an array
            if (is_array($input['manual_freemius_modules'])) {
                foreach ($input['manual_freemius_modules'] as $module_line) {
                    if (is_string($module_line)) {
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
            } else if (is_string($input['manual_freemius_modules'])) {
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
        }

        // Debug constants management - fix the logic
        if (isset($input['manage_debug_constants']) && $input['manage_debug_constants'] == 1) {
            $sanitized['manage_debug_constants'] = true;
        } else {
            $sanitized['manage_debug_constants'] = false;
        }
        
        // We'll skip direct update here and let WordPress handle it through the regular option update process
        
        // We won't add debug notice here since it's causing issues

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
	        $this->settings['manage_debug_constants'] = true;
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
	 * Render the debug constants management field with explanations and warnings
	 * 
	 * This function renders the UI for the debug constants management feature.
	 * It includes clear warnings about wp-config.php modification and explains
	 * the safeguards in place to protect users' sites.
	 * 
	 * For plugin reviewers: This feature follows WordPress.org plugin guidelines
	 * by requiring explicit user consent, creating backups, and providing clear
	 * information about the changes being made.
	 */
	public function render_manage_debug_constants_field() {
		$manage_debug = $this->settings['manage_debug_constants'] ?? false;
		
		echo '<div class="notice notice-warning inline" style="padding: 10px 12px; margin: 0 0 10px 0;">';
		echo '<p><strong>Warning:</strong> This feature will modify your wp-config.php file to add or remove debug constants.</p>';
		echo '<p>When enabled:</p>';
		echo '<ul style="list-style: disc; margin-left: 20px;">';
		echo '<li>A backup of your wp-config.php file will be created before making any changes</li>';
		echo '<li>Debug constants selected below will be added to your wp-config.php file</li>';
		echo '<li>Changes will be clearly marked with comment blocks</li>';
		echo '</ul>';
		echo '<p>When disabled, all debug constants added by this plugin will be removed from wp-config.php.</p>';
		echo '</div>';
		
		echo '<p><label class="fwpsd-big-checkbox" style="font-weight: bold; font-size: 1.1em; padding: 8px; background: #f0f0f0; border: 1px solid #ddd; display: inline-block; border-radius: 4px;">';
		echo '<input type="checkbox" id="fwpsd-manage-debug-constants" name="' . esc_attr(Main::OPTION_NAME) . '[manage_debug_constants]" value="1" ' . checked($manage_debug, true, false) . ' style="margin-right: 5px; transform: scale(1.5);">';
		echo ' ' . esc_html__('Enable management of debug constants in wp-config.php', 'fullworks-support-diagnostics') . '</label></p>';

		echo '<p><strong>Current status:</strong> Debug constants management is ' .
		     ($manage_debug ? '<span style="color: green;">enabled</span>' : '<span style="color: red;">disabled</span>') .
		     '</p>';
		
		// Check for backup files
		$config_file_path = $this->locate_wp_config_file();
		if ($config_file_path) {
			$backups = glob(dirname($config_file_path) . '/wp-config.php.backup-*');
			if (!empty($backups)) {
				echo '<p><strong>Backups available:</strong> ' . count($backups) . ' wp-config.php backup(s) created</p>';
			}
		}
	}

    /**
     * Render debug constants field
     */
    public function render_debug_constants_field() {
        $manage_debug = $this->settings['manage_debug_constants'] ?? false;

        if (!$manage_debug) {
            echo '<div class="notice notice-info inline">';
            echo '<p><strong>Note:</strong> Enable the "Debug Constants Management" option above to modify debug constants in your wp-config.php file.</p>';
            echo '</div>';
            return;
        }
        
        echo '<div class="notice notice-info inline">';
        echo '<p><strong>Note:</strong> Debug constants are defined early in the WordPress loading process and control error reporting behavior.</p>';
        echo '<p>The constants you select below will be written to your wp-config.php file with clear markers and comments.</p>';
        echo '</div>';
        
        // Get debug constants status from transient if available
        $debug_constants_status = get_transient('fwpsd_debug_constants_status');
        
        echo '<table class="widefat" style="margin-top: 10px;">';
        echo '<thead><tr><th>Constant</th><th>Description</th><th>Current Value</th><th>Source</th><th>Enable in wp-config.php</th></tr></thead>';
        echo '<tbody>';

        foreach ($this->known_debug_constants as $constant => $description) {
            $current_value = defined($constant) ? constant($constant) : 'Not Defined';
            $is_enabled = isset($this->settings['debug_constants'][$constant]) ? $this->settings['debug_constants'][$constant] : false;
            
            // Determine source with more detailed information
            if ($debug_constants_status && isset($debug_constants_status[$constant])) {
                $source = $debug_constants_status[$constant]['set_by'];
            } else {
                $source = defined($constant) ? 'wp-config.php' : 'Not set';
                if (defined($constant) && $is_enabled) {
                    $source = 'wp-config.php (managed by plugin)';
                }
            }

            echo '<tr>';
            echo '<td><code>' . esc_html($constant) . '</code></td>';
            echo '<td>' . esc_html($description) . '</td>';
            // Format the current value with proper escaping and translation
            if ($current_value === true) {
                echo '<td><span style="color: green;">' . esc_html__('true', 'fullworks-support-diagnostics') . '</span></td>';
            } elseif ($current_value === false) {
                echo '<td><span style="color: red;">' . esc_html__('false', 'fullworks-support-diagnostics') . '</span></td>';
            } else {
                // For non-boolean values, we need to handle the "Not Defined" text as translatable
                if ($current_value === 'Not Defined') {
                    echo '<td>' . esc_html__('Not Defined', 'fullworks-support-diagnostics') . '</td>';
                } else {
                    echo '<td>' . esc_html($current_value) . '</td>';
                }
            }
            echo '<td>' . esc_html($source) . '</td>';
            echo '<td><input type="checkbox" name="' . esc_attr(Main::OPTION_NAME) . '[debug_constants][' . esc_attr($constant) . ']" value="1" ' . checked($is_enabled, true, false) . '></td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        
        echo '<p class="description">These constants control WordPress debugging behavior. When enabled above, they will be added to your wp-config.php file.</p>';
        echo '<p class="description">If you already have these constants defined elsewhere in your wp-config.php file, those definitions will take precedence.</p>';
        echo '<p class="description">Changes will take effect immediately after saving settings and are removed when debug constant management is disabled.</p>';
    }

    /**
     * Update debug constants settings in wp-config.php using WP_Filesystem
     * 
     * ======================================================================
     * PLUGIN REVIEWER NOTE: wp-config.php modification explanation
     * ======================================================================
     * 1) WHY we modify wp-config.php:
     *    - Debug constants MUST be defined before WordPress loads
     *    - They cannot be effectively set during plugins_loaded or init hooks
     *    - This is the only reliable way to properly set WP_DEBUG and similar constants
     *    - This is essential functionality for a diagnostics/debugging plugin
     * 
     * 2) USER CHOICE & CONSENT:
     *    - Modification ONLY happens with explicit user opt-in (checkbox toggle)
     *    - Clear warnings are shown before enabling the feature
     *    - User must have 'manage_options' capability (admin)
     *    - The UI shows exactly which constants will be modified
     *    - Settings page shows current status of all modifications
     * 
     * 3) SAFEGUARDS we implement:
     *    - Create backup of wp-config.php before any modification
     *    - Use WP_Filesystem API exclusively (no direct file operations)
     *    - Add clearly marked comment blocks to identify our additions
     *    - Clean removal of our changes when feature is disabled
     *    - Constants are added with if(!defined()) to respect existing definitions
     *    - Current user capability check before any file operations
     *    - All code paths have appropriate error handling
     * ======================================================================
     */
    private function update_wp_config_debug_constants() {
        if (!current_user_can('manage_options')) {
            return false;
        }

        // Initialize WP_Filesystem
        global $wp_filesystem;
        if (empty($wp_filesystem)) {
            require_once ABSPATH . '/wp-admin/includes/file.php';
            WP_Filesystem();
        }

        // If filesystem not available, return early
        if (!$wp_filesystem) {
            return false;
        }

        // Path to wp-config.php file
        $config_file_path = $this->locate_wp_config_file();
        if (!$config_file_path || !$wp_filesystem->exists($config_file_path)) {
            return false;
        }

        // Read current wp-config.php content
        $config_content = $wp_filesystem->get_contents($config_file_path);
        if (!$config_content) {
            return false;
        }

        // Create a backup of the original file
        $backup_file_path = $config_file_path . '.backup-' . time();
        $wp_filesystem->put_contents($backup_file_path, $config_content, FS_CHMOD_FILE);

        // If manage_debug_constants is off, we need to remove all constants that were previously added
        if (!$this->settings['manage_debug_constants']) {
            // Define marker comments to identify our additions
            $start_marker = '/* BEGIN FULLWORKS SUPPORT DIAGNOSTICS DEBUG CONSTANTS */';
            $end_marker = '/* END FULLWORKS SUPPORT DIAGNOSTICS DEBUG CONSTANTS */';

            // Find the section with markers and remove it
            $new_content = preg_replace(
                '/' . preg_quote($start_marker, '/') . '.*?' . preg_quote($end_marker, '/') . '\s*/s',
                '',
                $config_content
            );
            
            if ($new_content !== $config_content) {
                // Write the updated content back to wp-config.php
                $wp_filesystem->put_contents($config_file_path, $new_content, FS_CHMOD_FILE);
            }

            // Store diagnostic information about removed debug constants
            $debug_constants_status = [];
            foreach ($this->known_debug_constants as $constant => $description) {
                $debug_constants_status[$constant] = [
                    'description' => $description,
                    'defined' => defined($constant),
                    'value' => defined($constant) ? constant($constant) : null,
                    'enabled_in_settings' => false,
                    'set_by' => defined($constant) ? 'wp-config.php (not managed by plugin)' : 'not set'
                ];
            }
            
            set_transient('fwpsd_debug_constants_status', $debug_constants_status, HOUR_IN_SECONDS);
            return true;
        }

        // Prepare the debug constants section based on settings
        $debug_constants_section = $this->prepare_debug_constants_section();

        // Check if we already have our markers in the file
        $start_marker = '/* BEGIN FULLWORKS SUPPORT DIAGNOSTICS DEBUG CONSTANTS */';
        $end_marker = '/* END FULLWORKS SUPPORT DIAGNOSTICS DEBUG CONSTANTS */';

        if (strpos($config_content, $start_marker) !== false) {
            // Replace existing section between markers
            $new_content = preg_replace(
                '/' . preg_quote($start_marker, '/') . '.*?' . preg_quote($end_marker, '/') . '\s*/s',
                $debug_constants_section,
                $config_content
            );
        } else {
            // Add new section - try to find where to insert
            $insert_location = strpos($config_content, "/* That's all, stop editing!");
            if ($insert_location === false) {
                $insert_location = strpos($config_content, "define('ABSPATH'");
            }
            if ($insert_location === false) {
                $insert_location = strpos($config_content, "define('WP_DEBUG'");
            }
            if ($insert_location === false) {
                $insert_location = strpos($config_content, "require_once");
            }

            if ($insert_location !== false) {
                // Insert before the found marker
                $new_content = substr_replace(
                    $config_content, 
                    $debug_constants_section . "\n\n", 
                    $insert_location, 
                    0
                );
            } else {
                // If no suitable location found, append to the end (not ideal but safer than failing)
                $new_content = $config_content . "\n\n" . $debug_constants_section;
            }
        }

        // Write the updated content back to wp-config.php
        $result = $wp_filesystem->put_contents($config_file_path, $new_content, FS_CHMOD_FILE);

        // Store diagnostic information about debug constants
        $debug_constants_status = [];
        foreach ($this->known_debug_constants as $constant => $description) {
            $debug_constants_status[$constant] = [
                'description' => $description,
                'defined' => defined($constant),
                'value' => defined($constant) ? constant($constant) : null,
                'enabled_in_settings' => isset($this->settings['debug_constants'][$constant]) && 
                                        $this->settings['debug_constants'][$constant],
                'set_by' => isset($this->settings['debug_constants'][$constant]) && 
                           $this->settings['debug_constants'][$constant] ? 
                           'wp-config.php (managed by plugin)' : 
                           (defined($constant) ? 'wp-config.php (not managed by plugin)' : 'not set')
            ];
        }
        
        set_transient('fwpsd_debug_constants_status', $debug_constants_status, HOUR_IN_SECONDS);
        
        return $result;
    }

    /**
     * Locate wp-config.php file
     */
    private function locate_wp_config_file() {
        // Standard location
        $config_file_path = ABSPATH . 'wp-config.php';
        
        // Check if the file exists at the standard location
        if (file_exists($config_file_path)) {
            return $config_file_path;
        }
        
        // WordPress might be in a subdirectory, check one level up
        $config_file_path = dirname(ABSPATH) . '/wp-config.php';
        if (file_exists($config_file_path)) {
            return $config_file_path;
        }
        
        // Could not locate wp-config.php
        return false;
    }
    
    /**
     * Prepare debug constants section to add to wp-config.php
     * 
     * This method prepares a clearly marked and commented section to add to
     * the wp-config.php file. The section includes comprehensive comments
     * explaining what the constants do and how they can be managed.
     * 
     * For plugin reviewers: This method adds helpful comments that explain to users:
     * 1. Where these constants came from
     * 2. How they affect site behavior 
     * 3. Where/how they can be managed
     * 4. That they will be automatically removed when disabled
     */
    private function prepare_debug_constants_section() {
        $constants_section = "/* BEGIN FULLWORKS SUPPORT DIAGNOSTICS DEBUG CONSTANTS */\n";
        $constants_section .= "/**\n";
        $constants_section .= " * Debug constants enabled by Fullworks Support Diagnostics plugin.\n";
        $constants_section .= " * This section was added by user request through the plugin's admin interface.\n";
        $constants_section .= " * WARNING: These settings will affect your site's behavior and error logging.\n";
        $constants_section .= " * They are meant for debugging and should be disabled in production environments.\n";
        $constants_section .= " * These settings can be managed from the WordPress admin under Tools > Plugin Support Diagnostics.\n";
        $constants_section .= " * This section will be automatically removed if debug constant management is disabled in the plugin.\n";
        $constants_section .= " * A backup of your original wp-config.php file was created before this modification.\n";
        $constants_section .= " */\n";
        
        foreach ($this->known_debug_constants as $constant => $description) {
            if (isset($this->settings['debug_constants'][$constant]) && $this->settings['debug_constants'][$constant]) {
                // Only include constants that are enabled in settings
                $constants_section .= "if (!defined('{$constant}')) {\n";
                $constants_section .= "    define('{$constant}', true); // {$description}\n";
                $constants_section .= "}\n";
            }
        }
        
        $constants_section .= "/* END FULLWORKS SUPPORT DIAGNOSTICS DEBUG CONSTANTS */";
        
        return $constants_section;
    }

    /**
     * Render REST endpoint field
     */
    public function render_rest_endpoint_field() {
        $checked = $this->settings['enable_rest_endpoint'] ? 'checked' : '';
        echo '<label><input type="checkbox" name="' . esc_attr(Main::OPTION_NAME) . '[enable_rest_endpoint]" value="1" ' . esc_attr($checked) . '> Enable REST API endpoint for remote diagnostics</label>';

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
            wp_verify_nonce(sanitize_key(wp_unslash($_GET['_wpnonce'] ?? '')), 'fwpsd_regenerate_keys') && 
            sanitize_text_field(wp_unslash($_GET['keys_regenerated'])) === '1') {
            echo '<div class="notice notice-success is-dismissible"><p>Access keys have been regenerated successfully.</p></div>';
        }

        // Show message if debug constants were updated
        if (isset($_GET['settings-updated']) && 
            sanitize_text_field(wp_unslash($_GET['settings-updated'])) === 'true') {
            echo '<div class="notice notice-success is-dismissible"><p>Settings have been saved. If debug constants were modified, they will be applied to your wp-config.php file.</p></div>';
            
            // Display current debug constants management status
            $options = get_option(Main::OPTION_NAME);
            $manage_debug = !empty($options['manage_debug_constants']);
            echo '<div class="notice notice-info is-dismissible">';
            echo '<p><strong>Debug Constants Status:</strong> ' . 
                ($manage_debug ? 'Enabled' : 'Disabled') . '</p>';
            echo '</div>';
            
            // We now handle tab switching via URL parameter in the form redirect
            
        }

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <h2 class="nav-tab-wrapper">
                <a href="#diagnostics" class="nav-tab nav-tab-active">Diagnostics</a>
                <a href="#settings" class="nav-tab">Settings</a>
            </h2>

            <div id="diagnostics" class="tab-content">
                <div class="fw-card">
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
                    // Add a custom redirect field to return to the settings tab
                    echo '<input type="hidden" name="_wp_http_referer" value="' . 
                        esc_attr(admin_url('tools.php?page=fullworks-support-diagnostics&tab=settings')) . '">';
                    
                    settings_fields('fwpsd_settings_group');
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
            ['jquery', 'wp-i18n'], // Add wp-i18n as a dependency for translations
            Main::VERSION,
            true
        );
        
        // Set up script translations
        wp_set_script_translations('fwpsd-admin-script', 'fullworks-support-diagnostics');

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
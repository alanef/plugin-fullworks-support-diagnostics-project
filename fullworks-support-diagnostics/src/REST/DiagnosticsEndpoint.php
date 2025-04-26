<?php

namespace Fullworks\SupportAssistant\REST;

use Fullworks\SupportAssistant\Core\Main;
use Fullworks\SupportAssistant\Data\DiagnosticData;

class DiagnosticsEndpoint {

    private $settings;
    private $discovered_plugins;
    private $known_debug_constants;

    public function __construct($settings, $discovered_plugins, $known_debug_constants) {
        $this->settings = $settings;
        $this->discovered_plugins = $discovered_plugins;
        $this->known_debug_constants = $known_debug_constants;
    }

    /**
     * Register REST API route
     */
    public function register_rest_route() {
        if (!$this->settings['enable_rest_endpoint']) {
            return;
        }

        register_rest_route('fullworks-support-diagnostics/v1', '/diagnostics', [
            'methods' => 'GET',
            'callback' => [$this, 'rest_api_callback'],
            'permission_callback' => [$this, 'rest_api_permission_check']
        ]);
    }

    /**
     * REST API permission check
     */
    public function rest_api_permission_check($request) {
        // Get request parameters
        $access_key = $request->get_param('access_key');
        $endpoint_key = $request->get_param('endpoint_key');
        $transient_key = sanitize_key($request->get_param('transient_key') ?? '');
        
        // Check if there's a valid transient with the diagnostic data
        if (!empty($transient_key)) {
            $transient_name = 'wpsa_' . $transient_key;
            $has_transient = get_transient($transient_name) !== false;
            if ($has_transient) {
                return true;
            }
        }
        
        // Check if both keys match
        if ($access_key === $this->settings['access_key'] && $endpoint_key === $this->settings['rest_endpoint_key']) {
            return true;
        }

        return false;
    }

    /**
     * REST API callback
     */
    public function rest_api_callback($request) {
        // Check if using transient key
        $transient_key = sanitize_key($request->get_param('transient_key') ?? '');
        if (!empty($transient_key)) {
            $transient_name = 'wpsa_' . $transient_key;
            $data = get_transient($transient_name);
            
            if ($data !== false) {
                return rest_ensure_response($data);
            }
        }

        // Otherwise generate fresh diagnostic data
        $diagnostic_data = $this->generate_diagnostic_data();
        return rest_ensure_response($diagnostic_data);
    }

    /**
     * AJAX: Generate diagnostic data
     */
    public function ajax_generate_diagnostic_data() {
        check_ajax_referer('fwpsd_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        $diagnostic_data = $this->generate_diagnostic_data();

        // Create a transient for direct access
        $transient_key = $this->generate_random_key(16);
        $transient_name = 'wpsa_' . $transient_key;
        set_transient($transient_name, $diagnostic_data, DAY_IN_SECONDS);
        
        // Verify transient was set successfully
        $transient_exists = get_transient($transient_name) !== false;

        $direct_access_url = add_query_arg([
            'transient_key' => $transient_key
        ], rest_url('fullworks-support-diagnostics/v1/diagnostics'));

        wp_send_json_success([
            'data' => $diagnostic_data,
            'direct_access_url' => $direct_access_url
        ]);
    }

    /**
     * AJAX: Regenerate keys
     */
    public function ajax_regenerate_keys() {
        check_ajax_referer('fwpsd_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        // Generate new keys
        $main = new Main();
        $this->settings['access_key'] = $main->generate_random_key(12);
        $this->settings['rest_endpoint_key'] = $main->generate_random_key(32);

        // Save updated settings
        update_option(Main::OPTION_NAME, $this->settings);

        wp_send_json_success([
            'message' => 'Keys regenerated successfully.',
            'access_key' => $this->settings['access_key'],
            'rest_endpoint_key' => $this->settings['rest_endpoint_key']
        ]);
    }

    /**
     * Generate diagnostic data
     */
    private function generate_diagnostic_data() {
        $diagnosticData = new DiagnosticData(
            $this->settings,
            $this->discovered_plugins,
            $this->known_debug_constants
        );

        return $diagnosticData->generate_diagnostic_data();
    }

    /**
     * Generate a random key
     */
    private function generate_random_key($length = 12) {
        return substr(str_shuffle(MD5(microtime())), 0, $length);
    }
}
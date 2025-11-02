<?php
/**
 * Main Plugin Class (Backward Compatibility Wrapper)
 *
 * This class is kept for backward compatibility with existing code that may
 * reference it. All functionality has been moved to the PluginPulse Connect Library.
 *
 * @package Fullworks\SupportAssistant\Core
 * @deprecated 1.1.0 Use PluginPulse\Library\Core\LibraryBootstrap instead
 */

namespace Fullworks\SupportAssistant\Core;

/**
 * Class Main
 *
 * Backward compatibility wrapper for the old plugin structure.
 * The library now handles all functionality via LibraryBootstrap.
 *
 * @deprecated 1.1.0
 */
class Main {

	/**
	 * Plugin version
	 */
	const VERSION = '1.1.0';

	/**
	 * Option name for settings
	 */
	const OPTION_NAME = 'fwpsd_settings';

	/**
	 * Transient name for diagnostic data
	 */
	const TRANSIENT_NAME = 'fwpsd_diagnostic_data';

	/**
	 * Constructor
	 *
	 * This class is now just a backward compatibility wrapper.
	 * All functionality is handled by PluginPulse\Library\Core\LibraryBootstrap.
	 *
	 * @deprecated 1.1.0 Use LibraryBootstrap::init() directly in your plugin file
	 */
	public function __construct() {
		// Trigger deprecation notice in debug mode
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log(
				'Fullworks\SupportAssistant\Core\Main is deprecated since version 1.1.0. ' .
				'Use PluginPulse\Library\Core\LibraryBootstrap::init() instead.'
			);
		}

		// Library initialization is now handled in the main plugin file
		// via wpsa_initialize_plugin() function
	}

	/**
	 * Generate a random key
	 *
	 * Kept for backward compatibility with external code that may call this.
	 *
	 * @param int $length Key length.
	 * @return string Random key.
	 * @deprecated 1.1.0
	 */
	public function generate_random_key( $length = 12 ) {
		return substr( str_shuffle( md5( microtime() ) ), 0, $length );
	}
}
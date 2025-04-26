<?php
/**
 * PHPUnit bootstrap file.
 */

// Require composer autoloader.
require_once dirname(__DIR__) . '/vendor/autoload.php';

// If we're running in WP's build directory, ensure that WP knows that too.
if (false !== getenv('WP_PHPUNIT__TESTS_CONFIG')) {
    $test_config = getenv('WP_PHPUNIT__TESTS_CONFIG');
    echo "Using WP PHPUnit test config at $test_config" . PHP_EOL;
    require_once $test_config;
} else {
    echo "Could not find a WordPress test config" . PHP_EOL;
    exit(1);
}
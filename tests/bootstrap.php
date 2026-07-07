<?php
/**
 * Test Bootstrap File
 *
 * Sets up test environment for PHPUnit
 *
 * @package BSO\Survival\Tests
 */

// Autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Define constants if not already defined (for WordPress-less testing)
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/../');
}

if (!defined('WP_CONTENT_DIR')) {
    define('WP_CONTENT_DIR', ABSPATH . 'wp-content');
}

// Setup PSR-4 autoloading for src/
$loader = new \Composer\Autoload\ClassLoader();
$loader->addPsr4('BSO\\Survival\\', ABSPATH . 'src/');
$loader->addPsr4('BSO\\Survival\\Tests\\', ABSPATH . 'tests/');
$loader->register();

// Setup test environment
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Mock WordPress functions if needed (for standalone testing)
if (!function_exists('add_action')) {
    require_once __DIR__ . '/mocks/wordpress-functions.php';
}

// Allow tests to set custom WordPress behavior
if (file_exists(__DIR__ . '/setup.php')) {
    require_once __DIR__ . '/setup.php';
}

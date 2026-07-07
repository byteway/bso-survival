<?php
/**
 * Plugin Name: BSO Survival
 * Plugin URI: https://example.com/bso-survival
 * Description: BSO Survival v2 plugin bootstrap.
 * Version: 2.0.0
 * Author: BSO Survival Team
 * License: GPL-2.0-or-later
 * Text Domain: bso-survival
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('BSO_SURVIVAL_VERSION')) {
    define('BSO_SURVIVAL_VERSION', '2.0.0');
}

if (!defined('BSO_SURVIVAL_PLUGIN_FILE')) {
    define('BSO_SURVIVAL_PLUGIN_FILE', __FILE__);
}

$autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

if (
    function_exists('register_activation_hook') &&
    class_exists(\BSO\Survival\Core\Activator::class)
) {
    register_activation_hook(__FILE__, [\BSO\Survival\Core\Activator::class, 'activate']);
}

if (class_exists(\BSO\Survival\Core\Plugin::class)) {
    (new \BSO\Survival\Core\Plugin())->register();
}

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

$autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

if (class_exists(\BSO\Survival\Core\Plugin::class)) {
    (new \BSO\Survival\Core\Plugin())->register();
}

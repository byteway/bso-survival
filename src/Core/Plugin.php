<?php

namespace BSO\Survival\Core;

use BSO\Survival\Core\Cli\SeedGoldenDatasetCommand;
use BSO\Survival\Frontend\ShortcodeController;

class Plugin {
    public function register(): void {
        add_action('plugins_loaded', [$this, 'load_textdomain']);
        add_action('init', [$this, 'register_shortcodes']);
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);

        $this->register_cli_commands();
    }

    public function load_textdomain(): void {
        load_plugin_textdomain(
            'bso-survival',
            false,
            dirname(plugin_basename(__DIR__ . '/../../bso-survival.php')) . '/languages'
        );
    }

    public function register_shortcodes(): void {
        (new ShortcodeController())->register();
    }

    public function register_assets(): void {
        wp_register_style(
            'bso-survival-frontend',
            plugins_url('assets/css/bso-survival.css', __DIR__ . '/../../bso-survival.php'),
            [],
            '2.0.0'
        );

        wp_register_script(
            'bso-survival-frontend',
            plugins_url('assets/js/bso-survival.js', __DIR__ . '/../../bso-survival.php'),
            [],
            '2.0.0',
            true
        );
    }

    private function register_cli_commands(): void {
        if (!defined('WP_CLI') || WP_CLI !== true || !class_exists('WP_CLI')) {
            return;
        }

        if (class_exists(SeedGoldenDatasetCommand::class)) {
            \WP_CLI::add_command('bso-survival seed-golden', SeedGoldenDatasetCommand::class);
        }
    }
}

<?php

namespace BSO\Survival\Frontend;

class ShortcodeController {
    public const TAG = 'bso_survival_dashboard';

    public function register(): void {
        add_shortcode(self::TAG, [$this, 'render']);
    }

    public function render(array $atts = []): string {
        wp_enqueue_style('bso-survival-frontend');
        wp_enqueue_script('bso-survival-frontend');

        $attributes = shortcode_atts([
            'title' => __('BSO Survival Dashboard', 'bso-survival'),
        ], $atts, self::TAG);

        ob_start();
        $title = (string) $attributes['title'];
        include __DIR__ . '/../../templates/frontend-dashboard.php';
        return (string) ob_get_clean();
    }
}

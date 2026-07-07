<?php

namespace BSO\Survival\Admin;

use BSO\Survival\Service\EventService;
use BSO\Survival\Service\PartRuleConfiguratorService;
use BSO\Survival\Service\ScoringMethodRegistry;

class PartRuleAdminPage {
    /** @var EventService */
    private $events;

    /** @var PartRuleConfiguratorService */
    private $configurator;

    /** @var object */
    private $rules;

    public function __construct(EventService $events, PartRuleConfiguratorService $configurator, $rulesRepository) {
        $this->events = $events;
        $this->configurator = $configurator;
        $this->rules = $rulesRepository;
    }

    public function registerMenu(): void {
        if (!function_exists('add_menu_page')) {
            return;
        }

        add_menu_page(
            __('BSO Survival Rules', 'bso-survival'),
            __('BSO Rules', 'bso-survival'),
            'manage_options',
            'bso-survival-rules',
            [$this, 'renderPage'],
            'dashicons-editor-ol',
            58
        );
    }

    public function handleSave(): void {
        if (!function_exists('current_user_can') || !current_user_can('manage_options')) {
            wp_die(__('Onvoldoende rechten.', 'bso-survival'));
        }

        $partId = isset($_POST['part_id']) ? (int) $_POST['part_id'] : 0;
        $eventId = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;
        $mode = isset($_POST['scoring_mode']) ? sanitize_text_field(wp_unslash((string) $_POST['scoring_mode'])) : '';

        $config = [
            'max_time' => isset($_POST['max_time']) ? (int) $_POST['max_time'] : null,
            'max_points' => isset($_POST['max_points']) ? (int) $_POST['max_points'] : null,
            'max_distance' => isset($_POST['max_distance']) ? (int) $_POST['max_distance'] : null,
            'normalization_curve' => isset($_POST['normalization_curve'])
                ? sanitize_text_field(wp_unslash((string) $_POST['normalization_curve']))
                : 'linear',
        ];

        $this->configurator->configure($partId, $mode, $config);

        $redirect = add_query_arg(
            [
                'page' => 'bso-survival-rules',
                'event_id' => $eventId,
                'saved' => 1,
            ],
            admin_url('admin.php')
        );

        wp_safe_redirect($redirect);
        exit;
    }

    public function renderPage(): void {
        if (!function_exists('current_user_can') || !current_user_can('manage_options')) {
            wp_die(__('Onvoldoende rechten.', 'bso-survival'));
        }

        $events = $this->events->listEvents();
        $eventId = isset($_GET['event_id']) ? (int) $_GET['event_id'] : 0;
        if ($eventId <= 0 && !empty($events)) {
            $eventId = (int) $events[0]->id;
        }

        $rows = $eventId > 0 ? $this->rules->findByEventId($eventId) : [];
        $methods = ScoringMethodRegistry::all();

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('BSO Survival Part Rules', 'bso-survival') . '</h1>';

        if (isset($_GET['saved']) && (int) $_GET['saved'] === 1) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Regel opgeslagen.', 'bso-survival') . '</p></div>';
        }

        echo '<form method="get" action="' . esc_url(admin_url('admin.php')) . '">';
        echo '<input type="hidden" name="page" value="bso-survival-rules" />';
        echo '<label for="bso-event-id"><strong>' . esc_html__('Event', 'bso-survival') . ':</strong></label> ';
        echo '<select id="bso-event-id" name="event_id">';
        foreach ($events as $event) {
            $selected = selected($eventId, (int) $event->id, false);
            echo '<option value="' . (int) $event->id . '" ' . $selected . '>' . esc_html($event->name) . '</option>';
        }
        echo '</select> ';
        echo '<button class="button">' . esc_html__('Laden', 'bso-survival') . '</button>';
        echo '</form>';

        echo '<hr />';

        foreach ($rows as $row) {
            $mode = is_string($row->scoring_mode) && $row->scoring_mode !== '' ? $row->scoring_mode : 'points';
            $config = json_decode((string) ($row->scoring_config ?? ''), true);
            if (!is_array($config)) {
                $config = [];
            }

            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="border:1px solid #dcdcde;padding:12px;margin-bottom:12px;">';
            echo '<input type="hidden" name="action" value="bso_survival_save_part_rule" />';
            echo '<input type="hidden" name="part_id" value="' . (int) $row->part_id . '" />';
            echo '<input type="hidden" name="event_id" value="' . (int) $eventId . '" />';

            echo '<h3 style="margin-top:0;">' . esc_html((string) $row->part_name) . '</h3>';

            echo '<p><label><strong>' . esc_html__('Scoring mode', 'bso-survival') . '</strong><br />';
            echo '<select name="scoring_mode">';
            foreach ($methods as $id => $method) {
                $selected = selected($mode, $id, false);
                echo '<option value="' . esc_attr($id) . '" ' . $selected . '>' . esc_html($method->getName()) . '</option>';
            }
            echo '</select></label></p>';

            echo '<p><label>max_time<br /><input type="number" name="max_time" value="' . esc_attr((string) ($config['max_time'] ?? 1200)) . '" /></label></p>';
            echo '<p><label>max_points<br /><input type="number" name="max_points" value="' . esc_attr((string) ($config['max_points'] ?? 100)) . '" /></label></p>';
            echo '<p><label>max_distance<br /><input type="number" name="max_distance" value="' . esc_attr((string) ($config['max_distance'] ?? 500)) . '" /></label></p>';

            echo '<p><label>normalization_curve<br /><input type="text" name="normalization_curve" value="' . esc_attr((string) ($config['normalization_curve'] ?? 'linear')) . '" /></label></p>';

            echo '<button class="button button-primary">' . esc_html__('Opslaan', 'bso-survival') . '</button>';
            echo '</form>';
        }

        echo '</div>';
    }
}

<?php

namespace BSO\Survival\Admin;

use BSO\Survival\Service\EventService;
use BSO\Survival\Service\PartRuleConfiguratorService;
use BSO\Survival\Service\ScoringMethodRegistry;

class PartRuleAdminPage {
    private const SAVE_NONCE_ACTION = 'bso_survival_save_part_rule';
    private const SAVE_NONCE_FIELD = 'bso_survival_save_part_rule_nonce';

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

        if (!isset($_POST[self::SAVE_NONCE_FIELD]) || !wp_verify_nonce((string) $_POST[self::SAVE_NONCE_FIELD], self::SAVE_NONCE_ACTION)) {
            wp_die(__('Ongeldige aanvraag (nonce).', 'bso-survival'));
        }

        $partId = isset($_POST['part_id']) ? (int) $_POST['part_id'] : 0;
        $eventId = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;
        $mode = isset($_POST['scoring_mode']) ? sanitize_key(wp_unslash((string) $_POST['scoring_mode'])) : '';
        $tiebreakerMode = isset($_POST['tiebreaker_mode']) ? sanitize_key(wp_unslash((string) $_POST['tiebreaker_mode'])) : 'manual_referee';

        $config = [
            'max_time' => isset($_POST['max_time']) ? (int) $_POST['max_time'] : null,
            'max_points' => isset($_POST['max_points']) ? (int) $_POST['max_points'] : null,
            'max_distance' => isset($_POST['max_distance']) ? (int) $_POST['max_distance'] : null,
            'normalization_curve' => isset($_POST['normalization_curve'])
                ? sanitize_key(wp_unslash((string) $_POST['normalization_curve']))
                : 'linear',
        ];

        $this->configurator->configure($partId, $mode, $config, $tiebreakerMode);

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
            $tiebreakerMode = is_string($row->tiebreaker_mode) && $row->tiebreaker_mode !== ''
                ? $row->tiebreaker_mode
                : 'manual_referee';
            $config = json_decode((string) ($row->scoring_config ?? ''), true);
            if (!is_array($config)) {
                $config = [];
            }

            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="border:1px solid #dcdcde;padding:12px;margin-bottom:12px;">';
            echo '<input type="hidden" name="action" value="bso_survival_save_part_rule" />';
            echo '<input type="hidden" name="part_id" value="' . (int) $row->part_id . '" />';
            echo '<input type="hidden" name="event_id" value="' . (int) $eventId . '" />';
            wp_nonce_field(self::SAVE_NONCE_ACTION, self::SAVE_NONCE_FIELD);

            echo '<h3 style="margin-top:0;">' . esc_html((string) $row->part_name) . '</h3>';

            echo '<p><label><strong>' . esc_html__('Scoring mode', 'bso-survival') . '</strong><br />';
            echo '<select name="scoring_mode">';
            foreach ($methods as $id => $method) {
                $selected = selected($mode, $id, false);
                echo '<option value="' . esc_attr($id) . '" ' . $selected . '>' . esc_html($method->getName()) . '</option>';
            }
            echo '</select></label></p>';

            echo '<p><label><strong>' . esc_html__('Tiebreaker', 'bso-survival') . '</strong><br />';
            echo '<select name="tiebreaker_mode">';
            echo '<option value="manual_referee" ' . selected($tiebreakerMode, 'manual_referee', false) . '>manual_referee</option>';
            echo '<option value="lower_raw_wins" ' . selected($tiebreakerMode, 'lower_raw_wins', false) . '>lower_raw_wins</option>';
            echo '<option value="higher_raw_wins" ' . selected($tiebreakerMode, 'higher_raw_wins', false) . '>higher_raw_wins</option>';
            echo '</select></label></p>';

            echo '<div class="bso-config-field" data-mode="time" style="display:' . ($mode === 'time' ? 'block' : 'none') . ';">';
            echo '<p><label>max_time<br /><input type="number" min="1" name="max_time" value="' . esc_attr((string) ($config['max_time'] ?? 1200)) . '" /></label></p>';
            echo '</div>';

            echo '<div class="bso-config-field" data-mode="points" style="display:' . ($mode === 'points' ? 'block' : 'none') . ';">';
            echo '<p><label>max_points<br /><input type="number" min="1" name="max_points" value="' . esc_attr((string) ($config['max_points'] ?? 100)) . '" /></label></p>';
            echo '</div>';

            echo '<div class="bso-config-field" data-mode="distance" style="display:' . ($mode === 'distance' ? 'block' : 'none') . ';">';
            echo '<p><label>max_distance<br /><input type="number" min="1" name="max_distance" value="' . esc_attr((string) ($config['max_distance'] ?? 500)) . '" /></label></p>';
            echo '</div>';

            echo '<p><label>normalization_curve<br />';
            echo '<select name="normalization_curve">';
            echo '<option value="linear" ' . selected((string) ($config['normalization_curve'] ?? 'linear'), 'linear', false) . '>linear</option>';
            echo '</select></label></p>';

            echo '<button class="button button-primary">' . esc_html__('Opslaan', 'bso-survival') . '</button>';
            echo '</form>';
        }

        echo '<script>';
        echo 'document.querySelectorAll("form[action*=admin-post.php]").forEach(function(form){';
        echo 'var modeSelect=form.querySelector("select[name=scoring_mode]");';
        echo 'if(!modeSelect){return;}';
        echo 'var sync=function(){var mode=modeSelect.value;form.querySelectorAll(".bso-config-field").forEach(function(el){el.style.display=(el.getAttribute("data-mode")===mode)?"block":"none";});};';
        echo 'modeSelect.addEventListener("change",sync);sync();';
        echo '});';
        echo '</script>';

        echo '</div>';
    }
}

<?php

namespace BSO\Survival\Admin;

use BSO\Survival\Support\Capabilities;

class AccessAdminPage {
    private const SAVE_NONCE_ACTION = 'bso_survival_save_access_overrides';
    private const SAVE_NONCE_FIELD = 'bso_survival_access_nonce';
    private const META_OVERRIDE_KEY = 'bso_survival_access_override';

    /** @var array<string, string> */
    private const OVERRIDES = [
        'inherit' => 'Overnemen van WordPress rol',
        'owner' => 'Survival eigenaar',
        'coordinator' => 'Survival coordinator',
        'scorer' => 'Alleen scorebeheer',
        'messenger' => 'Alleen meldingen',
        'none' => 'Geen Survival toegang',
    ];

    public function registerMenu(): void {
        if (!function_exists('add_submenu_page')) {
            return;
        }

        add_submenu_page(
            'bso-survival-rules',
            __('Toegang en rollen', 'bso-survival'),
            __('Toegang', 'bso-survival'),
            Capabilities::MANAGE_ACCESS,
            'bso-survival-access',
            [$this, 'renderPage']
        );
    }

    public function renderPage(): void {
        if (!Capabilities::canManageAccess()) {
            wp_die(esc_html__('Je hebt geen rechten om deze pagina te bekijken.', 'bso-survival'));
        }

        $users = get_users([
            'number' => 500,
            'orderby' => 'display_name',
            'order' => 'ASC',
        ]);

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Toegang en rollen', 'bso-survival') . '</h1>';
        echo '<p>' . esc_html__('Koppel bestaande WordPress gebruikers aan Survival rechten zonder hun standaard WordPress rol aan te passen.', 'bso-survival') . '</p>';

        if (isset($_GET['saved'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Toegangsinstellingen opgeslagen.', 'bso-survival') . '</p></div>';
        }

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="bso_survival_save_access_overrides" />';
        wp_nonce_field(self::SAVE_NONCE_ACTION, self::SAVE_NONCE_FIELD);

        echo '<table class="widefat striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Gebruiker', 'bso-survival') . '</th>';
        echo '<th>' . esc_html__('WordPress rollen', 'bso-survival') . '</th>';
        echo '<th>' . esc_html__('Effectieve Survival toegang', 'bso-survival') . '</th>';
        echo '<th>' . esc_html__('Override', 'bso-survival') . '</th>';
        echo '</tr></thead><tbody>';

        if ($users === []) {
            echo '<tr><td colspan="4">' . esc_html__('Geen gebruikers gevonden.', 'bso-survival') . '</td></tr>';
        } else {
            foreach ($users as $user) {
                $override = $this->getUserOverride((int) $user->ID);
                $roles = array_map('translate_user_role', (array) $user->roles);

                echo '<tr>';
                echo '<td><strong>' . esc_html((string) $user->display_name) . '</strong><br /><code>' . esc_html((string) $user->user_email) . '</code></td>';
                echo '<td>' . esc_html($roles !== [] ? implode(', ', $roles) : '-') . '</td>';
                echo '<td>' . esc_html($this->formatEffectiveAccess($user)) . '</td>';
                echo '<td>';
                echo '<select name="survival_override[' . esc_attr((string) $user->ID) . ']">';
                foreach (self::OVERRIDES as $value => $label) {
                    echo '<option value="' . esc_attr($value) . '"' . selected($override, $value, false) . '>' . esc_html($label) . '</option>';
                }
                echo '</select>';
                echo '</td>';
                echo '</tr>';
            }
        }

        echo '</tbody></table>';
        submit_button(__('Toegang opslaan', 'bso-survival'));
        echo '</form>';
        echo '</div>';
    }

    public function handleSave(): void {
        if (!Capabilities::canManageAccess()) {
            wp_die(esc_html__('Je hebt geen rechten om toegang te wijzigen.', 'bso-survival'));
        }

        check_admin_referer(self::SAVE_NONCE_ACTION, self::SAVE_NONCE_FIELD);

        $overrides = isset($_POST['survival_override']) && is_array($_POST['survival_override'])
            ? wp_unslash($_POST['survival_override'])
            : [];

        foreach ($overrides as $userIdRaw => $overrideRaw) {
            $userId = (int) $userIdRaw;
            if ($userId <= 0 || !get_user_by('id', $userId)) {
                continue;
            }

            $override = sanitize_key((string) $overrideRaw);
            if (!isset(self::OVERRIDES[$override])) {
                $override = 'inherit';
            }

            $this->applyUserOverride($userId, $override);
        }

        wp_safe_redirect(admin_url('admin.php?page=bso-survival-access&saved=1'));
        exit;
    }

    private function applyUserOverride(int $userId, string $override): void {
        $user = new \WP_User($userId);
        if (!$user->exists()) {
            return;
        }

        foreach (Capabilities::allSurvivalCapabilities() as $capability) {
            $user->remove_cap($capability);
        }

        if ($override === 'inherit') {
            delete_user_meta($userId, self::META_OVERRIDE_KEY);
            return;
        }

        update_user_meta($userId, self::META_OVERRIDE_KEY, $override);

        if ($override === 'owner') {
            $this->addCapabilities($user, [
                Capabilities::MANAGE_SETTINGS,
                Capabilities::MANAGE_ACCESS,
                Capabilities::MANAGE_SCORES,
                Capabilities::MANAGE_MESSAGES,
            ]);
            return;
        }

        if ($override === 'coordinator') {
            $this->addCapabilities($user, [
                Capabilities::MANAGE_SETTINGS,
                Capabilities::MANAGE_SCORES,
                Capabilities::MANAGE_MESSAGES,
            ]);
            return;
        }

        if ($override === 'scorer') {
            $this->addCapabilities($user, [
                Capabilities::MANAGE_SCORES,
            ]);
            return;
        }

        if ($override === 'messenger') {
            $this->addCapabilities($user, [
                Capabilities::MANAGE_MESSAGES,
            ]);
        }
    }

    /**
     * @param array<int, string> $capabilities
     */
    private function addCapabilities(\WP_User $user, array $capabilities): void {
        foreach ($capabilities as $capability) {
            $user->add_cap($capability);
        }
    }

    private function getUserOverride(int $userId): string {
        $stored = get_user_meta($userId, self::META_OVERRIDE_KEY, true);
        $value = is_string($stored) ? sanitize_key($stored) : 'inherit';

        return isset(self::OVERRIDES[$value]) ? $value : 'inherit';
    }

    private function formatEffectiveAccess(\WP_User $user): string {
        $canAccess = user_can($user, Capabilities::MANAGE_ACCESS);
        $canSettings = user_can($user, Capabilities::MANAGE_SETTINGS);
        $canScores = user_can($user, Capabilities::MANAGE_SCORES);
        $canMessages = user_can($user, Capabilities::MANAGE_MESSAGES);

        if ($canAccess && $canSettings && $canScores && $canMessages) {
            return __('Eigenaar (volledig beheer)', 'bso-survival');
        }

        if ($canSettings && $canScores && $canMessages) {
            return __('Coordinator (beheer + score + meldingen)', 'bso-survival');
        }

        if ($canScores && !$canSettings && !$canMessages) {
            return __('Scorebeheer', 'bso-survival');
        }

        if ($canMessages && !$canSettings && !$canScores) {
            return __('Meldingenbeheer', 'bso-survival');
        }

        if ($canSettings || $canScores || $canMessages || $canAccess) {
            return __('Aangepaste rechten', 'bso-survival');
        }

        return __('Geen toegang', 'bso-survival');
    }
}

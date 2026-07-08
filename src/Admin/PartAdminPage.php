<?php

namespace BSO\Survival\Admin;

use BSO\Survival\Service\PartAdminService;

class PartAdminPage {
    private const SAVE_NONCE_ACTION = 'bso_survival_part_save';
    private const SAVE_NONCE_FIELD = 'bso_survival_part_save_nonce';
    private const DELETE_NONCE_ACTION = 'bso_survival_part_delete';
    private const DELETE_NONCE_FIELD = 'bso_survival_part_delete_nonce';
    private const IMPORT_NONCE_ACTION = 'bso_survival_part_import';
    private const IMPORT_NONCE_FIELD = 'bso_survival_part_import_nonce';
    private const EXPORT_NONCE_ACTION = 'bso_survival_part_export';
    private const EXPORT_NONCE_FIELD = 'bso_survival_part_export_nonce';

    /** @var PartAdminService */
    private $parts;

    public function __construct(PartAdminService $parts) {
        $this->parts = $parts;
    }

    public function registerMenu(): void {
        if (!function_exists('add_submenu_page')) {
            return;
        }

        add_submenu_page(
            'bso-survival-rules',
            __('Onderdelen', 'bso-survival'),
            __('Onderdelen', 'bso-survival'),
            'manage_options',
            'bso-survival-parts',
            [$this, 'renderPage']
        );
    }

    public function handleSave(): void {
        $this->assertAdminPermissions();
        if (!isset($_POST[self::SAVE_NONCE_FIELD]) || !wp_verify_nonce((string) $_POST[self::SAVE_NONCE_FIELD], self::SAVE_NONCE_ACTION)) {
            wp_die(__('Ongeldige aanvraag (nonce).', 'bso-survival'));
        }

        $partId = isset($_POST['part_id']) ? (int) $_POST['part_id'] : 0;
        $payload = [
            'name' => isset($_POST['name']) ? sanitize_text_field(wp_unslash((string) $_POST['name'])) : '',
            'status' => isset($_POST['status']) ? sanitize_text_field(wp_unslash((string) $_POST['status'])) : 'actief',
            'latitude' => isset($_POST['latitude']) ? sanitize_text_field(wp_unslash((string) $_POST['latitude'])) : '',
            'longitude' => isset($_POST['longitude']) ? sanitize_text_field(wp_unslash((string) $_POST['longitude'])) : '',
            'meta_data' => isset($_POST['meta_data']) ? wp_unslash((string) $_POST['meta_data']) : '',
        ];

        try {
            if ($partId > 0) {
                $part = $this->parts->updatePart($partId, $payload);
                $this->redirectWithStatus('updated', (int) ($part->id ?? $partId));
            }

            $part = $this->parts->createPart($payload);
            $this->redirectWithStatus('created', (int) ($part->id ?? 0));
        } catch (\Throwable $exception) {
            $this->redirectWithStatus('error', $partId, $exception->getMessage());
        }
    }

    public function handleDelete(): void {
        $this->assertAdminPermissions();
        if (!isset($_POST[self::DELETE_NONCE_FIELD]) || !wp_verify_nonce((string) $_POST[self::DELETE_NONCE_FIELD], self::DELETE_NONCE_ACTION)) {
            wp_die(__('Ongeldige aanvraag (nonce).', 'bso-survival'));
        }

        $partId = isset($_POST['part_id']) ? (int) $_POST['part_id'] : 0;
        try {
            $this->parts->deletePart($partId);
            $this->redirectWithStatus('deleted');
        } catch (\Throwable $exception) {
            $this->redirectWithStatus('error', $partId, $exception->getMessage());
        }
    }

    public function handleImport(): void {
        $this->assertAdminPermissions();
        if (!isset($_POST[self::IMPORT_NONCE_FIELD]) || !wp_verify_nonce((string) $_POST[self::IMPORT_NONCE_FIELD], self::IMPORT_NONCE_ACTION)) {
            wp_die(__('Ongeldige aanvraag (nonce).', 'bso-survival'));
        }

        try {
            $json = '';
            if (isset($_FILES['parts_import_file']['tmp_name']) && is_string($_FILES['parts_import_file']['tmp_name']) && $_FILES['parts_import_file']['tmp_name'] !== '') {
                $json = (string) file_get_contents($_FILES['parts_import_file']['tmp_name']);
            }
            if ($json === '' && isset($_POST['parts_import_json'])) {
                $json = trim((string) wp_unslash($_POST['parts_import_json']));
            }
            if ($json === '') {
                throw new \InvalidArgumentException('Kies een JSON-bestand of plak JSON in het importveld.');
            }

            $created = $this->parts->importParts($json);
            $this->redirectWithStatus('imported', 0, sprintf('%d onderdelen geimporteerd.', count($created)));
        } catch (\Throwable $exception) {
            $this->redirectWithStatus('error', 0, $exception->getMessage());
        }
    }

    public function handleExport(): void {
        $this->assertAdminPermissions();
        if (!isset($_POST[self::EXPORT_NONCE_FIELD]) || !wp_verify_nonce((string) $_POST[self::EXPORT_NONCE_FIELD], self::EXPORT_NONCE_ACTION)) {
            wp_die(__('Ongeldige aanvraag (nonce).', 'bso-survival'));
        }

        $json = $this->parts->exportParts();
        $filename = sprintf('bso-survival-parts-%s.json', gmdate('Ymd-His'));

        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $json;
        exit;
    }

    public function renderPage(): void {
        $this->assertAdminPermissions();
        $parts = $this->parts->listParts();
        $selectedPartId = isset($_GET['part_id']) ? (int) $_GET['part_id'] : 0;
        $selectedPart = $selectedPartId > 0 ? $this->parts->getPart($selectedPartId) : null;

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Survival onderdelen', 'bso-survival') . '</h1>';

        $this->renderNotice();

        echo '<div style="display:grid;grid-template-columns:minmax(320px,420px) minmax(420px,1fr);gap:24px;align-items:start;">';
        echo '<div>';
        echo '<h2>' . esc_html($selectedPart !== null ? __('Onderdeel bewerken', 'bso-survival') : __('Nieuw onderdeel', 'bso-survival')) . '</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="bso_survival_part_save" />';
        echo '<input type="hidden" name="part_id" value="' . (int) ($selectedPart->id ?? 0) . '" />';
        wp_nonce_field(self::SAVE_NONCE_ACTION, self::SAVE_NONCE_FIELD);

        echo '<table class="form-table" role="presentation"><tbody>';
        echo '<tr><th scope="row"><label for="bso-part-name">' . esc_html__('Naam', 'bso-survival') . '</label></th>';
        echo '<td><input id="bso-part-name" name="name" type="text" class="regular-text" required="required" value="' . esc_attr((string) ($selectedPart->name ?? '')) . '" /></td></tr>';

        $currentStatus = (string) ($selectedPart->status ?? 'actief');
        echo '<tr><th scope="row"><label for="bso-part-status">' . esc_html__('Status', 'bso-survival') . '</label></th>';
        echo '<td><select id="bso-part-status" name="status">';
        echo '<option value="actief"' . selected($currentStatus, 'actief', false) . '>' . esc_html__('Actief', 'bso-survival') . '</option>';
        echo '<option value="inactief"' . selected($currentStatus, 'inactief', false) . '>' . esc_html__('Inactief', 'bso-survival') . '</option>';
        echo '</select></td></tr>';

        echo '<tr><th scope="row"><label for="bso-part-latitude">' . esc_html__('Latitude', 'bso-survival') . '</label></th>';
        echo '<td><input id="bso-part-latitude" name="latitude" type="text" class="regular-text" value="' . esc_attr((string) ($selectedPart->latitude ?? '')) . '" /></td></tr>';

        echo '<tr><th scope="row"><label for="bso-part-longitude">' . esc_html__('Longitude', 'bso-survival') . '</label></th>';
        echo '<td><input id="bso-part-longitude" name="longitude" type="text" class="regular-text" value="' . esc_attr((string) ($selectedPart->longitude ?? '')) . '" /></td></tr>';

        echo '<tr><th scope="row"><label for="bso-part-meta">' . esc_html__('Meta JSON', 'bso-survival') . '</label></th>';
        echo '<td><textarea id="bso-part-meta" name="meta_data" rows="8" class="large-text code">' . esc_textarea((string) ($selectedPart->meta_data ?? '')) . '</textarea>';
        echo '<p class="description">' . esc_html__('Gebruik geldig JSON voor uitbreidbare onderdeelmetadata.', 'bso-survival') . '</p></td></tr>';
        echo '</tbody></table>';

        echo '<p><button class="button button-primary">' . esc_html($selectedPart !== null ? __('Onderdeel opslaan', 'bso-survival') : __('Onderdeel aanmaken', 'bso-survival')) . '</button>';
        if ($selectedPart !== null) {
            echo ' <a class="button" href="' . esc_url(admin_url('admin.php?page=bso-survival-parts')) . '">' . esc_html__('Nieuw formulier', 'bso-survival') . '</a>';
        }
        echo '</p>';
        echo '</form>';

        echo '<hr />';
        echo '<h2>' . esc_html__('Import / export', 'bso-survival') . '</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" enctype="multipart/form-data">';
        echo '<input type="hidden" name="action" value="bso_survival_part_import" />';
        wp_nonce_field(self::IMPORT_NONCE_ACTION, self::IMPORT_NONCE_FIELD);
        echo '<p><input type="file" name="parts_import_file" accept="application/json,.json" /></p>';
        echo '<p><textarea name="parts_import_json" rows="8" class="large-text code" placeholder="[{\"name\":\"Kanovaren\",\"status\":\"actief\"}]"></textarea></p>';
        echo '<p><button class="button button-secondary">' . esc_html__('Onderdelen importeren', 'bso-survival') . '</button></p>';
        echo '</form>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="bso_survival_part_export" />';
        wp_nonce_field(self::EXPORT_NONCE_ACTION, self::EXPORT_NONCE_FIELD);
        echo '<p><button class="button">' . esc_html__('Exporteer onderdelen als JSON', 'bso-survival') . '</button></p>';
        echo '</form>';
        echo '</div>';

        echo '<div>';
        echo '<h2>' . esc_html__('Beschikbare onderdelen', 'bso-survival') . '</h2>';
        echo '<table class="widefat striped"><thead><tr>';
        echo '<th>' . esc_html__('ID', 'bso-survival') . '</th>';
        echo '<th>' . esc_html__('Naam', 'bso-survival') . '</th>';
        echo '<th>' . esc_html__('Status', 'bso-survival') . '</th>';
        echo '<th>' . esc_html__('Gekoppeld event', 'bso-survival') . '</th>';
        echo '<th>' . esc_html__('Acties', 'bso-survival') . '</th>';
        echo '</tr></thead><tbody>';

        if ($parts === []) {
            echo '<tr><td colspan="5">' . esc_html__('Nog geen onderdelen beschikbaar.', 'bso-survival') . '</td></tr>';
        }

        foreach ($parts as $part) {
            $partId = (int) ($part->id ?? 0);
            $editUrl = add_query_arg([
                'page' => 'bso-survival-parts',
                'part_id' => $partId,
            ], admin_url('admin.php'));
            $eventLabel = isset($part->event_id) && (int) $part->event_id > 0 ? '#' . (int) $part->event_id : __('niet gekoppeld', 'bso-survival');

            echo '<tr>';
            echo '<td>' . $partId . '</td>';
            echo '<td>' . esc_html((string) ($part->name ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($part->status ?? '')) . '</td>';
            echo '<td>' . esc_html((string) $eventLabel) . '</td>';
            echo '<td>';
            echo '<a class="button button-small" href="' . esc_url($editUrl) . '">' . esc_html__('Bewerken', 'bso-survival') . '</a> ';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline;" onsubmit="return confirm(\'' . esc_js(__('Weet je zeker dat je dit onderdeel wilt verwijderen of deactiveren?', 'bso-survival')) . '\');">';
            echo '<input type="hidden" name="action" value="bso_survival_part_delete" />';
            echo '<input type="hidden" name="part_id" value="' . $partId . '" />';
            wp_nonce_field(self::DELETE_NONCE_ACTION, self::DELETE_NONCE_FIELD);
            echo '<button class="button button-small button-link-delete">' . esc_html__('Verwijderen', 'bso-survival') . '</button>';
            echo '</form>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    private function renderNotice(): void {
        if (!isset($_GET['saved'])) {
            return;
        }

        $saved = sanitize_text_field(wp_unslash((string) $_GET['saved']));
        $message = isset($_GET['message']) ? sanitize_text_field(wp_unslash((string) $_GET['message'])) : '';

        if ($saved === 'created') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Onderdeel aangemaakt.', 'bso-survival') . '</p></div>';
            return;
        }
        if ($saved === 'updated') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Onderdeel bijgewerkt.', 'bso-survival') . '</p></div>';
            return;
        }
        if ($saved === 'deleted') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Onderdeel verwijderd uit de actieve set.', 'bso-survival') . '</p></div>';
            return;
        }
        if ($saved === 'imported') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message !== '' ? $message : __('Onderdelen geimporteerd.', 'bso-survival')) . '</p></div>';
            return;
        }
        if ($saved === 'error') {
            echo '<div class="notice notice-error"><p>' . esc_html($message !== '' ? $message : __('Onbekende fout.', 'bso-survival')) . '</p></div>';
        }
    }

    private function assertAdminPermissions(): void {
        if (!function_exists('current_user_can') || !current_user_can('manage_options')) {
            wp_die(__('Onvoldoende rechten.', 'bso-survival'));
        }
    }

    private function redirectWithStatus(string $saved, int $partId = 0, string $message = ''): void {
        $args = [
            'page' => 'bso-survival-parts',
            'saved' => $saved,
        ];
        if ($partId > 0) {
            $args['part_id'] = $partId;
        }
        if ($message !== '') {
            $args['message'] = $message;
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }
}

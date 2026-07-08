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
            $this->redirectWithStatus('error', $partId, $exception->getMessage(), 'edit');
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
            $this->redirectWithStatus('error', $partId, $exception->getMessage(), 'edit');
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
            $this->redirectWithStatus('error', 0, $exception->getMessage(), 'import');
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

        $panel = isset($_GET['panel']) ? sanitize_key((string) wp_unslash($_GET['panel'])) : '';
        if (!in_array($panel, ['import', 'edit'], true)) {
            $panel = '';
        }

        $search = isset($_GET['part_search']) ? sanitize_text_field(wp_unslash((string) $_GET['part_search'])) : '';
        $sortBy = isset($_GET['sort_by']) ? sanitize_key((string) wp_unslash($_GET['sort_by'])) : 'name';
        $sortDirection = isset($_GET['sort_direction']) ? sanitize_key((string) wp_unslash($_GET['sort_direction'])) : 'asc';
        $parts = $this->parts->listPartsFilteredSorted($search, $sortBy, $sortDirection);
        $selectedPartId = isset($_GET['part_id']) ? (int) $_GET['part_id'] : 0;
        $selectedPart = $selectedPartId > 0 ? $this->parts->getPart($selectedPartId) : null;

        $newPartUrl = $this->buildAdminUrl([
            'panel' => 'edit',
            'part_search' => $search,
            'sort_by' => $sortBy,
            'sort_direction' => $sortDirection,
        ]);
        $importPanelUrl = $this->buildAdminUrl([
            'panel' => 'import',
            'part_search' => $search,
            'sort_by' => $sortBy,
            'sort_direction' => $sortDirection,
        ]);
        $closePanelUrl = $this->buildAdminUrl([
            'part_search' => $search,
            'sort_by' => $sortBy,
            'sort_direction' => $sortDirection,
        ]);

        echo '<div class="wrap">';
        echo '<style>
            .bso-parts-layout{position:relative;}
            .bso-parts-main{max-width:100%;transition:margin-right .2s ease;}
            .bso-parts-main.with-panel{margin-right:380px;}
            .bso-parts-toolbar{display:flex;justify-content:space-between;align-items:center;gap:10px;margin:10px 0 14px 0;}
            .bso-parts-toolbar-actions{display:flex;gap:8px;flex-wrap:wrap;}
            .bso-parts-panel{position:fixed;top:32px;right:0;width:360px;height:calc(100vh - 32px);background:#fff;border-left:1px solid #dcdcde;z-index:999;padding:14px 16px 16px 16px;overflow:auto;box-shadow:-6px 0 20px rgba(0,0,0,.08);}
            .bso-parts-panel-top{display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;}
            .bso-parts-panel-title{font-size:20px;font-weight:600;margin:0;}
            .bso-parts-panel-actions{display:flex;gap:8px;}
            .bso-parts-search{margin:0 0 12px 0;display:flex;gap:8px;align-items:center;flex-wrap:wrap;}
            @media (max-width: 1200px){
                .bso-parts-main.with-panel{margin-right:0;}
                .bso-parts-panel{position:static;width:auto;height:auto;box-shadow:none;border:1px solid #dcdcde;margin-top:14px;}
            }
        </style>';
        echo '<h1>' . esc_html__('Survival onderdelen', 'bso-survival') . '</h1>';

        $this->renderNotice();

        echo '<div class="bso-parts-layout">';
        echo '<div class="bso-parts-main' . ($panel !== '' ? ' with-panel' : '') . '">';
        echo '<div class="bso-parts-toolbar">';
        echo '<h2 style="margin:0;">' . esc_html__('Beschikbare onderdelen', 'bso-survival') . '</h2>';
        echo '<div class="bso-parts-toolbar-actions">';
        echo '<a class="button button-secondary" href="' . esc_url($newPartUrl) . '">' . esc_html__('Nieuw onderdeel', 'bso-survival') . '</a>';
        echo '<a class="button" href="' . esc_url($importPanelUrl) . '">' . esc_html__('Import / export', 'bso-survival') . '</a>';
        echo '</div>';
        echo '</div>';

        echo '<form method="get" action="' . esc_url(admin_url('admin.php')) . '" class="bso-parts-search">';
        echo '<input type="hidden" name="page" value="bso-survival-parts" />';
        echo '<input type="hidden" name="sort_by" value="' . esc_attr($sortBy) . '" />';
        echo '<input type="hidden" name="sort_direction" value="' . esc_attr($sortDirection) . '" />';
        if ($panel !== '') {
            echo '<input type="hidden" name="panel" value="' . esc_attr($panel) . '" />';
        }
        if ($selectedPartId > 0) {
            echo '<input type="hidden" name="part_id" value="' . (int) $selectedPartId . '" />';
        }
        echo '<label for="bso-part-search"><strong>' . esc_html__('Zoeken', 'bso-survival') . ':</strong></label>';
        echo '<input id="bso-part-search" type="search" name="part_search" class="regular-text" value="' . esc_attr($search) . '" placeholder="' . esc_attr__('Zoek op ID, naam, status of event', 'bso-survival') . '" />';
        echo '<button class="button">' . esc_html__('Zoek', 'bso-survival') . '</button>';
        echo '<a class="button button-link" href="' . esc_url($closePanelUrl) . '">' . esc_html__('Reset', 'bso-survival') . '</a>';
        echo '</form>';

        echo '<table class="widefat striped"><thead><tr>';
        echo '<th>' . $this->renderSortLink('id', __('ID', 'bso-survival'), $sortBy, $sortDirection, $selectedPartId, $search, $panel) . '</th>';
        echo '<th>' . $this->renderSortLink('name', __('Naam', 'bso-survival'), $sortBy, $sortDirection, $selectedPartId, $search, $panel) . '</th>';
        echo '<th>' . $this->renderSortLink('status', __('Status', 'bso-survival'), $sortBy, $sortDirection, $selectedPartId, $search, $panel) . '</th>';
        echo '<th>' . $this->renderSortLink('event_id', __('Gekoppeld event', 'bso-survival'), $sortBy, $sortDirection, $selectedPartId, $search, $panel) . '</th>';
        echo '<th>' . esc_html__('Acties', 'bso-survival') . '</th>';
        echo '</tr></thead><tbody>';

        if ($parts === []) {
            echo '<tr><td colspan="5">' . esc_html__('Nog geen onderdelen beschikbaar.', 'bso-survival') . '</td></tr>';
        }

        foreach ($parts as $part) {
            $partId = (int) ($part->id ?? 0);
            $editUrl = $this->buildAdminUrl([
                'part_id' => $partId,
                'part_search' => $search,
                'sort_by' => $sortBy,
                'sort_direction' => $sortDirection,
                'panel' => 'edit',
            ]);
            $eventId = isset($part->event_id) ? (int) $part->event_id : 0;
            $eventLabel = $eventId > 0 ? ('#' . $eventId) : __('niet gekoppeld', 'bso-survival');
            $eventUrl = $eventId > 0 ? add_query_arg([
                'page' => 'bso-survival-events',
                'event_id' => $eventId,
            ], admin_url('admin.php')) : '';

            echo '<tr>';
            echo '<td>' . $partId . '</td>';
            echo '<td>' . esc_html((string) ($part->name ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($part->status ?? '')) . '</td>';
            echo '<td>';
            if ($eventUrl !== '') {
                echo '<a href="' . esc_url($eventUrl) . '">' . esc_html((string) $eventLabel) . '</a>';
            } else {
                echo esc_html((string) $eventLabel);
            }
            echo '</td>';
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

        if ($panel !== '') {
            $this->renderSidePanel($panel, $selectedPart, $selectedPartId, $search, $sortBy, $sortDirection);
        }

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

    private function redirectWithStatus(string $saved, int $partId = 0, string $message = '', string $panel = ''): void {
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
        if (in_array($panel, ['import', 'edit'], true)) {
            $args['panel'] = $panel;
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    private function renderSortLink(string $column, string $label, string $currentColumn, string $currentDirection, int $selectedPartId, string $search, string $panel): string {
        $isActive = $column === $currentColumn;
        $nextDirection = $isActive && $currentDirection === 'asc' ? 'desc' : 'asc';
        $indicator = '';

        if ($isActive) {
            $indicator = $currentDirection === 'asc' ? ' ↑' : ' ↓';
        }

        $args = [
            'sort_by' => $column,
            'sort_direction' => $nextDirection,
        ];

        if ($search !== '') {
            $args['part_search'] = $search;
        }
        if ($panel !== '') {
            $args['panel'] = $panel;
        }

        if ($selectedPartId > 0) {
            $args['part_id'] = $selectedPartId;
        }

        $url = $this->buildAdminUrl($args);

        return '<a href="' . esc_url($url) . '">' . esc_html($label . $indicator) . '</a>';
    }

    /**
     * @param array<string, mixed> $args
     */
    private function buildAdminUrl(array $args, int $selectedPartId = 0): string {
        $baseArgs = ['page' => 'bso-survival-parts'];
        if ($selectedPartId > 0 && !isset($args['part_id'])) {
            $baseArgs['part_id'] = $selectedPartId;
        }

        return add_query_arg(array_merge($baseArgs, $args), admin_url('admin.php'));
    }

    /** @param object|null $selectedPart */
    private function renderSidePanel(string $panel, $selectedPart, int $selectedPartId, string $search, string $sortBy, string $sortDirection): void {
        $closePanelUrl = $this->buildAdminUrl([
            'part_search' => $search,
            'sort_by' => $sortBy,
            'sort_direction' => $sortDirection,
        ]);

        echo '<aside class="bso-parts-panel">';
        echo '<div class="bso-parts-panel-top">';
        echo '<p class="bso-parts-panel-title">' . esc_html($panel === 'import' ? __('Import', 'bso-survival') : __('Bewerk onderdeel', 'bso-survival')) . '</p>';
        echo '<div class="bso-parts-panel-actions">';
        echo '<a class="button button-link" href="' . esc_url($closePanelUrl) . '">' . esc_html__('Annuleren', 'bso-survival') . '</a>';
        echo '</div>';
        echo '</div>';

        if ($panel === 'import') {
            $this->renderImportPanel($closePanelUrl);
        } else {
            $this->renderEditPanel($selectedPart, $selectedPartId, $closePanelUrl);
        }

        echo '</aside>';
    }

    /** @param object|null $selectedPart */
    private function renderEditPanel($selectedPart, int $selectedPartId, string $closePanelUrl): void {
        $part = is_object($selectedPart) ? $selectedPart : (object) [];
        $currentStatus = (string) ($part->status ?? 'actief');

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="bso_survival_part_save" />';
        echo '<input type="hidden" name="part_id" value="' . (int) ($selectedPartId > 0 ? $selectedPartId : 0) . '" />';
        wp_nonce_field(self::SAVE_NONCE_ACTION, self::SAVE_NONCE_FIELD);

        echo '<table class="form-table" role="presentation"><tbody>';
        echo '<tr><th scope="row"><label for="bso-part-name">' . esc_html__('Naam', 'bso-survival') . '</label></th>';
        echo '<td><input id="bso-part-name" name="name" type="text" class="regular-text" required="required" value="' . esc_attr((string) ($part->name ?? '')) . '" /></td></tr>';

        echo '<tr><th scope="row"><label for="bso-part-status">' . esc_html__('Status', 'bso-survival') . '</label></th>';
        echo '<td><select id="bso-part-status" name="status">';
        echo '<option value="actief"' . selected($currentStatus, 'actief', false) . '>' . esc_html__('Actief', 'bso-survival') . '</option>';
        echo '<option value="inactief"' . selected($currentStatus, 'inactief', false) . '>' . esc_html__('Inactief', 'bso-survival') . '</option>';
        echo '</select></td></tr>';

        echo '<tr><th scope="row"><label for="bso-part-latitude">' . esc_html__('Latitude', 'bso-survival') . '</label></th>';
        echo '<td><input id="bso-part-latitude" name="latitude" type="text" class="regular-text" value="' . esc_attr((string) ($part->latitude ?? '')) . '" /></td></tr>';

        echo '<tr><th scope="row"><label for="bso-part-longitude">' . esc_html__('Longitude', 'bso-survival') . '</label></th>';
        echo '<td><input id="bso-part-longitude" name="longitude" type="text" class="regular-text" value="' . esc_attr((string) ($part->longitude ?? '')) . '" /></td></tr>';

        echo '<tr><th scope="row"><label for="bso-part-meta">' . esc_html__('Meta JSON', 'bso-survival') . '</label></th>';
        echo '<td><textarea id="bso-part-meta" name="meta_data" rows="8" class="large-text code">' . esc_textarea((string) ($part->meta_data ?? '')) . '</textarea>';
        echo '<p class="description">' . esc_html__('Gebruik geldig JSON voor uitbreidbare onderdeelmetadata.', 'bso-survival') . '</p></td></tr>';
        echo '</tbody></table>';

        echo '<p>';
        echo '<button class="button button-primary">' . esc_html($selectedPartId > 0 ? __('Onderdeel opslaan', 'bso-survival') : __('Onderdeel aanmaken', 'bso-survival')) . '</button> ';
        echo '<a class="button" href="' . esc_url($closePanelUrl) . '">' . esc_html__('Annuleren', 'bso-survival') . '</a>';
        echo '</p>';
        echo '</form>';
    }

    private function renderImportPanel(string $closePanelUrl): void {
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" enctype="multipart/form-data">';
        echo '<input type="hidden" name="action" value="bso_survival_part_import" />';
        wp_nonce_field(self::IMPORT_NONCE_ACTION, self::IMPORT_NONCE_FIELD);
        echo '<p><label><strong>' . esc_html__('Bestand kiezen', 'bso-survival') . '</strong></label><br /><input type="file" name="parts_import_file" accept="application/json,.json" /></p>';
        echo '<p><textarea name="parts_import_json" rows="10" class="large-text code" placeholder="[{\"name\":\"Kanovaren\",\"status\":\"actief\"}]"></textarea></p>';
        echo '<p><button class="button button-primary">' . esc_html__('Onderdelen importeren', 'bso-survival') . '</button></p>';
        echo '</form>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="bso_survival_part_export" />';
        wp_nonce_field(self::EXPORT_NONCE_ACTION, self::EXPORT_NONCE_FIELD);
        echo '<p><button class="button button-secondary">' . esc_html__('Exporteer onderdelen als JSON', 'bso-survival') . '</button></p>';
        echo '</form>';

        echo '<p><a class="button" href="' . esc_url($closePanelUrl) . '">' . esc_html__('Annuleren', 'bso-survival') . '</a></p>';
    }
}

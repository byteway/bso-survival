<?php

namespace BSO\Survival\Admin;

use BSO\Survival\Service\EmailTemplateService;
use BSO\Survival\Service\EmailOutboxService;
use BSO\Survival\Service\RegistrationConfirmationService;
use BSO\Survival\Support\Capabilities;
use InvalidArgumentException;

class EmailTemplateAdminPage {
    private const SAVE_NONCE_ACTION = 'bso_survival_save_email_template';
    private const SAVE_NONCE_FIELD = 'bso_survival_save_email_template_nonce';

    /** @var EmailTemplateService */
    private $templates;

    /** @var EmailOutboxService|null */
    private $outbox;

    public function __construct(EmailTemplateService $templates, EmailOutboxService $outbox = null) {
        $this->templates = $templates;
        $this->outbox = $outbox;
    }

    public function registerMenu(): void {
        if (!function_exists('add_submenu_page')) {
            return;
        }

        add_submenu_page(
            'bso-survival-rules',
            __('Email Templates', 'bso-survival'),
            __('Email Templates', 'bso-survival'),
            Capabilities::MANAGE_SETTINGS,
            'bso-survival-email-templates',
            [$this, 'renderPage']
        );
    }

    public function handleSave(): void {
        if (!Capabilities::canManageSettings()) {
            wp_die(__('Onvoldoende rechten.', 'bso-survival'));
        }

        if (!isset($_POST[self::SAVE_NONCE_FIELD]) || !wp_verify_nonce((string) $_POST[self::SAVE_NONCE_FIELD], self::SAVE_NONCE_ACTION)) {
            wp_die(__('Ongeldige aanvraag (nonce).', 'bso-survival'));
        }

        $templateKey = isset($_POST['template_key']) ? sanitize_key((string) $_POST['template_key']) : '';
        $subject = isset($_POST['subject']) ? sanitize_text_field((string) wp_unslash($_POST['subject'])) : '';
        $htmlBody = isset($_POST['html_body']) ? (string) wp_unslash($_POST['html_body']) : '';
        $updatedBy = $this->resolveUpdatedBy();

        if ($templateKey === '') {
            wp_die(__('Template key ontbreekt.', 'bso-survival'));
        }

        if (function_exists('wp_kses_post')) {
            $htmlBody = wp_kses_post($htmlBody);
        }

        try {
            $saved = $this->templates->saveTemplate($templateKey, $subject, $htmlBody, $updatedBy);
            $error = '';
        } catch (InvalidArgumentException $exception) {
            $saved = false;
            $error = $exception->getMessage();
        }

        $redirect = add_query_arg(
            [
                'page' => 'bso-survival-email-templates',
                'saved' => $saved ? 1 : 0,
                'template_key' => $templateKey,
                'error' => $error,
            ],
            admin_url('admin.php')
        );

        wp_safe_redirect($redirect);
        exit;
    }

    public function renderPage(): void {
        if (!Capabilities::canManageSettings()) {
            wp_die(__('Onvoldoende rechten.', 'bso-survival'));
        }

        $templateKey = isset($_GET['template_key'])
            ? sanitize_key((string) $_GET['template_key'])
            : EmailTemplateService::TEMPLATE_PUBLICATION_RESULT;
        if (!in_array($templateKey, $this->allowedTemplateKeys(), true)) {
            $templateKey = EmailTemplateService::TEMPLATE_PUBLICATION_RESULT;
        }

        $template = $this->templates->getTemplate($templateKey);
        $previewContext = $templateKey === EmailTemplateService::TEMPLATE_REGISTRATION_CONFIRMATION
            ? RegistrationConfirmationService::sampleContext()
            : [
                'headline' => 'Uitslag gepubliceerd',
                'event_id' => '14',
                'published_at' => '2026-07-08T12:00:00+00:00',
                'top_3_html' => '<ol><li>#1 Team Rood</li><li>#2 Team Blauw</li><li>#3 Team Groen</li></ol>',
            ];
        $preview = $this->templates->render($templateKey, $previewContext);
        $placeholders = $this->templates->allowedPlaceholders($templateKey);

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('BSO Survival Email Templates', 'bso-survival') . '</h1>';
        echo '<p>' . esc_html__('Beheer templates voor publicatie en inschrijvingsbevestiging.', 'bso-survival') . '</p>';

        echo '<form method="get" action="' . esc_url(admin_url('admin.php')) . '" style="margin-bottom:12px;">';
        echo '<input type="hidden" name="page" value="bso-survival-email-templates" />';
        echo '<label for="bso-email-template-key"><strong>' . esc_html__('Template', 'bso-survival') . ':</strong></label> ';
        echo '<select id="bso-email-template-key" name="template_key">';
        foreach ($this->allowedTemplateKeys() as $key) {
            $label = $key === EmailTemplateService::TEMPLATE_REGISTRATION_CONFIRMATION
                ? __('Inschrijvingsbevestiging', 'bso-survival')
                : __('Publicatieresultaat', 'bso-survival');
            echo '<option value="' . esc_attr($key) . '" ' . selected($templateKey, $key, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select> ';
        echo '<button class="button">' . esc_html__('Laden', 'bso-survival') . '</button>';
        echo '</form>';

        if (isset($_GET['saved'])) {
            $saved = (int) $_GET['saved'] === 1;
            $noticeClass = $saved ? 'notice-success' : 'notice-error';
            $noticeText = $saved ? __('Template opgeslagen.', 'bso-survival') : __('Template opslaan mislukt.', 'bso-survival');
            if (!$saved && isset($_GET['error']) && is_string($_GET['error']) && $_GET['error'] !== '') {
                $noticeText .= ' ' . sanitize_text_field((string) $_GET['error']);
            }
            echo '<div class="notice ' . esc_attr($noticeClass) . ' is-dismissible"><p>' . esc_html($noticeText) . '</p></div>';
        }

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="max-width:960px;">';
        echo '<input type="hidden" name="action" value="bso_survival_save_email_template" />';
        echo '<input type="hidden" name="template_key" value="' . esc_attr($templateKey) . '" />';
        wp_nonce_field(self::SAVE_NONCE_ACTION, self::SAVE_NONCE_FIELD);

        echo '<table class="form-table" role="presentation">';
        echo '<tbody>';

        echo '<tr>';
        echo '<th scope="row"><label for="bso-email-template-subject">' . esc_html__('Subject', 'bso-survival') . '</label></th>';
        echo '<td><input id="bso-email-template-subject" type="text" name="subject" class="large-text" value="' . esc_attr((string) ($template->subject ?? '')) . '" /></td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th scope="row"><label for="bso-email-template-body">' . esc_html__('HTML body', 'bso-survival') . '</label></th>';
        echo '<td><textarea id="bso-email-template-body" name="html_body" class="large-text code" rows="16">' . esc_textarea((string) ($template->html_body ?? '')) . '</textarea>';
        echo '<p class="description">' . esc_html__('Beschikbare placeholders', 'bso-survival') . ': ';
        $first = true;
        foreach ($placeholders as $placeholder) {
            if (!$first) {
                echo ', ';
            }
            $first = false;
            echo '<code>{' . esc_html($placeholder) . '}</code>';
        }
        echo '</p></td>';
        echo '</tr>';

        echo '</tbody>';
        echo '</table>';

        echo '<p><button class="button button-primary">' . esc_html__('Template opslaan', 'bso-survival') . '</button></p>';
        echo '</form>';

        echo '<h2>' . esc_html__('Preview', 'bso-survival') . '</h2>';
        echo '<div class="notice inline" style="display:block;padding:10px 12px;">';
        echo '<p><strong>' . esc_html__('Subject', 'bso-survival') . ':</strong> ' . esc_html((string) ($preview['subject'] ?? '')) . '</p>';
        echo '<div><strong>' . esc_html__('Body', 'bso-survival') . ':</strong></div>';
        echo '<div style="background:#fff;border:1px solid #ccd0d4;padding:10px;margin-top:6px;">' . wp_kses_post((string) ($preview['body'] ?? '')) . '</div>';
        echo '</div>';

        if ($this->outbox !== null) {
            $messages = $this->outbox->recentMessages(20);
            echo '<h2>' . esc_html__('Outbox status (laatste 20)', 'bso-survival') . '</h2>';
            if ($messages === []) {
                echo '<p>' . esc_html__('Nog geen outbox-berichten.', 'bso-survival') . '</p>';
            } else {
                echo '<table class="widefat striped" style="max-width:1100px;">';
                echo '<thead><tr>';
                echo '<th>' . esc_html__('ID', 'bso-survival') . '</th>';
                echo '<th>' . esc_html__('Template', 'bso-survival') . '</th>';
                echo '<th>' . esc_html__('Recipient', 'bso-survival') . '</th>';
                echo '<th>' . esc_html__('Status', 'bso-survival') . '</th>';
                echo '<th>' . esc_html__('Attempts', 'bso-survival') . '</th>';
                echo '<th>' . esc_html__('Last error', 'bso-survival') . '</th>';
                echo '</tr></thead><tbody>';
                foreach ($messages as $message) {
                    echo '<tr>';
                    echo '<td>' . (int) ($message->id ?? 0) . '</td>';
                    echo '<td>' . esc_html((string) ($message->template_key ?? '')) . '</td>';
                    echo '<td>' . esc_html((string) ($message->recipient ?? '')) . '</td>';
                    echo '<td>' . esc_html((string) ($message->status ?? '')) . '</td>';
                    echo '<td>' . (int) ($message->attempt_count ?? 0) . '</td>';
                    echo '<td>' . esc_html((string) ($message->last_error ?? '')) . '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
            }
        }
        echo '</div>';
    }

    /**
     * @return array<int, string>
     */
    private function allowedTemplateKeys(): array {
        return [
            EmailTemplateService::TEMPLATE_PUBLICATION_RESULT,
            EmailTemplateService::TEMPLATE_REGISTRATION_CONFIRMATION,
        ];
    }

    private function resolveUpdatedBy(): string {
        if (function_exists('wp_get_current_user')) {
            $user = wp_get_current_user();
            if (is_object($user) && isset($user->user_login) && $user->user_login !== '') {
                return (string) $user->user_login;
            }
        }

        return 'beheerder';
    }
}

<?php

namespace BSO\Survival\Admin;

use BSO\Survival\Service\EmailTemplateService;

class EmailTemplateAdminPage {
    private const SAVE_NONCE_ACTION = 'bso_survival_save_email_template';
    private const SAVE_NONCE_FIELD = 'bso_survival_save_email_template_nonce';

    /** @var EmailTemplateService */
    private $templates;

    public function __construct(EmailTemplateService $templates) {
        $this->templates = $templates;
    }

    public function registerMenu(): void {
        if (!function_exists('add_submenu_page')) {
            return;
        }

        add_submenu_page(
            'bso-survival-rules',
            __('Email Templates', 'bso-survival'),
            __('Email Templates', 'bso-survival'),
            'manage_options',
            'bso-survival-email-templates',
            [$this, 'renderPage']
        );
    }

    public function handleSave(): void {
        if (!function_exists('current_user_can') || !current_user_can('manage_options')) {
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

        $saved = $this->templates->saveTemplate($templateKey, $subject, $htmlBody, $updatedBy);

        $redirect = add_query_arg(
            [
                'page' => 'bso-survival-email-templates',
                'saved' => $saved ? 1 : 0,
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

        $templateKey = EmailTemplateService::TEMPLATE_PUBLICATION_RESULT;
        $template = $this->templates->getTemplate($templateKey);

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('BSO Survival Email Templates', 'bso-survival') . '</h1>';
        echo '<p>' . esc_html__('Beheer de template voor publicatie-notificaties.', 'bso-survival') . '</p>';

        if (isset($_GET['saved'])) {
            $saved = (int) $_GET['saved'] === 1;
            $noticeClass = $saved ? 'notice-success' : 'notice-error';
            $noticeText = $saved ? __('Template opgeslagen.', 'bso-survival') : __('Template opslaan mislukt.', 'bso-survival');
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
        echo '<p class="description">' . esc_html__('Beschikbare placeholders: {headline}, {event_id}, {published_at}, {top_3_html}', 'bso-survival') . '</p></td>';
        echo '</tr>';

        echo '</tbody>';
        echo '</table>';

        echo '<p><button class="button button-primary">' . esc_html__('Template opslaan', 'bso-survival') . '</button></p>';
        echo '</form>';
        echo '</div>';
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

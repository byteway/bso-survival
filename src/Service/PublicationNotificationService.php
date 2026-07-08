<?php

namespace BSO\Survival\Service;

class PublicationNotificationService {
    /** @var EmailTemplateService|null */
    private $templates;

    /** @var EmailOutboxService|null */
    private $outbox;

    /** @var OutboxProcessorService|null */
    private $processor;

    public function __construct(EmailTemplateService $templates = null, EmailOutboxService $outbox = null, OutboxProcessorService $processor = null) {
        $this->templates = $templates;
        $this->outbox = $outbox;
        $this->processor = $processor;
    }

    /**
     * @param array<string, mixed> $publication
     * @return array<string, mixed>
     */
    public function sendPublicationNotifications(int $eventId, array $publication, string $changedBy): array {
        $recipients = $this->normalizeRecipients($publication['recipients'] ?? []);
        if ($recipients === []) {
            return [
                'sent_count' => 0,
                'failed_count' => 0,
                'sent_to' => [],
                'failed_to' => [],
            ];
        }

        $subject = $this->buildSubject($publication);
        $body = $this->buildBody($publication);
        $headers = ['Content-Type: text/html; charset=UTF-8'];

        if ($this->templates !== null && $this->outbox !== null && $this->processor !== null) {
            return $this->queueAndProcess($eventId, $recipients, $publication, $changedBy);
        }

        if (function_exists('do_action')) {
            do_action('bso_survival_before_publication_notifications', $eventId, $recipients, $subject, $publication, $changedBy);
        }

        $sentTo = [];
        $failedTo = [];

        foreach ($recipients as $recipient) {
            $sent = function_exists('wp_mail')
                ? (bool) wp_mail($recipient, $subject, $body, $headers)
                : false;

            if ($sent) {
                $sentTo[] = $recipient;
            } else {
                $failedTo[] = $recipient;
            }
        }

        $summary = [
            'sent_count' => count($sentTo),
            'failed_count' => count($failedTo),
            'sent_to' => $sentTo,
            'failed_to' => $failedTo,
        ];

        if (function_exists('do_action')) {
            do_action('bso_survival_publication_notifications_sent', $eventId, $summary, $publication, $changedBy);
        }

        return $summary;
    }

    /**
     * @param array<int, string> $recipients
     * @param array<string, mixed> $publication
     * @return array<string, mixed>
     */
    private function queueAndProcess(int $eventId, array $recipients, array $publication, string $changedBy): array {
        $templateKey = EmailTemplateService::TEMPLATE_PUBLICATION_RESULT;
        $context = $this->buildTemplateContext($eventId, $publication);
        $rendered = $this->templates->render($templateKey, $context);

        if (function_exists('do_action')) {
            do_action('bso_survival_before_publication_notifications', $eventId, $recipients, $rendered['subject'], $publication, $changedBy);
        }

        $queuedRecipients = [];
        $queueFailedRecipients = [];

        foreach ($recipients as $recipient) {
            $dedupeKey = sha1($eventId . '|' . $recipient . '|' . (string) ($publication['published_at'] ?? '') . '|' . (string) ($publication['headline'] ?? ''));
            $queued = $this->outbox->enqueue([
                'event_id' => $eventId,
                'recipient' => $recipient,
                'template_key' => $templateKey,
                'subject' => $rendered['subject'],
                'body' => $rendered['body'],
                'dedupe_key' => $dedupeKey,
            ]);

            if ($queued) {
                $queuedRecipients[] = $recipient;
            } else {
                $queueFailedRecipients[] = $recipient;
            }
        }

        $processed = $this->processor->processDue(200);
        $failedTo = $processed['failed_to'] ?? [];
        foreach ($queueFailedRecipients as $recipient) {
            $failedTo[] = $recipient;
        }

        $failedTo = array_values(array_unique(array_map('strval', $failedTo)));

        $summary = [
            'sent_count' => (int) ($processed['sent'] ?? 0),
            'failed_count' => (int) ($processed['failed'] ?? 0) + count($queueFailedRecipients),
            'retry_count' => (int) ($processed['retry'] ?? 0),
            'queued_count' => count($queuedRecipients),
            'sent_to' => $processed['sent_to'] ?? [],
            'failed_to' => $failedTo,
        ];

        if (function_exists('do_action')) {
            do_action('bso_survival_publication_notifications_sent', $eventId, $summary, $publication, $changedBy);
        }

        return $summary;
    }

    /**
     * @param array<string, mixed> $publication
     * @return array<string, mixed>
     */
    private function buildTemplateContext(int $eventId, array $publication): array {
        $topThree = isset($publication['top_3']) && is_array($publication['top_3']) ? $publication['top_3'] : [];

        return [
            'event_id' => $eventId,
            'headline' => (string) ($publication['headline'] ?? 'Eindstand gepubliceerd'),
            'published_at' => (string) ($publication['published_at'] ?? gmdate('c')),
            'top_3_html' => $this->buildTopThreeHtml($topThree),
        ];
    }

    /**
     * @param array<int, mixed> $topThree
     */
    private function buildTopThreeHtml(array $topThree): string {
        if ($topThree === []) {
            return '<p>Geen top-3 beschikbaar.</p>';
        }

        $html = '<ol>';
        foreach ($topThree as $item) {
            if (!is_array($item)) {
                continue;
            }

            $rank = (int) ($item['rank'] ?? 0);
            $teamName = (string) ($item['team_name'] ?? 'Onbekend team');
            $points = (float) ($item['points'] ?? 0);
            $html .= '<li>' . esc_html(sprintf('#%d %s (%.2f pt)', $rank, $teamName, $points)) . '</li>';
        }

        $html .= '</ol>';
        return $html;
    }

    /**
     * @param mixed $rawRecipients
     * @return array<int, string>
     */
    private function normalizeRecipients($rawRecipients): array {
        if (!is_array($rawRecipients)) {
            return [];
        }

        $normalized = [];
        foreach ($rawRecipients as $recipient) {
            $email = strtolower(trim((string) $recipient));
            if ($email === '') {
                continue;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $normalized[$email] = $email;
        }

        return array_values($normalized);
    }

    /**
     * @param array<string, mixed> $publication
     */
    private function buildSubject(array $publication): string {
        $headline = trim((string) ($publication['headline'] ?? ''));
        if ($headline !== '') {
            return $headline;
        }

        return 'BSO Survival: Eindstand gepubliceerd';
    }

    /**
     * @param array<string, mixed> $publication
     */
    private function buildBody(array $publication): string {
        $headline = trim((string) ($publication['headline'] ?? 'Eindstand gepubliceerd'));
        $topThree = isset($publication['top_3']) && is_array($publication['top_3'])
            ? $publication['top_3']
            : [];

        $body = '<h2>' . esc_html($headline) . '</h2>';
        $body .= '<p>De eindstand van het event is gepubliceerd.</p>';

        if ($topThree !== []) {
            $body .= '<h3>Top 3</h3><ol>';
            foreach ($topThree as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $rank = (int) ($item['rank'] ?? 0);
                $teamName = (string) ($item['team_name'] ?? 'Onbekend team');
                $points = (float) ($item['points'] ?? 0);
                $line = sprintf('#%d %s (%.2f pt)', $rank, $teamName, $points);
                $body .= '<li>' . esc_html($line) . '</li>';
            }
            $body .= '</ol>';
        }

        return $body;
    }
}

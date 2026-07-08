<?php

namespace BSO\Survival\Service;

use BSO\Survival\Contracts\MailerInterface;

class OutboxProcessorService {
    private const MAX_ATTEMPTS = 5;

    /** @var EmailOutboxService */
    private $outbox;

    /** @var MailerInterface */
    private $mailer;

    public function __construct(EmailOutboxService $outbox, MailerInterface $mailer) {
        $this->outbox = $outbox;
        $this->mailer = $mailer;
    }

    /**
     * @return array<string, mixed>
     */
    public function processDue(int $limit = 50): array {
        $messages = $this->outbox->dueMessages($limit);
        $summary = [
            'processed' => 0,
            'sent' => 0,
            'retry' => 0,
            'failed' => 0,
            'sent_to' => [],
            'failed_to' => [],
        ];

        foreach ($messages as $message) {
            $summary['processed']++;
            $id = (int) ($message->id ?? 0);
            $recipient = (string) ($message->recipient ?? '');
            $attemptCount = (int) ($message->attempt_count ?? 0) + 1;
            $subject = (string) ($message->subject_snapshot ?? '');
            $body = (string) ($message->body_snapshot ?? '');
            $sent = $this->mailer->send($recipient, $subject, $body, ['Content-Type: text/html; charset=UTF-8']);

            if ($sent) {
                $this->outbox->markSent($id);
                $summary['sent']++;
                $summary['sent_to'][] = $recipient;
                continue;
            }

            if ($attemptCount >= self::MAX_ATTEMPTS) {
                $this->outbox->markFailed($id, $attemptCount, 'wp_mail returned false');
                $summary['failed']++;
                $summary['failed_to'][] = $recipient;
                continue;
            }

            $this->outbox->markForRetry($id, $attemptCount, 'wp_mail returned false');
            $summary['retry']++;
        }

        if (function_exists('do_action')) {
            do_action('bso_survival_email_outbox_processed', $summary);
        }

        return $summary;
    }
}

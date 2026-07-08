<?php

namespace BSO\Survival\Service;

use BSO\Survival\Database\Repository\EmailOutboxRepositoryInterface;

class EmailOutboxService {
    /** @var EmailOutboxRepositoryInterface */
    private $outbox;

    public function __construct(EmailOutboxRepositoryInterface $outbox) {
        $this->outbox = $outbox;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function enqueue(array $payload): bool {
        $eventId = (int) ($payload['event_id'] ?? 0);
        $recipient = strtolower(trim((string) ($payload['recipient'] ?? '')));
        $templateKey = trim((string) ($payload['template_key'] ?? ''));
        $subject = trim((string) ($payload['subject'] ?? ''));
        $body = trim((string) ($payload['body'] ?? ''));
        $dedupeKey = (string) ($payload['dedupe_key'] ?? '');

        if ($eventId <= 0) {
            return false;
        }

        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        if ($templateKey === '' || $subject === '' || $body === '') {
            return false;
        }

        if ($dedupeKey === '') {
            return false;
        }

        // Column length for dedupe_key is 191, keep deterministic uniqueness but avoid truncation by DB.
        if (strlen($dedupeKey) > 191) {
            $dedupeKey = sha1($dedupeKey);
        }

        if ($this->outbox->findByDedupeKey($dedupeKey) !== null) {
            return true;
        }

        $now = gmdate('Y-m-d H:i:s');
        $record = $this->outbox->insert([
            'event_id' => $eventId,
            'recipient' => $recipient,
            'template_key' => $templateKey,
            'subject_snapshot' => $subject,
            'body_snapshot' => $body,
            'status' => 'queued',
            'attempt_count' => 0,
            'next_attempt_at' => $now,
            'sent_at' => null,
            'last_error' => null,
            'dedupe_key' => $dedupeKey,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $record !== null;
    }

    /**
     * @return array<int, object>
     */
    public function dueMessages(int $limit = 50): array {
        return $this->outbox->findDue(gmdate('Y-m-d H:i:s'), max(1, $limit));
    }

    public function markSent(int $id): bool {
        return $this->outbox->markSent($id, gmdate('Y-m-d H:i:s'));
    }

    public function markForRetry(int $id, int $attemptCount, string $lastError): bool {
        $next = gmdate('Y-m-d H:i:s', time() + $this->retryDelaySeconds($attemptCount));
        return $this->outbox->markForRetry($id, $attemptCount, $next, $lastError);
    }

    public function markFailed(int $id, int $attemptCount, string $lastError): bool {
        return $this->outbox->markFailed($id, $attemptCount, $lastError);
    }

    /**
     * @return array<int, object>
     */
    public function recentMessages(int $limit = 20): array {
        return $this->outbox->findRecent(max(1, $limit));
    }

    private function retryDelaySeconds(int $attemptCount): int {
        if ($attemptCount <= 1) {
            return 60;
        }

        if ($attemptCount === 2) {
            return 300;
        }

        if ($attemptCount === 3) {
            return 1800;
        }

        return 7200;
    }
}

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
        $dedupeKey = (string) ($payload['dedupe_key'] ?? '');
        if ($dedupeKey === '') {
            return false;
        }

        if ($this->outbox->findByDedupeKey($dedupeKey) !== null) {
            return true;
        }

        $now = gmdate('Y-m-d H:i:s');
        $record = $this->outbox->insert([
            'event_id' => (int) ($payload['event_id'] ?? 0),
            'recipient' => (string) ($payload['recipient'] ?? ''),
            'template_key' => (string) ($payload['template_key'] ?? ''),
            'subject_snapshot' => (string) ($payload['subject'] ?? ''),
            'body_snapshot' => (string) ($payload['body'] ?? ''),
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

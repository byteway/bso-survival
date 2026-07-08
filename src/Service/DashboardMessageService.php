<?php

namespace BSO\Survival\Service;

use BSO\Survival\Database\Repository\DashboardMessageRepositoryInterface;
use InvalidArgumentException;
use RuntimeException;

class DashboardMessageService {
    /** @var DashboardMessageRepositoryInterface */
    private $messages;

    /** @var AuditLogService|null */
    private $audit;

    public function __construct(DashboardMessageRepositoryInterface $messages, AuditLogService $audit = null) {
        $this->messages = $messages;
        $this->audit = $audit;
    }

    /**
     * @return array<int, object>
     */
    public function listForEvent(int $eventId, int $limit = 20): array {
        if ($eventId <= 0) {
            throw new InvalidArgumentException('event_id must be a positive integer.');
        }

        return $this->messages->findByEventId($eventId, $limit);
    }

    /**
     * @return array<int, object>
     */
    public function listActiveForEvent(int $eventId, int $limit = 5): array {
        if ($eventId <= 0) {
            throw new InvalidArgumentException('event_id must be a positive integer.');
        }

        return $this->messages->findActiveByEventId($eventId, $limit);
    }

    /**
     * @param array<string, mixed> $payload
     * @return object
     */
    public function create(array $payload) {
        $eventId = (int) ($payload['event_id'] ?? 0);
        $type = trim((string) ($payload['type'] ?? 'info'));
        $text = trim((string) ($payload['text'] ?? ''));
        $visibility = trim((string) ($payload['visibility'] ?? 'intern'));
        $status = trim((string) ($payload['status'] ?? 'actief'));
        $changedBy = trim((string) ($payload['changed_by'] ?? 'admin'));

        if ($eventId <= 0) {
            throw new InvalidArgumentException('event_id must be a positive integer.');
        }

        if ($text === '') {
            throw new InvalidArgumentException('text is verplicht.');
        }

        if ($type === '') {
            $type = 'info';
        }

        if (!in_array($status, ['actief', 'inactief'], true)) {
            throw new InvalidArgumentException('status moet actief of inactief zijn.');
        }

        if ($visibility === '') {
            $visibility = 'intern';
        }

        $now = gmdate('Y-m-d H:i:s');
        $created = $this->messages->create([
            'event_id' => $eventId,
            'type' => $type,
            'text' => $text,
            'visibility' => $visibility,
            'status' => $status,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($created === null) {
            throw new RuntimeException('Melding kon niet worden opgeslagen.');
        }

        if ($this->audit !== null) {
            $this->audit->log(
                $eventId,
                'dashboard_message',
                (int) ($created->id ?? 0),
                'created',
                null,
                [
                    'type' => $type,
                    'status' => $status,
                    'visibility' => $visibility,
                ],
                $changedBy
            );
        }

        return $created;
    }

    public function setStatus(int $messageId, int $eventId, string $status, string $changedBy = 'admin') {
        if ($messageId <= 0) {
            throw new InvalidArgumentException('message_id must be a positive integer.');
        }

        if ($eventId <= 0) {
            throw new InvalidArgumentException('event_id must be a positive integer.');
        }

        if (!in_array($status, ['actief', 'inactief'], true)) {
            throw new InvalidArgumentException('status moet actief of inactief zijn.');
        }

        $existing = $this->messages->findById($messageId);
        if ($existing === null) {
            throw new InvalidArgumentException(sprintf('message %d not found.', $messageId));
        }

        if ((int) ($existing->event_id ?? 0) !== $eventId) {
            throw new InvalidArgumentException('message hoort niet bij dit event_id.');
        }

        $updated = $this->messages->updateStatus($messageId, $status);
        if ($updated === null) {
            throw new RuntimeException('Meldingstatus kon niet worden bijgewerkt.');
        }

        if ($this->audit !== null) {
            $this->audit->log(
                $eventId,
                'dashboard_message',
                $messageId,
                'status_changed',
                ['status' => (string) ($existing->status ?? '')],
                ['status' => $status],
                trim($changedBy) === '' ? 'admin' : $changedBy
            );
        }

        return $updated;
    }
}

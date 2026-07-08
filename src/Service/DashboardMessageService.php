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
    public function listForEvent(int $eventId, int $limit = 20, string $scope = 'all'): array {
        if ($eventId <= 0) {
            throw new InvalidArgumentException('event_id must be a positive integer.');
        }

        return $this->messages->findByScope($eventId, $scope, false, $limit, 0);
    }

    /**
     * @return array{items: array<int, object>, total: int, page: int, per_page: int}
     */
    public function listPageForEvent(int $eventId, int $page = 1, int $perPage = 20, string $scope = 'all'): array {
        if ($eventId <= 0) {
            throw new InvalidArgumentException('event_id must be a positive integer.');
        }

        if ($page <= 0) {
            throw new InvalidArgumentException('page must be a positive integer.');
        }

        if ($perPage <= 0 || $perPage > 100) {
            throw new InvalidArgumentException('per_page must be between 1 and 100.');
        }

        $offset = ($page - 1) * $perPage;

        return [
            'items' => $this->messages->findByScope($eventId, $scope, false, $perPage, $offset),
            'total' => $this->messages->countByScope($eventId, $scope, false),
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * @return array<int, object>
     */
    public function listActiveForEvent(int $eventId, int $limit = 5, string $scope = 'all'): array {
        if ($eventId <= 0) {
            throw new InvalidArgumentException('event_id must be a positive integer.');
        }

        return $this->messages->findByScope($eventId, $scope, true, $limit, 0);
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
        $scope = trim((string) ($payload['scope'] ?? 'event'));
        $status = trim((string) ($payload['status'] ?? 'actief'));
        $changedBy = trim((string) ($payload['changed_by'] ?? 'admin'));
        $metaData = $payload['meta_data'] ?? null;
        $visibleFromRaw = $payload['visible_from'] ?? null;
        $visibleUntilRaw = $payload['visible_until'] ?? null;

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

        if (!in_array($scope, ['event', 'global'], true)) {
            throw new InvalidArgumentException('scope moet event of global zijn.');
        }

        $metaDataJson = $this->normalizeMetaData($metaData);
        $window = $this->normalizeVisibilityWindow($visibleFromRaw, $visibleUntilRaw);

        if ($scope === 'global') {
            $visibility = 'global';
        }

        $now = gmdate('Y-m-d H:i:s');
        $created = $this->messages->create([
            'event_id' => $eventId,
            'type' => $type,
            'text' => $text,
            'visibility' => $visibility,
            'status' => $status,
            'meta_data' => $metaDataJson,
            'visible_from' => $window['visible_from'],
            'visible_until' => $window['visible_until'],
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

    /**
     * @param mixed $value
     */
    private function normalizeMetaData($value): ?string {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (!is_array($decoded)) {
                throw new InvalidArgumentException('meta_data moet geldige JSON zijn.');
            }

            return json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if (!is_array($value)) {
            throw new InvalidArgumentException('meta_data moet een array of JSON-string zijn.');
        }

        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            throw new InvalidArgumentException('meta_data kon niet worden gecodeerd.');
        }

        return $encoded;
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

        if ((string) ($existing->visibility ?? '') === 'global') {
            $updated = $this->messages->updateStatusForEvent($messageId, 0, $status);
        } else {
            $updated = $this->messages->updateStatusForEvent($messageId, $eventId, $status);
        }

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

    /**
     * @param array<string, mixed> $payload
     * @return object
     */
    public function update(int $messageId, int $eventId, array $payload, string $changedBy = 'admin') {
        if ($messageId <= 0) {
            throw new InvalidArgumentException('message_id must be a positive integer.');
        }

        if ($eventId <= 0) {
            throw new InvalidArgumentException('event_id must be a positive integer.');
        }

        $existing = $this->messages->findById($messageId);
        if ($existing === null) {
            throw new InvalidArgumentException(sprintf('message %d not found.', $messageId));
        }

        if ((int) ($existing->event_id ?? 0) !== $eventId) {
            throw new InvalidArgumentException('message hoort niet bij dit event_id.');
        }

        $type = trim((string) ($payload['type'] ?? (string) ($existing->type ?? 'info')));
        if ($type === '') {
            $type = 'info';
        }

        $text = trim((string) ($payload['text'] ?? (string) ($existing->text ?? '')));
        if ($text === '') {
            throw new InvalidArgumentException('text is verplicht.');
        }

        $status = trim((string) ($payload['status'] ?? (string) ($existing->status ?? 'actief')));
        if (!in_array($status, ['actief', 'inactief'], true)) {
            throw new InvalidArgumentException('status moet actief of inactief zijn.');
        }

        $scope = trim((string) ($payload['scope'] ?? ''));
        if ($scope !== '' && !in_array($scope, ['event', 'global'], true)) {
            throw new InvalidArgumentException('scope moet event of global zijn.');
        }

        $visibility = trim((string) ($payload['visibility'] ?? (string) ($existing->visibility ?? 'intern')));
        if ($visibility === '') {
            $visibility = 'intern';
        }

        if ($scope === 'global') {
            $visibility = 'global';
        } elseif ($scope === 'event' && $visibility === 'global') {
            $visibility = 'intern';
        }

        $metaDataPayload = array_key_exists('meta_data', $payload)
            ? $payload['meta_data']
            : (string) ($existing->meta_data ?? '');
        $metaDataJson = $this->normalizeMetaData($metaDataPayload);

        $visibleFromRaw = array_key_exists('visible_from', $payload)
            ? $payload['visible_from']
            : (string) ($existing->visible_from ?? '');
        $visibleUntilRaw = array_key_exists('visible_until', $payload)
            ? $payload['visible_until']
            : (string) ($existing->visible_until ?? '');
        $window = $this->normalizeVisibilityWindow($visibleFromRaw, $visibleUntilRaw);

        $data = [
            'type' => $type,
            'text' => $text,
            'visibility' => $visibility,
            'status' => $status,
            'meta_data' => $metaDataJson,
            'visible_from' => $window['visible_from'],
            'visible_until' => $window['visible_until'],
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ];

        if ((string) ($existing->visibility ?? '') === 'global') {
            $updated = $this->messages->updateByIdForEvent($messageId, 0, $data);
        } else {
            $updated = $this->messages->updateByIdForEvent($messageId, $eventId, $data);
        }

        if ($updated === null) {
            throw new RuntimeException('Melding kon niet worden bijgewerkt.');
        }

        if ($this->audit !== null) {
            $this->audit->log(
                $eventId,
                'dashboard_message',
                $messageId,
                'updated',
                [
                    'type' => (string) ($existing->type ?? ''),
                    'text' => (string) ($existing->text ?? ''),
                    'visibility' => (string) ($existing->visibility ?? ''),
                    'status' => (string) ($existing->status ?? ''),
                    'meta_data' => (string) ($existing->meta_data ?? ''),
                    'visible_from' => (string) ($existing->visible_from ?? ''),
                    'visible_until' => (string) ($existing->visible_until ?? ''),
                ],
                [
                    'type' => $type,
                    'text' => $text,
                    'visibility' => $visibility,
                    'status' => $status,
                    'meta_data' => $metaDataJson,
                    'visible_from' => $window['visible_from'],
                    'visible_until' => $window['visible_until'],
                ],
                trim($changedBy) === '' ? 'admin' : $changedBy
            );
        }

        return $updated;
    }

    public function delete(int $messageId, int $eventId, string $changedBy = 'admin'): bool {
        if ($messageId <= 0) {
            throw new InvalidArgumentException('message_id must be a positive integer.');
        }

        if ($eventId <= 0) {
            throw new InvalidArgumentException('event_id must be a positive integer.');
        }

        $existing = $this->messages->findById($messageId);
        if ($existing === null) {
            throw new InvalidArgumentException(sprintf('message %d not found.', $messageId));
        }

        if ((int) ($existing->event_id ?? 0) !== $eventId) {
            throw new InvalidArgumentException('message hoort niet bij dit event_id.');
        }

        if ((string) ($existing->visibility ?? '') === 'global') {
            $deleted = $this->messages->deleteByIdForEvent($messageId, 0);
        } else {
            $deleted = $this->messages->deleteByIdForEvent($messageId, $eventId);
        }

        if (!$deleted) {
            throw new RuntimeException('Melding kon niet worden verwijderd.');
        }

        if ($this->audit !== null) {
            $this->audit->log(
                $eventId,
                'dashboard_message',
                $messageId,
                'deleted',
                [
                    'type' => (string) ($existing->type ?? ''),
                    'text' => (string) ($existing->text ?? ''),
                    'visibility' => (string) ($existing->visibility ?? ''),
                    'status' => (string) ($existing->status ?? ''),
                ],
                null,
                trim($changedBy) === '' ? 'admin' : $changedBy
            );
        }

        return true;
    }

    /**
     * @param mixed $visibleFromRaw
     * @param mixed $visibleUntilRaw
     * @return array{visible_from: string|null, visible_until: string|null}
     */
    private function normalizeVisibilityWindow($visibleFromRaw, $visibleUntilRaw): array {
        $visibleFrom = $this->normalizeDateTime($visibleFromRaw, 'visible_from');
        $visibleUntil = $this->normalizeDateTime($visibleUntilRaw, 'visible_until');

        if ($visibleFrom !== null && $visibleUntil !== null && strtotime($visibleUntil) <= strtotime($visibleFrom)) {
            throw new InvalidArgumentException('visible_until moet groter zijn dan visible_from.');
        }

        return [
            'visible_from' => $visibleFrom,
            'visible_until' => $visibleUntil,
        ];
    }

    /**
     * @param mixed $value
     */
    private function normalizeDateTime($value, string $field): ?string {
        if ($value === null) {
            return null;
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $normalized = str_replace('T', ' ', $raw);
        $timestamp = strtotime($normalized);
        if ($timestamp === false) {
            throw new InvalidArgumentException(sprintf('%s moet een geldige datum/tijd zijn.', $field));
        }

        return gmdate('Y-m-d H:i:s', $timestamp);
    }
}

<?php

namespace BSO\Survival\Service;

use BSO\Survival\Database\Repository\AuditLogRepositoryInterface;
use InvalidArgumentException;
use RuntimeException;

class AuditLogService {
    /** @var AuditLogRepositoryInterface */
    private $auditLogs;

    public function __construct(AuditLogRepositoryInterface $auditLogs) {
        $this->auditLogs = $auditLogs;
    }

    /**
     * @param mixed $oldValue
     * @param mixed $newValue
     * @param array<string, mixed> $context
     * @return object
     */
    public function log(?int $eventId, string $entityType, int $entityId, string $action, $oldValue, $newValue, string $changedBy, array $context = []) {
        $this->guardOptionalPositiveId($eventId, 'event id');
        $this->guardNonEmpty($entityType, 'entity type');
        $this->guardPositiveId($entityId, 'entity id');
        $this->guardNonEmpty($action, 'action');
        $this->guardNonEmpty($changedBy, 'changed_by');

        $payload = [
            'event_id' => $eventId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'action' => $action,
            'old_value' => $this->encodeValue($oldValue),
            'new_value' => $this->encodeValue($newValue),
            'changed_by' => $changedBy,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ];

        if (function_exists('do_action')) {
            do_action('bso_survival_before_audit_log_write', $payload, $context);
        }

        try {
            $stored = $this->auditLogs->insert($payload);
        } catch (\Throwable $e) {
            if (function_exists('do_action')) {
                do_action('bso_survival_audit_log_failed', $payload, $context, $e);
            }

            throw new RuntimeException('Failed to write audit log.', 0, $e);
        }

        if ($stored === null) {
            if (function_exists('do_action')) {
                do_action('bso_survival_audit_log_failed', $payload, $context, null);
            }

            throw new RuntimeException('Failed to write audit log.');
        }

        if (function_exists('do_action')) {
            do_action('bso_survival_audit_log_written', (int) $stored->id, $payload, $stored, $context);
        }

        return $stored;
    }

    /**
     * @param mixed $value
     */
    private function encodeValue($value): string {
        if (is_string($value)) {
            return $value;
        }

        if ($value === null) {
            return '';
        }

        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            return '';
        }

        return $encoded;
    }

    private function guardOptionalPositiveId(?int $id, string $label): void {
        if ($id !== null && $id <= 0) {
            throw new InvalidArgumentException(sprintf('%s must be a positive integer when provided.', $label));
        }
    }

    private function guardPositiveId(int $id, string $label): void {
        if ($id <= 0) {
            throw new InvalidArgumentException(sprintf('%s must be a positive integer.', $label));
        }
    }

    private function guardNonEmpty(string $value, string $label): void {
        if (trim($value) === '') {
            throw new InvalidArgumentException(sprintf('%s must not be empty.', $label));
        }
    }
}

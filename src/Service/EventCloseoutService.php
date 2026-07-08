<?php

namespace BSO\Survival\Service;

use InvalidArgumentException;

class EventCloseoutService {
    /** @var EventService */
    private $events;

    /** @var CertificateService */
    private $certificates;

    /** @var AuditLogService */
    private $auditLogs;

    public function __construct(EventService $events, CertificateService $certificates, AuditLogService $auditLogs) {
        $this->events = $events;
        $this->certificates = $certificates;
        $this->auditLogs = $auditLogs;
    }

    /**
     * @param array<int, array<string, mixed>> $certificateDefinitions
     * @return array<string, mixed>
     */
    public function closeEvent(int $eventId, string $changedBy, array $certificateDefinitions = []): array {
        if ($eventId <= 0) {
            throw new InvalidArgumentException('event id must be a positive integer.');
        }

        if (trim($changedBy) === '') {
            throw new InvalidArgumentException('changed_by must not be empty.');
        }

        $event = $this->events->getEvent($eventId);
        if ($event === null) {
            throw new InvalidArgumentException(sprintf('Event %d not found.', $eventId));
        }

        if (function_exists('do_action')) {
            do_action('bso_survival_before_event_closeout', $eventId, $changedBy, $certificateDefinitions, $event);
        }

        $generatedCertificates = [];
        foreach ($certificateDefinitions as $definition) {
            $generatedCertificates[] = $this->certificates->generate(
                $eventId,
                (int) ($definition['team_id'] ?? 0),
                (string) ($definition['file_path'] ?? ''),
                isset($definition['meta']) && is_array($definition['meta']) ? $definition['meta'] : []
            );
        }

        $this->events->updateStatus($eventId, 'afgesloten');

        $auditLog = $this->auditLogs->log(
            $eventId,
            'event',
            $eventId,
            'closeout_completed',
            ['status' => $event->status ?? null],
            ['status' => 'afgesloten', 'certificates' => count($generatedCertificates)],
            $changedBy,
            ['certificate_count' => count($generatedCertificates)]
        );

        $result = [
            'event_id' => $eventId,
            'status' => 'afgesloten',
            'certificates' => $generatedCertificates,
            'audit_log' => $auditLog,
        ];

        if (function_exists('do_action')) {
            do_action('bso_survival_event_closed_out', $eventId, $result, $changedBy);
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $publicationData
     * @return array<string, mixed>
     */
    public function publishEvent(int $eventId, string $changedBy, array $publicationData = []): array {
        if ($eventId <= 0) {
            throw new InvalidArgumentException('event id must be a positive integer.');
        }

        if (trim($changedBy) === '') {
            throw new InvalidArgumentException('changed_by must not be empty.');
        }

        $event = $this->events->getEvent($eventId);
        if ($event === null) {
            throw new InvalidArgumentException(sprintf('Event %d not found.', $eventId));
        }

        if (function_exists('do_action')) {
            do_action('bso_survival_before_event_publication', $eventId, $changedBy, $publicationData, $event);
        }

        $this->events->updateStatus($eventId, 'gepubliceerd');

        $auditLog = $this->auditLogs->log(
            $eventId,
            'event',
            $eventId,
            'publication_completed',
            ['status' => $event->status ?? null],
            ['status' => 'gepubliceerd', 'publication' => $publicationData],
            $changedBy,
            ['publication' => $publicationData]
        );

        $result = [
            'event_id' => $eventId,
            'status' => 'gepubliceerd',
            'publication' => $publicationData,
            'audit_log' => $auditLog,
        ];

        if (function_exists('do_action')) {
            do_action('bso_survival_event_published', $eventId, $result, $changedBy);
        }

        return $result;
    }
}

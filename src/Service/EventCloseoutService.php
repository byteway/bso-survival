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

    /** @var PublicationNotificationService|null */
    private $publicationNotifications;

    /** @var EventPublicationService|null */
    private $publicationResults;

    public function __construct(EventService $events, CertificateService $certificates, AuditLogService $auditLogs, PublicationNotificationService $publicationNotifications = null, EventPublicationService $publicationResults = null) {
        $this->events = $events;
        $this->certificates = $certificates;
        $this->auditLogs = $auditLogs;
        $this->publicationNotifications = $publicationNotifications;
        $this->publicationResults = $publicationResults;
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

        $publication = $this->buildPublicationPayload($publicationData);

        if (function_exists('do_action')) {
            do_action('bso_survival_before_event_publication', $eventId, $changedBy, $publication, $event);
        }

        $this->events->updateStatus($eventId, 'gepubliceerd');

        $persistedPublication = null;
        if ($this->publicationResults !== null) {
            $this->publicationResults->saveForEvent($eventId, $publication, $changedBy);
            $persistedPublication = $this->publicationResults->getForEvent($eventId);
        }

        $notifications = null;
        if ($this->publicationNotifications !== null) {
            $notifications = $this->publicationNotifications->sendPublicationNotifications($eventId, $publication, $changedBy);
        }

        $auditLog = $this->auditLogs->log(
            $eventId,
            'event',
            $eventId,
            'publication_completed',
            ['status' => $event->status ?? null],
            ['status' => 'gepubliceerd', 'publication' => $publication, 'publication_persisted' => $persistedPublication, 'notifications' => $notifications],
            $changedBy,
            ['publication' => $publication, 'publication_persisted' => $persistedPublication, 'notifications' => $notifications]
        );

        $result = [
            'event_id' => $eventId,
            'status' => 'gepubliceerd',
            'publication' => $publication,
            'publication_persisted' => $persistedPublication,
            'notifications' => $notifications,
            'audit_log' => $auditLog,
        ];

        if (function_exists('do_action')) {
            do_action('bso_survival_event_published', $eventId, $result, $changedBy);
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $publicationData
     * @return array<string, mixed>
     */
    private function buildPublicationPayload(array $publicationData): array {
        $headline = trim((string) ($publicationData['headline'] ?? ''));
        $publishedAt = trim((string) ($publicationData['published_at'] ?? ''));
        $standingsSource = [];

        if (isset($publicationData['final_standings']) && is_array($publicationData['final_standings'])) {
            $standingsSource = $publicationData['final_standings'];
        } elseif (isset($publicationData['standings']) && is_array($publicationData['standings'])) {
            $standingsSource = $publicationData['standings'];
        }

        $finalStandings = $this->normalizeStandings($standingsSource);
        $topThree = array_slice($finalStandings, 0, 3);

        return [
            'headline' => $headline,
            'published_at' => $publishedAt !== '' ? $publishedAt : gmdate('c'),
            'top_3' => $topThree,
            'final_standings' => $finalStandings,
            'recipients' => isset($publicationData['recipients']) && is_array($publicationData['recipients'])
                ? $publicationData['recipients']
                : [],
        ];
    }

    /**
     * @param array<int, mixed> $standingsSource
     * @return array<int, array<string, mixed>>
     */
    private function normalizeStandings(array $standingsSource): array {
        $normalized = [];
        $fallbackRank = 1;

        foreach ($standingsSource as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $rank = isset($entry['rank']) ? (int) $entry['rank'] : $fallbackRank;
            if ($rank <= 0) {
                $rank = $fallbackRank;
            }

            $normalized[] = [
                'rank' => $rank,
                'team_id' => isset($entry['team_id']) ? (int) $entry['team_id'] : 0,
                'team_name' => (string) ($entry['team_name'] ?? ''),
                'points' => isset($entry['points']) ? (float) $entry['points'] : 0.0,
            ];

            $fallbackRank++;
        }

        usort($normalized, static function (array $a, array $b): int {
            // Tie policy: rank asc, points desc, team_name asc (case-insensitive), team_id asc.
            $byRank = $a['rank'] <=> $b['rank'];
            if ($byRank !== 0) {
                return $byRank;
            }

            $byPoints = $b['points'] <=> $a['points'];
            if ($byPoints !== 0) {
                return $byPoints;
            }

            $byName = strcasecmp((string) $a['team_name'], (string) $b['team_name']);
            if ($byName !== 0) {
                return $byName;
            }

            return $a['team_id'] <=> $b['team_id'];
        });

        return $normalized;
    }
}

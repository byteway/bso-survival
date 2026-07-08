<?php

namespace BSO\Survival\Service;

use BSO\Survival\Database\Repository\RegistrationWindowRepositoryInterface;
use BSO\Survival\Database\Repository\TeamMemberRepositoryInterface;
use BSO\Survival\Database\Repository\TeamRepositoryInterface;
use InvalidArgumentException;
use RuntimeException;

class TeamRegistrationService {
    /** @var EventService */
    private $events;

    /** @var TeamRepositoryInterface */
    private $teams;

    /** @var TeamMemberRepositoryInterface */
    private $teamMembers;

    /** @var RegistrationWindowRepositoryInterface */
    private $registrationWindows;

    /** @var RegistrationConfirmationService|null */
    private $confirmations;

    /** @var object|null */
    private $wpdb;

    public function __construct(
        EventService $events,
        TeamRepositoryInterface $teams,
        TeamMemberRepositoryInterface $teamMembers,
        RegistrationWindowRepositoryInterface $registrationWindows,
        RegistrationConfirmationService $confirmations = null,
        $wpdb = null
    ) {
        $this->events = $events;
        $this->teams = $teams;
        $this->teamMembers = $teamMembers;
        $this->registrationWindows = $registrationWindows;
        $this->confirmations = $confirmations;

        if ($wpdb === null) {
            global $wpdb;
        }

        $this->wpdb = $wpdb;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function register(array $payload): array {
        $eventId = (int) ($payload['event_id'] ?? 0);
        $teamName = trim((string) ($payload['team_name'] ?? ''));
        $contactName = trim((string) ($payload['contact_name'] ?? ''));
        $contactEmail = strtolower(trim((string) ($payload['contact_email'] ?? '')));
        $contactPhone = trim((string) ($payload['contact_phone'] ?? ''));
        $teamMembers = $this->normalizeMembers($payload['team_members'] ?? []);
        $idempotencyKey = trim((string) ($payload['idempotency_key'] ?? ''));

        if ($eventId <= 0) {
            throw new InvalidArgumentException('event_id must be a positive integer.');
        }

        if ($teamName === '') {
            throw new InvalidArgumentException('team_name is verplicht.');
        }

        if ($contactName === '') {
            throw new InvalidArgumentException('contact_name is verplicht.');
        }

        if ($contactPhone === '') {
            throw new InvalidArgumentException('contact_phone is verplicht.');
        }

        if (!filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('contact_email is ongeldig.');
        }

        if ($teamMembers === []) {
            throw new InvalidArgumentException('team_members moet minimaal 1 naam bevatten.');
        }

        $event = $this->events->getEvent($eventId);
        if ($event === null) {
            throw new InvalidArgumentException(sprintf('Event %d niet gevonden.', $eventId));
        }

        $window = $this->registrationWindows->findOpenForEventAt($eventId, gmdate('Y-m-d H:i:s'));
        if ($window === null) {
            throw new InvalidArgumentException('Inschrijving is op dit moment gesloten.');
        }

        $maxTeams = $this->extractMaxTeams((string) ($event->meta_data ?? ''));
        $registeredTeams = $this->teams->countByEventId($eventId);
        if ($maxTeams > 0 && $registeredTeams >= $maxTeams) {
            throw new InvalidArgumentException('Inschrijving is vol voor dit event.');
        }

        $existing = $this->teams->findByEventIdAndName($eventId, $teamName);
        if ($existing !== null) {
            return [
                'registration_id' => (int) ($existing->id ?? 0),
                'team_id' => (int) ($existing->id ?? 0),
                'status' => 'already_registered',
                'counts' => [
                    'registered_teams' => $registeredTeams,
                    'max_teams' => $maxTeams,
                ],
            ];
        }

        $now = gmdate('Y-m-d H:i:s');
        $teamMeta = [];
        if ($idempotencyKey !== '') {
            $teamMeta['idempotency_key'] = $idempotencyKey;
        }

        $this->beginTransaction();

        try {
            $team = $this->teams->create([
                'event_id' => $eventId,
                'name' => $teamName,
                'contact_name' => $contactName,
                'contact_phone' => $contactPhone,
                'contact_email' => $contactEmail,
                'status' => 'ingeschreven',
                'meta_data' => $teamMeta !== [] ? $this->encodeJson($teamMeta) : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($team === null) {
                throw new RuntimeException('Team kon niet worden opgeslagen.');
            }

            $teamId = (int) ($team->id ?? 0);
            if ($teamId <= 0) {
                throw new RuntimeException('Team id ontbreekt na opslag.');
            }

            $memberRows = [];
            foreach ($teamMembers as $memberName) {
                $memberRows[] = [
                    'team_id' => $teamId,
                    'name' => $memberName,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            $createdMembers = $this->teamMembers->createBatch($memberRows);
            if ($createdMembers !== count($memberRows)) {
                throw new RuntimeException('Niet alle teamleden konden worden opgeslagen.');
            }

            $queuedConfirmation = false;
            if ($this->confirmations !== null) {
                $queuedConfirmation = $this->confirmations->enqueueForRegistration([
                    'event_id' => $eventId,
                    'event_name' => (string) ($event->name ?? ''),
                    'event_date' => (string) ($event->event_date ?? ''),
                    'team_id' => $teamId,
                    'team_name' => $teamName,
                    'contact_name' => $contactName,
                    'contact_email' => $contactEmail,
                    'team_members_count' => count($teamMembers),
                    'registration_id' => (string) $teamId,
                ]);
            }

            $this->commitTransaction();

            $updatedCount = $this->teams->countByEventId($eventId);

            return [
                'registration_id' => $teamId,
                'team_id' => $teamId,
                'status' => 'registered',
                'confirmation_queued' => $queuedConfirmation,
                'counts' => [
                    'registered_teams' => $updatedCount,
                    'max_teams' => $maxTeams,
                ],
            ];
        } catch (\Throwable $exception) {
            $this->rollbackTransaction();
            throw $exception;
        }
    }

    /**
     * @param mixed $members
     * @return array<int, string>
     */
    private function normalizeMembers($members): array {
        if (!is_array($members)) {
            return [];
        }

        $normalized = [];
        foreach ($members as $member) {
            $name = trim((string) $member);
            if ($name === '') {
                continue;
            }

            $normalized[] = $name;
        }

        return array_values(array_unique($normalized));
    }

    private function extractMaxTeams(string $metaData): int {
        if ($metaData === '') {
            return 0;
        }

        $decoded = json_decode($metaData, true);
        if (!is_array($decoded)) {
            return 0;
        }

        $maxTeams = (int) ($decoded['max_teams'] ?? 0);
        return $maxTeams > 0 ? $maxTeams : 0;
    }

    private function encodeJson(array $value): string {
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $encoded === false ? '{}' : $encoded;
    }

    private function beginTransaction(): void {
        if ($this->wpdb !== null && method_exists($this->wpdb, 'query')) {
            $this->wpdb->query('START TRANSACTION');
        }
    }

    private function commitTransaction(): void {
        if ($this->wpdb !== null && method_exists($this->wpdb, 'query')) {
            $this->wpdb->query('COMMIT');
        }
    }

    private function rollbackTransaction(): void {
        if ($this->wpdb !== null && method_exists($this->wpdb, 'query')) {
            $this->wpdb->query('ROLLBACK');
        }
    }
}

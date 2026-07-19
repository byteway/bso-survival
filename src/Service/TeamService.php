<?php

namespace BSO\Survival\Service;

use BSO\Survival\Database\Repository\TeamMemberRepositoryInterface;
use BSO\Survival\Database\Repository\TeamRepositoryInterface;
use InvalidArgumentException;
use RuntimeException;

class TeamService {
    /** @var TeamRepositoryInterface */
    private $teams;

    /** @var TeamMemberRepositoryInterface|null */
    private $members;

    public function __construct(TeamRepositoryInterface $teams, TeamMemberRepositoryInterface $members = null) {
        $this->teams = $teams;
        $this->members = $members;
    }

    /**
     * @return array<int, object>
     */
    public function listTeamsForEvent(int $eventId): array {
        $this->guardPositiveId($eventId, 'event id');
        return $this->teams->findByEventId($eventId);
    }

    public function countTeamsForEvent(int $eventId): int {
        $this->guardPositiveId($eventId, 'event id');
        return $this->teams->countByEventId($eventId);
    }

    public function countRegisteredTeamsForEvent(int $eventId): int {
        $this->guardPositiveId($eventId, 'event id');
        return $this->teams->countRegisteredByEventId($eventId);
    }

    /**
     * @return object|null
     */
    public function getTeam(int $id) {
        $this->guardPositiveId($id, 'team id');
        return $this->teams->findById($id);
    }

    /**
     * @return array<int, object>
     */
    public function listMembersForTeam(int $teamId): array {
        $this->guardPositiveId($teamId, 'team id');
        if ($this->members === null) {
            return [];
        }

        return $this->members->findByTeamId($teamId);
    }

    /**
     * @param array<int, string> $members
     * @return object|null
     */
    public function updateTeam(int $teamId, string $name, string $contactName, string $contactEmail, string $contactPhone, string $status, array $members = []) {
        $this->guardPositiveId($teamId, 'team id');

        $cleanName = trim($name);
        if ($cleanName === '') {
            throw new InvalidArgumentException('team_name is verplicht.');
        }

        $cleanContactName = trim($contactName);
        if ($cleanContactName === '') {
            throw new InvalidArgumentException('contact_name is verplicht.');
        }

        $cleanEmail = strtolower(trim($contactEmail));
        if (!filter_var($cleanEmail, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('contact_email is ongeldig.');
        }

        $cleanPhone = trim($contactPhone);
        if ($cleanPhone === '') {
            throw new InvalidArgumentException('contact_phone is verplicht.');
        }

        $normalizedStatus = trim($status) !== '' ? trim($status) : 'ingeschreven';
        $normalizedMembers = $this->normalizeMembers($members);

        $updated = $this->teams->updateById($teamId, [
            'name' => $cleanName,
            'contact_name' => $cleanContactName,
            'contact_email' => $cleanEmail,
            'contact_phone' => $cleanPhone,
            'status' => $normalizedStatus,
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);

        if ($updated === null) {
            throw new RuntimeException('Team kon niet worden bijgewerkt.');
        }

        if ($this->members !== null) {
            $this->members->replaceForTeam($teamId, $normalizedMembers);
        }

        return $updated;
    }

    /**
     * @param array<int, string> $members
     * @return array<int, string>
     */
    private function normalizeMembers(array $members): array {
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

    private function guardPositiveId(int $id, string $label): void {
        if ($id <= 0) {
            throw new InvalidArgumentException(sprintf('%s must be a positive integer.', $label));
        }
    }
}

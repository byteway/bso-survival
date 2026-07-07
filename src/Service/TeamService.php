<?php

namespace BSO\Survival\Service;

use BSO\Survival\Database\Repository\TeamRepositoryInterface;
use InvalidArgumentException;

class TeamService {
    /** @var TeamRepositoryInterface */
    private $teams;

    public function __construct(TeamRepositoryInterface $teams) {
        $this->teams = $teams;
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

    /**
     * @return object|null
     */
    public function getTeam(int $id) {
        $this->guardPositiveId($id, 'team id');
        return $this->teams->findById($id);
    }

    private function guardPositiveId(int $id, string $label): void {
        if ($id <= 0) {
            throw new InvalidArgumentException(sprintf('%s must be a positive integer.', $label));
        }
    }
}

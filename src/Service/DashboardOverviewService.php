<?php

namespace BSO\Survival\Service;

use InvalidArgumentException;

class DashboardOverviewService {
    /** @var EventService */
    private $events;

    /** @var PartService */
    private $parts;

    /** @var TeamService */
    private $teams;

    public function __construct(EventService $events, PartService $parts, TeamService $teams) {
        $this->events = $events;
        $this->parts = $parts;
        $this->teams = $teams;
    }

    /**
     * Build a dashboard-ready overview for one event.
     *
     * @return array<string, mixed>
     */
    public function getOverviewForEvent(int $eventId): array {
        $this->guardPositiveId($eventId, 'event id');

        $event = $this->events->getEvent($eventId);
        if ($event === null) {
            throw new InvalidArgumentException(sprintf('Event %d not found.', $eventId));
        }

        $parts = $this->parts->listPartsForEvent($eventId);
        $teams = $this->teams->listTeamsForEvent($eventId);
        $partsCount = $this->parts->countPartsForEvent($eventId);
        $teamsCount = $this->teams->countTeamsForEvent($eventId);

        return [
            'event' => $event,
            'parts' => $parts,
            'teams' => $teams,
            'counts' => [
                'parts' => $partsCount,
                'teams' => $teamsCount,
            ],
            'status' => [
                'event_status' => $event->status ?? null,
                'has_parts' => $partsCount > 0,
                'has_teams' => $teamsCount > 0,
                'is_ready_for_planning' => $partsCount > 0 && $teamsCount > 0,
            ],
        ];
    }

    private function guardPositiveId(int $id, string $label): void {
        if ($id <= 0) {
            throw new InvalidArgumentException(sprintf('%s must be a positive integer.', $label));
        }
    }
}

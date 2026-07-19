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

    /** @var EventPublicationService|null */
    private $publications;

    public function __construct(EventService $events, PartService $parts, TeamService $teams, EventPublicationService $publications = null) {
        $this->events = $events;
        $this->parts = $parts;
        $this->teams = $teams;
        $this->publications = $publications;
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
        $registeredTeamsCount = $this->teams->countRegisteredTeamsForEvent($eventId);
        $eventStatus = (string) ($event->status ?? '');
        $maxTeams = $this->extractMaxTeams((string) ($event->meta_data ?? ''));
        $isReadOnly = in_array($eventStatus, ['afgesloten', 'gepubliceerd'], true);
        $isPublished = $eventStatus === 'gepubliceerd';
        $publication = null;

        if ($this->publications !== null) {
            $publication = $this->publications->getForEvent($eventId);
        }

        return [
            'event' => $event,
            'parts' => $parts,
            'teams' => $teams,
            'publication' => $publication,
            'counts' => [
                'parts' => $partsCount,
                'teams' => $teamsCount,
                'registered_teams' => $registeredTeamsCount,
                'max_teams' => $maxTeams,
                'published_final_standings' => is_array($publication['final_standings'] ?? null)
                    ? count($publication['final_standings'])
                    : 0,
            ],
            'status' => [
                'event_status' => $eventStatus,
                'has_parts' => $partsCount > 0,
                'has_teams' => $teamsCount > 0,
                'is_ready_for_planning' => $partsCount > 0 && $teamsCount > 0,
                'is_read_only' => $isReadOnly,
                'is_published' => $isPublished,
                'is_registration_full' => $maxTeams > 0 && $registeredTeamsCount >= $maxTeams,
                'has_published_results' => is_array($publication['final_standings'] ?? null) && $publication['final_standings'] !== [],
            ],
        ];
    }

    /**
     * @return array<int, object>
     */
    public function listUpcomingActiveEvents(int $limit = 5): array {
        $limit = max(1, $limit);
        $today = gmdate('Y-m-d');

        $events = array_values(array_filter($this->events->listActiveEvents(), static function ($event) use ($today): bool {
            if (!is_object($event)) {
                return false;
            }

            $eventDate = trim((string) ($event->event_date ?? ''));
            if ($eventDate === '') {
                return false;
            }

            return strcmp($eventDate, $today) >= 0;
        }));

        usort($events, static function ($left, $right): int {
            $leftDate = (string) ($left->event_date ?? '');
            $rightDate = (string) ($right->event_date ?? '');
            $dateCompare = strcmp($leftDate, $rightDate);
            if ($dateCompare !== 0) {
                return $dateCompare;
            }

            return ((int) ($left->id ?? 0)) <=> ((int) ($right->id ?? 0));
        });

        return array_slice($events, 0, $limit);
    }

    public function resolveDefaultDashboardEventId(): int {
        $upcoming = $this->listUpcomingActiveEvents(1);
        if ($upcoming !== []) {
            return (int) ($upcoming[0]->id ?? 0);
        }

        return 0;
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

    private function guardPositiveId(int $id, string $label): void {
        if ($id <= 0) {
            throw new InvalidArgumentException(sprintf('%s must be a positive integer.', $label));
        }
    }
}

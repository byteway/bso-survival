<?php

namespace BSO\Survival\Service;

use BSO\Survival\Database\Repository\EventAdminRepositoryInterface;
use BSO\Survival\Database\Repository\EventPublicationRepositoryInterface;
use BSO\Survival\Database\Repository\EventRepositoryInterface;
use BSO\Survival\Database\Repository\PartAdminRepositoryInterface;
use InvalidArgumentException;
use RuntimeException;

class EventAdminService {
    /** @var EventRepositoryInterface */
    private $events;

    /** @var EventAdminRepositoryInterface */
    private $eventAdmin;

    /** @var PartAdminRepositoryInterface */
    private $parts;

    /** @var EventPublicationRepositoryInterface */
    private $publications;

    public function __construct(
        EventRepositoryInterface $events,
        EventAdminRepositoryInterface $eventAdmin,
        PartAdminRepositoryInterface $parts,
        EventPublicationRepositoryInterface $publications
    ) {
        $this->events = $events;
        $this->eventAdmin = $eventAdmin;
        $this->parts = $parts;
        $this->publications = $publications;
    }

    /** @return object */
    public function createEvent(string $name, string $eventDate, int $maxTeams = 22) {
        $cleanName = trim($name);
        if ($cleanName === '') {
            throw new InvalidArgumentException('event_name is verplicht.');
        }

        $cleanDate = trim($eventDate);
        if (!$this->isValidDate($cleanDate)) {
            throw new InvalidArgumentException('event_date moet YYYY-MM-DD zijn.');
        }

        if ($maxTeams <= 0) {
            throw new InvalidArgumentException('max_teams moet groter zijn dan 0.');
        }

        $now = gmdate('Y-m-d H:i:s');
        $metaData = json_encode(['max_teams' => $maxTeams], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($metaData === false) {
            throw new RuntimeException('meta_data kon niet worden opgebouwd.');
        }

        $created = $this->eventAdmin->create([
            'name' => $cleanName,
            'event_date' => $cleanDate,
            'status' => 'concept',
            'meta_data' => $metaData,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($created === null) {
            throw new RuntimeException('Event kon niet worden aangemaakt.');
        }

        return $created;
    }

    /** @return object */
    public function updateEvent(int $eventId, string $name, string $eventDate, int $maxTeams = 22) {
        $event = $this->requireEditableEvent($eventId);

        $cleanName = trim($name);
        if ($cleanName === '') {
            throw new InvalidArgumentException('event_name is verplicht.');
        }

        $cleanDate = trim($eventDate);
        if (!$this->isValidDate($cleanDate)) {
            throw new InvalidArgumentException('event_date moet YYYY-MM-DD zijn.');
        }

        if ($maxTeams <= 0) {
            throw new InvalidArgumentException('max_teams moet groter zijn dan 0.');
        }

        $metaData = json_encode(['max_teams' => $maxTeams], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($metaData === false) {
            throw new RuntimeException('meta_data kon niet worden opgebouwd.');
        }

        $updated = $this->eventAdmin->updateById((int) ($event->id ?? $eventId), [
            'name' => $cleanName,
            'event_date' => $cleanDate,
            'meta_data' => $metaData,
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);

        if ($updated === null) {
            throw new RuntimeException('Event kon niet worden bijgewerkt.');
        }

        return $updated;
    }

    /**
     * @return array{event_id: int, linked_count: int, unlinked_count: int, linked_ids: array<int, int>, unlinked_ids: array<int, int>}
     */
    public function linkPartsToEvent(int $eventId, array $partIds): array {
        $event = $this->requireEditableEvent($eventId);

        $normalizedIds = array_values(array_unique(array_filter(array_map('intval', $partIds), static function (int $id): bool {
            return $id > 0;
        })));

        $selectedParts = $this->parts->findByIds($normalizedIds);
        if (count($selectedParts) !== count($normalizedIds)) {
            throw new InvalidArgumentException('Een of meer gekozen parts bestaan niet.');
        }

        $this->guardUniquePartNamesForEvent($selectedParts);

        foreach ($selectedParts as $part) {
            $sourceEventId = isset($part->event_id) ? (int) $part->event_id : 0;
            if ($sourceEventId <= 0 || $sourceEventId === $eventId) {
                continue;
            }

            $sourceEvent = $this->events->findById($sourceEventId);
            if ($sourceEvent === null) {
                continue;
            }

            $sourceStatus = (string) ($sourceEvent->status ?? '');
            if (!$this->isClosedLikeStatus($sourceStatus)) {
                throw new RuntimeException(sprintf('Part "%s" hangt nog aan actief event #%d.', (string) ($part->name ?? ''), $sourceEventId));
            }
        }

        $currentParts = $this->parts->findByEventId($eventId);
        $currentIds = array_values(array_unique(array_map(static function ($part): int {
            return (int) ($part->id ?? 0);
        }, $currentParts)));

        $toUnlink = array_values(array_diff($currentIds, $normalizedIds));
        $toLink = $normalizedIds;

        foreach ($toUnlink as $partId) {
            if (!$this->parts->assignToEvent($partId, null)) {
                throw new RuntimeException(sprintf('Kon part #%d niet ontkoppelen.', $partId));
            }
        }

        foreach ($toLink as $partId) {
            if (!$this->parts->assignToEvent($partId, $eventId)) {
                throw new RuntimeException(sprintf('Kon part #%d niet koppelen.', $partId));
            }
        }

        return [
            'event_id' => (int) ($event->id ?? $eventId),
            'linked_count' => count($toLink),
            'unlinked_count' => count($toUnlink),
            'linked_ids' => $toLink,
            'unlinked_ids' => $toUnlink,
        ];
    }

    /**
     * @param array<int, object> $parts
     */
    private function guardUniquePartNamesForEvent(array $parts): void {
        $seen = [];
        $duplicates = [];

        foreach ($parts as $part) {
            $name = trim((string) ($part->name ?? ''));
            if ($name === '') {
                continue;
            }

            $key = function_exists('mb_strtolower') ? mb_strtolower($name) : strtolower($name);
            if (isset($seen[$key])) {
                $duplicates[$key] = $name;
                continue;
            }

            $seen[$key] = true;
        }

        if ($duplicates !== []) {
            throw new RuntimeException('Een event mag geen dubbele partnamen bevatten. Conflicterende partnaam/namen: ' . implode(', ', array_values($duplicates)) . '.');
        }
    }

    /**
     * @return array{event_id: int, status: string, detached_parts: int, summary_retained: bool}
     */
    public function deleteEventFromAdmin(int $eventId): array {
        if ($eventId <= 0) {
            throw new InvalidArgumentException('event_id moet positief zijn.');
        }

        $event = $this->events->findById($eventId);
        if ($event === null) {
            throw new InvalidArgumentException(sprintf('Event %d niet gevonden.', $eventId));
        }

        $status = (string) ($event->status ?? '');
        if ($status === 'verwijderd') {
            throw new RuntimeException('Event is al verwijderd.');
        }

        if ($this->isClosedLikeStatus($status)) {
            $publication = $this->publications->findByEventId($eventId);
            if ($publication === null) {
                throw new RuntimeException('Gesloten events kunnen alleen verwijderd worden als er een samenvatting/publicatie bestaat.');
            }
        }

        $parts = $this->parts->findByEventId($eventId);
        foreach ($parts as $part) {
            $partId = (int) ($part->id ?? 0);
            if ($partId <= 0) {
                continue;
            }

            if (!$this->parts->assignToEvent($partId, null)) {
                throw new RuntimeException(sprintf('Kon part #%d niet loskoppelen tijdens verwijderen.', $partId));
            }
        }

        if (!$this->eventAdmin->markDeleted($eventId)) {
            throw new RuntimeException('Event kon niet als verwijderd gemarkeerd worden.');
        }

        return [
            'event_id' => $eventId,
            'status' => 'verwijderd',
            'detached_parts' => count($parts),
            'summary_retained' => $this->publications->findByEventId($eventId) !== null,
        ];
    }

    /**
     * @return array<int, object>
     */
    public function listLinkableParts(): array {
        return array_values(array_filter($this->parts->findAll(), static function ($part): bool {
            return (string) ($part->status ?? '') !== 'verwijderd';
        }));
    }

    /**
     * @return array<int, object>
     */
    public function listEligiblePartsForEvent(int $eventId, string $search = ''): array {
        $event = $this->events->findById($eventId);
        if ($event === null) {
            throw new InvalidArgumentException(sprintf('Event %d niet gevonden.', $eventId));
        }

        $attachedParts = $this->parts->findByEventId($eventId);
        $attachedById = [];
        $attachedNames = [];
        foreach ($attachedParts as $part) {
            $partId = (int) ($part->id ?? 0);
            if ($partId > 0) {
                $attachedById[$partId] = true;
            }

            $name = trim((string) ($part->name ?? ''));
            if ($name !== '') {
                $attachedNames[$this->normalizePartName($name)] = true;
            }
        }

        $query = $this->normalizePartName(trim($search));
        $eligible = [];
        foreach ($this->listLinkableParts() as $part) {
            $partId = (int) ($part->id ?? 0);
            $ownerEventId = isset($part->event_id) ? (int) $part->event_id : 0;
            $isAttachedToSelected = isset($attachedById[$partId]);

            if (!$isAttachedToSelected && $ownerEventId > 0) {
                $ownerEvent = $this->events->findById($ownerEventId);
                $ownerStatus = $ownerEvent !== null ? (string) ($ownerEvent->status ?? '') : '';
                if (!$this->isClosedLikeStatus($ownerStatus) && $ownerStatus !== 'verwijderd') {
                    continue;
                }
            }

            $name = trim((string) ($part->name ?? ''));
            $nameKey = $this->normalizePartName($name);
            if (!$isAttachedToSelected && $nameKey !== '' && isset($attachedNames[$nameKey])) {
                continue;
            }

            if ($query !== '' && strpos($nameKey, $query) === false) {
                continue;
            }

            $eligible[] = $part;
        }

        usort($eligible, static function ($left, $right): int {
            $nameCompare = strcmp((string) ($left->name ?? ''), (string) ($right->name ?? ''));
            if ($nameCompare !== 0) {
                return $nameCompare;
            }

            return ((int) ($left->id ?? 0)) <=> ((int) ($right->id ?? 0));
        });

        return $eligible;
    }

    /** @return object */
    private function requireEditableEvent(int $eventId) {
        if ($eventId <= 0) {
            throw new InvalidArgumentException('event_id moet positief zijn.');
        }

        $event = $this->events->findById($eventId);
        if ($event === null) {
            throw new InvalidArgumentException(sprintf('Event %d niet gevonden.', $eventId));
        }

        $status = (string) ($event->status ?? '');
        if ($this->isClosedLikeStatus($status) || $status === 'verwijderd') {
            throw new RuntimeException('Gesloten of verwijderde events kunnen niet meer worden aangepast.');
        }

        return $event;
    }

    private function isClosedLikeStatus(string $status): bool {
        return in_array($status, ['afgesloten', 'gepubliceerd'], true);
    }

    private function normalizePartName(string $name): string {
        return function_exists('mb_strtolower') ? mb_strtolower($name) : strtolower($name);
    }

    private function isValidDate(string $value): bool {
        if ($value === '') {
            return false;
        }

        $parsed = date_create_from_format('Y-m-d', $value);
        if ($parsed === false) {
            return false;
        }

        return $parsed->format('Y-m-d') === $value;
    }
}

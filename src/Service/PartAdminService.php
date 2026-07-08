<?php

namespace BSO\Survival\Service;

use BSO\Survival\Database\Repository\EventRepositoryInterface;
use BSO\Survival\Database\Repository\PartAdminRepositoryInterface;
use InvalidArgumentException;
use RuntimeException;

class PartAdminService {
    private const SORTABLE_COLUMNS = ['id', 'name', 'status', 'event_id'];

    /** @var PartAdminRepositoryInterface */
    private $parts;

    /** @var EventRepositoryInterface */
    private $events;

    public function __construct(PartAdminRepositoryInterface $parts, EventRepositoryInterface $events) {
        $this->parts = $parts;
        $this->events = $events;
    }

    /** @return array<int, object> */
    public function listParts(): array {
        return array_values(array_filter($this->parts->findAll(), static function ($part): bool {
            return (string) ($part->status ?? '') !== 'verwijderd';
        }));
    }

    /**
     * @return array<int, object>
     */
    public function listPartsSorted(string $sortBy = 'name', string $sortDirection = 'asc'): array {
        return $this->listPartsFilteredSorted('', $sortBy, $sortDirection);
    }

    /**
     * @return array<int, object>
     */
    public function listPartsFilteredSorted(string $search = '', string $sortBy = 'name', string $sortDirection = 'asc'): array {
        $parts = $this->filterPartsBySearch($this->listParts(), $search);
        $column = $this->normalizeSortColumn($sortBy);
        $direction = $this->normalizeSortDirection($sortDirection);

        usort($parts, function ($left, $right) use ($column, $direction): int {
            $result = $this->comparePartsByColumn($left, $right, $column);
            if ($result === 0) {
                $result = $this->comparePartsByColumn($left, $right, 'id');
            }

            return $direction === 'desc' ? ($result * -1) : $result;
        });

        return $parts;
    }

    /** @return object|null */
    public function getPart(int $partId) {
        if ($partId <= 0) {
            return null;
        }

        $part = $this->parts->findById($partId);
        if ($part === null || (string) ($part->status ?? '') === 'verwijderd') {
            return null;
        }

        return $part;
    }

    /** @return object */
    public function createPart(array $payload) {
        $name = $this->sanitizeName((string) ($payload['name'] ?? ''));
        $this->guardUniqueName($name);

        $status = $this->sanitizeStatus((string) ($payload['status'] ?? 'actief'));
        $latitude = $this->sanitizeCoordinate($payload['latitude'] ?? null, 'latitude');
        $longitude = $this->sanitizeCoordinate($payload['longitude'] ?? null, 'longitude');
        $metaData = $this->sanitizeMetaData($payload['meta_data'] ?? null);
        $now = gmdate('Y-m-d H:i:s');

        $created = $this->parts->create([
            'event_id' => null,
            'name' => $name,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'status' => $status,
            'meta_data' => $metaData,
            'scheduling_constraints' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($created === null) {
            throw new RuntimeException('Onderdeel kon niet worden aangemaakt.');
        }

        return $created;
    }

    /** @return object */
    public function updatePart(int $partId, array $payload) {
        if ($partId <= 0) {
            throw new InvalidArgumentException('part_id moet positief zijn.');
        }

        $existing = $this->parts->findById($partId);
        if ($existing === null || (string) ($existing->status ?? '') === 'verwijderd') {
            throw new InvalidArgumentException(sprintf('Onderdeel %d niet gevonden.', $partId));
        }

        $name = $this->sanitizeName((string) ($payload['name'] ?? (string) ($existing->name ?? '')));
        $this->guardUniqueName($name, $partId);

        $status = $this->sanitizeStatus((string) ($payload['status'] ?? (string) ($existing->status ?? 'actief')));
        $latitude = $this->sanitizeCoordinate(array_key_exists('latitude', $payload) ? $payload['latitude'] : ($existing->latitude ?? null), 'latitude');
        $longitude = $this->sanitizeCoordinate(array_key_exists('longitude', $payload) ? $payload['longitude'] : ($existing->longitude ?? null), 'longitude');
        $metaData = $this->sanitizeMetaData(array_key_exists('meta_data', $payload) ? $payload['meta_data'] : ($existing->meta_data ?? null));

        $updated = $this->parts->updateById($partId, [
            'name' => $name,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'status' => $status,
            'meta_data' => $metaData,
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);

        if ($updated === null) {
            throw new RuntimeException('Onderdeel kon niet worden bijgewerkt.');
        }

        return $updated;
    }

    public function deletePart(int $partId): bool {
        if ($partId <= 0) {
            throw new InvalidArgumentException('part_id moet positief zijn.');
        }

        $existing = $this->parts->findById($partId);
        if ($existing === null || (string) ($existing->status ?? '') === 'verwijderd') {
            throw new InvalidArgumentException(sprintf('Onderdeel %d niet gevonden.', $partId));
        }

        $eventId = (int) ($existing->event_id ?? 0);
        if ($eventId > 0) {
            $event = $this->events->findById($eventId);
            $status = $event !== null ? (string) ($event->status ?? '') : '';
            $normalizedStatus = function_exists('mb_strtolower') ? mb_strtolower(trim($status)) : strtolower(trim($status));
            if (!in_array($normalizedStatus, ['afgesloten', 'gesloten', 'closed', 'gepubliceerd', 'verwijderd'], true)) {
                throw new RuntimeException('Onderdeel kan niet verwijderd worden zolang het nog aan een actief event gekoppeld is.');
            }
        }

        return $this->parts->markDeleted($partId);
    }

    /** @return array<int, object> */
    public function importParts(string $jsonPayload): array {
        $decoded = json_decode($jsonPayload, true);
        if (!is_array($decoded)) {
            throw new InvalidArgumentException('Importbestand moet geldige JSON-array zijn.');
        }

        $prepared = [];
        $seenNames = [];
        foreach ($decoded as $index => $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException(sprintf('Importrecord %d is ongeldig.', $index + 1));
            }

            $name = $this->sanitizeName((string) ($row['name'] ?? ''));
            $key = function_exists('mb_strtolower') ? mb_strtolower($name) : strtolower($name);
            if (isset($seenNames[$key])) {
                throw new InvalidArgumentException('Import bevat dubbele partnamen: ' . $name . '.');
            }
            $seenNames[$key] = true;
            $this->guardUniqueName($name);

            $prepared[] = [
                'name' => $name,
                'status' => (string) ($row['status'] ?? 'actief'),
                'latitude' => $row['latitude'] ?? null,
                'longitude' => $row['longitude'] ?? null,
                'meta_data' => $row['meta_data'] ?? null,
            ];
        }

        $created = [];
        foreach ($prepared as $payload) {
            $created[] = $this->createPart($payload);
        }

        return $created;
    }

    public function exportParts(): string {
        $payload = array_map(static function ($part): array {
            return [
                'name' => (string) ($part->name ?? ''),
                'status' => (string) ($part->status ?? 'actief'),
                'latitude' => $part->latitude ?? null,
                'longitude' => $part->longitude ?? null,
                'meta_data' => (string) ($part->meta_data ?? ''),
            ];
        }, $this->listParts());

        $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded)) {
            throw new RuntimeException('Export kon niet worden opgebouwd.');
        }

        return $encoded;
    }

    private function sanitizeName(string $name): string {
        $clean = trim($name);
        if ($clean === '') {
            throw new InvalidArgumentException('name is verplicht.');
        }

        return $clean;
    }

    private function sanitizeStatus(string $status): string {
        $clean = trim($status);
        if (!in_array($clean, ['actief', 'inactief'], true)) {
            return 'actief';
        }

        return $clean;
    }

    /** @param mixed $value */
    private function sanitizeCoordinate($value, string $field): ?float {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            throw new InvalidArgumentException(sprintf('%s moet numeriek zijn.', $field));
        }

        return (float) $value;
    }

    /** @param mixed $value */
    private function sanitizeMetaData($value): ?string {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (!is_array($decoded)) {
                throw new InvalidArgumentException('meta_data moet geldige JSON zijn.');
            }

            $encoded = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return is_string($encoded) ? $encoded : null;
        }

        if (!is_array($value)) {
            throw new InvalidArgumentException('meta_data moet een array of JSON-string zijn.');
        }

        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return is_string($encoded) ? $encoded : null;
    }

    private function guardUniqueName(string $name, int $ignorePartId = 0): void {
        $needle = function_exists('mb_strtolower') ? mb_strtolower($name) : strtolower($name);
        foreach ($this->listParts() as $part) {
            $partId = (int) ($part->id ?? 0);
            if ($ignorePartId > 0 && $partId === $ignorePartId) {
                continue;
            }

            $existingName = trim((string) ($part->name ?? ''));
            $existingNeedle = function_exists('mb_strtolower') ? mb_strtolower($existingName) : strtolower($existingName);
            if ($existingNeedle === $needle) {
                throw new InvalidArgumentException('Onderdeelnaam bestaat al: ' . $name . '.');
            }
        }
    }

    private function normalizeSortColumn(string $sortBy): string {
        $candidate = trim($sortBy);
        if (!in_array($candidate, self::SORTABLE_COLUMNS, true)) {
            return 'name';
        }

        return $candidate;
    }

    private function normalizeSortDirection(string $sortDirection): string {
        return strtolower(trim($sortDirection)) === 'desc' ? 'desc' : 'asc';
    }

    /**
     * @param array<int, object> $parts
     * @return array<int, object>
     */
    private function filterPartsBySearch(array $parts, string $search): array {
        $needle = trim($search);
        if ($needle === '') {
            return $parts;
        }

        $needle = function_exists('mb_strtolower') ? mb_strtolower($needle) : strtolower($needle);

        return array_values(array_filter($parts, static function ($part) use ($needle): bool {
            $name = (string) ($part->name ?? '');
            $status = (string) ($part->status ?? '');
            $eventId = (string) ((int) ($part->event_id ?? 0));
            $id = (string) ((int) ($part->id ?? 0));

            $haystack = [
                function_exists('mb_strtolower') ? mb_strtolower($name) : strtolower($name),
                function_exists('mb_strtolower') ? mb_strtolower($status) : strtolower($status),
                $eventId,
                $id,
            ];

            foreach ($haystack as $value) {
                if (strpos($value, $needle) !== false) {
                    return true;
                }
            }

            return false;
        }));
    }

    /** @param object $left @param object $right */
    private function comparePartsByColumn($left, $right, string $column): int {
        if ($column === 'id' || $column === 'event_id') {
            return ((int) ($left->{$column} ?? 0)) <=> ((int) ($right->{$column} ?? 0));
        }

        return strcmp(
            (string) ($left->{$column} ?? ''),
            (string) ($right->{$column} ?? '')
        );
    }
}

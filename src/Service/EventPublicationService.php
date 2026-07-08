<?php

namespace BSO\Survival\Service;

use BSO\Survival\Database\Repository\EventPublicationRepositoryInterface;
use InvalidArgumentException;

class EventPublicationService {
    /** @var EventPublicationRepositoryInterface */
    private $publications;

    public function __construct(EventPublicationRepositoryInterface $publications) {
        $this->publications = $publications;
    }

    /**
     * @param array<string, mixed> $publication
     * @return object|null
     */
    public function saveForEvent(int $eventId, array $publication, string $changedBy) {
        if ($eventId <= 0) {
            throw new InvalidArgumentException('event id must be a positive integer.');
        }

        if (trim($changedBy) === '') {
            throw new InvalidArgumentException('changed_by must not be empty.');
        }

        $now = gmdate('Y-m-d H:i:s');

        return $this->publications->upsertByEventId($eventId, [
            'headline' => (string) ($publication['headline'] ?? ''),
            'published_at' => (string) ($publication['published_at'] ?? ''),
            'top_3_json' => $this->encodeArray($publication['top_3'] ?? []),
            'final_standings_json' => $this->encodeArray($publication['final_standings'] ?? []),
            'raw_publication_json' => $this->encodeArray($publication),
            'changed_by' => $changedBy,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getForEvent(int $eventId): ?array {
        if ($eventId <= 0) {
            throw new InvalidArgumentException('event id must be a positive integer.');
        }

        $row = $this->publications->findByEventId($eventId);
        if ($row === null) {
            return null;
        }

        return [
            'headline' => (string) ($row->headline ?? ''),
            'published_at' => (string) ($row->published_at ?? ''),
            'top_3' => $this->decodeArray((string) ($row->top_3_json ?? '[]')),
            'final_standings' => $this->decodeArray((string) ($row->final_standings_json ?? '[]')),
            'changed_by' => (string) ($row->changed_by ?? ''),
        ];
    }

    /** @param mixed $value */
    private function encodeArray($value): string {
        $encoded = json_encode(is_array($value) ? $value : [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $encoded === false ? '[]' : $encoded;
    }

    /**
     * @return array<int|string, mixed>
     */
    private function decodeArray(string $json): array {
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }
}

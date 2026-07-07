<?php

namespace BSO\Survival\Service;

use BSO\Survival\Database\Repository\PartRepositoryInterface;
use InvalidArgumentException;

class PartService {
    /** @var PartRepositoryInterface */
    private $parts;

    public function __construct(PartRepositoryInterface $parts) {
        $this->parts = $parts;
    }

    /**
     * @return array<int, object>
     */
    public function listPartsForEvent(int $eventId): array {
        $this->guardPositiveId($eventId, 'event id');
        return $this->parts->findByEventId($eventId);
    }

    public function countPartsForEvent(int $eventId): int {
        $this->guardPositiveId($eventId, 'event id');
        return $this->parts->countByEventId($eventId);
    }

    /**
     * @return object|null
     */
    public function getPart(int $id) {
        $this->guardPositiveId($id, 'part id');
        return $this->parts->findById($id);
    }

    private function guardPositiveId(int $id, string $label): void {
        if ($id <= 0) {
            throw new InvalidArgumentException(sprintf('%s must be a positive integer.', $label));
        }
    }
}

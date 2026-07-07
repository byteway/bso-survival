<?php

namespace BSO\Survival\Service;

use BSO\Survival\Database\Repository\EventRepositoryInterface;
use InvalidArgumentException;

class EventService {
    /** @var EventRepositoryInterface */
    private $events;

    public function __construct(EventRepositoryInterface $events) {
        $this->events = $events;
    }

    /**
     * @return array<int, object>
     */
    public function listEvents(): array {
        return $this->events->findAll();
    }

    /**
     * @return array<int, object>
     */
    public function listActiveEvents(): array {
        return $this->events->findByStatus('actief');
    }

    /**
     * @return object|null
     */
    public function getEvent(int $id) {
        $this->guardPositiveId($id, 'event id');
        return $this->events->findById($id);
    }

    private function guardPositiveId(int $id, string $label): void {
        if ($id <= 0) {
            throw new InvalidArgumentException(sprintf('%s must be a positive integer.', $label));
        }
    }
}

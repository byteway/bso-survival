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

    public function updateStatus(int $id, string $status): bool {
        $this->guardPositiveId($id, 'event id');
        $this->guardNonEmptyStatus($status);

        $event = $this->events->findById($id);
        if ($event === null) {
            throw new InvalidArgumentException(sprintf('Event %d not found.', $id));
        }

        $previousStatus = $event->status ?? null;

        if (function_exists('do_action')) {
            do_action('bso_survival_before_event_status_change', $id, $previousStatus, $status, $event);
        }

        $updated = $this->events->updateStatus($id, $status);
        if (!$updated) {
            return false;
        }

        if (function_exists('do_action')) {
            do_action('bso_survival_event_status_changed', $id, $previousStatus, $status, $event);
        }

        return true;
    }

    private function guardPositiveId(int $id, string $label): void {
        if ($id <= 0) {
            throw new InvalidArgumentException(sprintf('%s must be a positive integer.', $label));
        }
    }

    private function guardNonEmptyStatus(string $status): void {
        if (trim($status) === '') {
            throw new InvalidArgumentException('status must not be empty.');
        }
    }
}

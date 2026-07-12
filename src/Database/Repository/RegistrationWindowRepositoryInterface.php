<?php

namespace BSO\Survival\Database\Repository;

interface RegistrationWindowRepositoryInterface {
    /** @return object|null */
    public function findOpenForEventAt(int $eventId, string $momentUtc);

    /** @return object|null */
    public function findByEventId(int $eventId);

    /** @return object|null */
    public function saveForEvent(int $eventId, string $opensAt, string $closesAt, string $status = 'open');
}

<?php

namespace BSO\Survival\Database\Repository;

interface RegistrationWindowRepositoryInterface {
    /** @return object|null */
    public function findOpenForEventAt(int $eventId, string $momentUtc);
}

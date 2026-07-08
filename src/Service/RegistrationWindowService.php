<?php

namespace BSO\Survival\Service;

use BSO\Survival\Database\Repository\RegistrationWindowRepositoryInterface;

class RegistrationWindowService {
    /** @var RegistrationWindowRepositoryInterface */
    private $windows;

    public function __construct(RegistrationWindowRepositoryInterface $windows) {
        $this->windows = $windows;
    }

    public function isOpenForEvent(int $eventId, string $momentUtc = ''): bool {
        if ($eventId <= 0) {
            return false;
        }

        $moment = $momentUtc !== '' ? $momentUtc : gmdate('Y-m-d H:i:s');
        return $this->windows->findOpenForEventAt($eventId, $moment) !== null;
    }
}

<?php

namespace BSO\Survival\Database\Repository;

interface EventPublicationRepositoryInterface {
    /** @return object|null */
    public function findByEventId(int $eventId);

    /**
     * @param array<string, mixed> $data
     * @return object|null
     */
    public function upsertByEventId(int $eventId, array $data);
}

<?php

namespace BSO\Survival\Database;

class Schema {
    /**
     * Base table definitions for v2.
     *
     * @return array<string, array<string, string>>
     */
    public static function tables(): array {
        return [
            'events' => [
                'id' => 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT',
                'name' => 'VARCHAR(191) NOT NULL',
                'meta_data' => 'LONGTEXT NULL',
                'created_at' => 'DATETIME NOT NULL',
                'updated_at' => 'DATETIME NOT NULL',
            ],
            'teams' => [
                'id' => 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT',
                'event_id' => 'BIGINT UNSIGNED NOT NULL',
                'name' => 'VARCHAR(191) NOT NULL',
                'meta_data' => 'LONGTEXT NULL',
                'created_at' => 'DATETIME NOT NULL',
                'updated_at' => 'DATETIME NOT NULL',
            ],
        ];
    }
}

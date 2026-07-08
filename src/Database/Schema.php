<?php

namespace BSO\Survival\Database;

class Schema {
    /**
     * Complete table definitions for v2.
     *
     * The structure is intentionally migration-friendly:
     * - columns: raw SQL type declarations per column
     * - primary_key: list of PK columns
     * - unique_keys: list of unique key column sets
     * - indexes: list of non-unique index column sets
     * - foreign_keys: FK metadata for migration builders
     *
     * @return array<string, array<string, mixed>>
     */
    public static function tables(): array {
        return [
            'events' => [
                'columns' => [
                    'id' => 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT',
                    'name' => 'VARCHAR(191) NOT NULL',
                    'event_date' => 'DATE NOT NULL',
                    'status' => "VARCHAR(32) NOT NULL DEFAULT 'concept'",
                    'meta_data' => "LONGTEXT NULL DEFAULT NULL",
                    'created_at' => 'DATETIME NOT NULL',
                    'updated_at' => 'DATETIME NOT NULL',
                ],
                'primary_key' => ['id'],
                'indexes' => [
                    ['event_date'],
                    ['status'],
                ],
            ],

            'registration_windows' => [
                'columns' => [
                    'id' => 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT',
                    'event_id' => 'BIGINT UNSIGNED NOT NULL',
                    'opens_at' => 'DATETIME NOT NULL',
                    'closes_at' => 'DATETIME NOT NULL',
                    'status' => "VARCHAR(32) NOT NULL DEFAULT 'open'",
                    'created_at' => 'DATETIME NOT NULL',
                    'updated_at' => 'DATETIME NOT NULL',
                ],
                'primary_key' => ['id'],
                'indexes' => [
                    ['event_id'],
                    ['status'],
                ],
                'foreign_keys' => [
                    [
                        'column' => 'event_id',
                        'references' => 'events.id',
                        'on_delete' => 'CASCADE',
                    ],
                ],
            ],

            'timeslots' => [
                'columns' => [
                    'id' => 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT',
                    'event_id' => 'BIGINT UNSIGNED NOT NULL',
                    'start_at' => 'DATETIME NOT NULL',
                    'end_at' => 'DATETIME NOT NULL',
                    'transfer_minutes' => 'SMALLINT UNSIGNED NOT NULL DEFAULT 5',
                    'status' => "VARCHAR(32) NOT NULL DEFAULT 'planned'",
                    'created_at' => 'DATETIME NOT NULL',
                    'updated_at' => 'DATETIME NOT NULL',
                ],
                'primary_key' => ['id'],
                'indexes' => [
                    ['event_id'],
                    ['start_at'],
                    ['status'],
                ],
                'foreign_keys' => [
                    [
                        'column' => 'event_id',
                        'references' => 'events.id',
                        'on_delete' => 'CASCADE',
                    ],
                ],
            ],

            'teams' => [
                'columns' => [
                    'id' => 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT',
                    'event_id' => 'BIGINT UNSIGNED NOT NULL',
                    'name' => 'VARCHAR(191) NOT NULL',
                    'contact_name' => 'VARCHAR(191) NOT NULL',
                    'contact_phone' => 'VARCHAR(64) NOT NULL',
                    'contact_email' => 'VARCHAR(191) NOT NULL',
                    'status' => "VARCHAR(32) NOT NULL DEFAULT 'ingeschreven'",
                    'meta_data' => "LONGTEXT NULL DEFAULT NULL",
                    'created_at' => 'DATETIME NOT NULL',
                    'updated_at' => 'DATETIME NOT NULL',
                ],
                'primary_key' => ['id'],
                'unique_keys' => [
                    ['event_id', 'name'],
                ],
                'indexes' => [
                    ['event_id'],
                    ['status'],
                    ['contact_email'],
                ],
                'foreign_keys' => [
                    [
                        'column' => 'event_id',
                        'references' => 'events.id',
                        'on_delete' => 'CASCADE',
                    ],
                ],
            ],

            'team_members' => [
                'columns' => [
                    'id' => 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT',
                    'team_id' => 'BIGINT UNSIGNED NOT NULL',
                    'name' => 'VARCHAR(191) NOT NULL',
                    'created_at' => 'DATETIME NOT NULL',
                    'updated_at' => 'DATETIME NOT NULL',
                ],
                'primary_key' => ['id'],
                'indexes' => [
                    ['team_id'],
                ],
                'foreign_keys' => [
                    [
                        'column' => 'team_id',
                        'references' => 'teams.id',
                        'on_delete' => 'CASCADE',
                    ],
                ],
            ],

            'parts' => [
                'columns' => [
                    'id' => 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT',
                    'event_id' => 'BIGINT UNSIGNED NULL DEFAULT NULL',
                    'name' => 'VARCHAR(191) NOT NULL',
                    'latitude' => 'DECIMAL(10,8) NULL DEFAULT NULL',
                    'longitude' => 'DECIMAL(11,8) NULL DEFAULT NULL',
                    'status' => "VARCHAR(32) NOT NULL DEFAULT 'actief'",
                    'meta_data' => "LONGTEXT NULL DEFAULT NULL",
                    'scheduling_constraints' => "LONGTEXT NULL DEFAULT NULL",
                    'created_at' => 'DATETIME NOT NULL',
                    'updated_at' => 'DATETIME NOT NULL',
                ],
                'primary_key' => ['id'],
                'unique_keys' => [
                    ['event_id', 'name'],
                ],
                'indexes' => [
                    ['event_id'],
                    ['status'],
                ],
                'foreign_keys' => [
                    [
                        'column' => 'event_id',
                        'references' => 'events.id',
                        'on_delete' => 'SET NULL',
                    ],
                ],
            ],

            'part_rules' => [
                'columns' => [
                    'id' => 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT',
                    'part_id' => 'BIGINT UNSIGNED NOT NULL',
                    'scoring_mode' => "VARCHAR(32) NOT NULL DEFAULT 'points'",
                    'unit' => 'VARCHAR(64) NOT NULL',
                    'tiebreaker_mode' => "VARCHAR(32) NOT NULL DEFAULT 'manual_referee'",
                    'scoring_config' => "LONGTEXT NULL DEFAULT NULL",
                    'created_at' => 'DATETIME NOT NULL',
                    'updated_at' => 'DATETIME NOT NULL',
                ],
                'primary_key' => ['id'],
                'unique_keys' => [
                    ['part_id'],
                ],
                'indexes' => [
                    ['scoring_mode'],
                ],
                'foreign_keys' => [
                    [
                        'column' => 'part_id',
                        'references' => 'parts.id',
                        'on_delete' => 'CASCADE',
                    ],
                ],
            ],

            'part_help' => [
                'columns' => [
                    'id' => 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT',
                    'part_id' => 'BIGINT UNSIGNED NOT NULL',
                    'help_text' => 'LONGTEXT NOT NULL',
                    'image_urls' => "LONGTEXT NULL DEFAULT NULL",
                    'created_at' => 'DATETIME NOT NULL',
                    'updated_at' => 'DATETIME NOT NULL',
                ],
                'primary_key' => ['id'],
                'indexes' => [
                    ['part_id'],
                ],
                'foreign_keys' => [
                    [
                        'column' => 'part_id',
                        'references' => 'parts.id',
                        'on_delete' => 'CASCADE',
                    ],
                ],
            ],

            'staff_members' => [
                'columns' => [
                    'id' => 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT',
                    'event_id' => 'BIGINT UNSIGNED NOT NULL',
                    'name' => 'VARCHAR(191) NOT NULL',
                    'role' => 'VARCHAR(64) NOT NULL',
                    'created_at' => 'DATETIME NOT NULL',
                    'updated_at' => 'DATETIME NOT NULL',
                ],
                'primary_key' => ['id'],
                'indexes' => [
                    ['event_id'],
                    ['role'],
                ],
                'foreign_keys' => [
                    [
                        'column' => 'event_id',
                        'references' => 'events.id',
                        'on_delete' => 'CASCADE',
                    ],
                ],
            ],

            'assignments' => [
                'columns' => [
                    'id' => 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT',
                    'timeslot_id' => 'BIGINT UNSIGNED NOT NULL',
                    'part_id' => 'BIGINT UNSIGNED NOT NULL',
                    'team_id' => 'BIGINT UNSIGNED NOT NULL',
                    'referee_primary_id' => 'BIGINT UNSIGNED NULL DEFAULT NULL',
                    'referee_secondary_id' => 'BIGINT UNSIGNED NULL DEFAULT NULL',
                    'source' => "VARCHAR(32) NOT NULL DEFAULT 'planner'",
                    'status' => "VARCHAR(32) NOT NULL DEFAULT 'planned'",
                    'meta_data' => "LONGTEXT NULL DEFAULT NULL",
                    'created_at' => 'DATETIME NOT NULL',
                    'updated_at' => 'DATETIME NOT NULL',
                ],
                'primary_key' => ['id'],
                'unique_keys' => [
                    ['timeslot_id', 'part_id', 'team_id'],
                ],
                'indexes' => [
                    ['timeslot_id'],
                    ['part_id'],
                    ['team_id'],
                    ['status'],
                ],
                'foreign_keys' => [
                    [
                        'column' => 'timeslot_id',
                        'references' => 'timeslots.id',
                        'on_delete' => 'CASCADE',
                    ],
                    [
                        'column' => 'part_id',
                        'references' => 'parts.id',
                        'on_delete' => 'CASCADE',
                    ],
                    [
                        'column' => 'team_id',
                        'references' => 'teams.id',
                        'on_delete' => 'CASCADE',
                    ],
                    [
                        'column' => 'referee_primary_id',
                        'references' => 'staff_members.id',
                        'on_delete' => 'SET NULL',
                    ],
                    [
                        'column' => 'referee_secondary_id',
                        'references' => 'staff_members.id',
                        'on_delete' => 'SET NULL',
                    ],
                ],
            ],

            'score_entries' => [
                'columns' => [
                    'id' => 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT',
                    'assignment_id' => 'BIGINT UNSIGNED NOT NULL',
                    'raw_value' => 'DECIMAL(14,4) NOT NULL',
                    'normalized_points' => 'DECIMAL(14,4) NOT NULL',
                    'position' => 'INT UNSIGNED NULL DEFAULT NULL',
                    'rank_points' => 'INT UNSIGNED NULL DEFAULT NULL',
                    'joker_applied' => 'TINYINT(1) NOT NULL DEFAULT 0',
                    'entered_by_role' => 'VARCHAR(64) NOT NULL',
                    'entered_at' => 'DATETIME NOT NULL',
                    'status' => "VARCHAR(32) NOT NULL DEFAULT 'concept'",
                    'created_at' => 'DATETIME NOT NULL',
                    'updated_at' => 'DATETIME NOT NULL',
                ],
                'primary_key' => ['id'],
                'indexes' => [
                    ['assignment_id'],
                    ['status'],
                    ['entered_at'],
                ],
                'foreign_keys' => [
                    [
                        'column' => 'assignment_id',
                        'references' => 'assignments.id',
                        'on_delete' => 'CASCADE',
                    ],
                ],
            ],

            'joker_usages' => [
                'columns' => [
                    'id' => 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT',
                    'event_id' => 'BIGINT UNSIGNED NOT NULL',
                    'team_id' => 'BIGINT UNSIGNED NOT NULL',
                    'score_entry_id' => 'BIGINT UNSIGNED NOT NULL',
                    'used_at' => 'DATETIME NOT NULL',
                    'validated_by' => 'VARCHAR(191) NOT NULL',
                    'created_at' => 'DATETIME NOT NULL',
                    'updated_at' => 'DATETIME NOT NULL',
                ],
                'primary_key' => ['id'],
                'unique_keys' => [
                    ['event_id', 'team_id'],
                    ['event_id', 'team_id', 'score_entry_id'],
                ],
                'indexes' => [
                    ['event_id'],
                    ['team_id'],
                    ['score_entry_id'],
                ],
                'foreign_keys' => [
                    [
                        'column' => 'event_id',
                        'references' => 'events.id',
                        'on_delete' => 'CASCADE',
                    ],
                    [
                        'column' => 'team_id',
                        'references' => 'teams.id',
                        'on_delete' => 'CASCADE',
                    ],
                    [
                        'column' => 'score_entry_id',
                        'references' => 'score_entries.id',
                        'on_delete' => 'CASCADE',
                    ],
                ],
            ],

            'messages' => [
                'columns' => [
                    'id' => 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT',
                    'event_id' => 'BIGINT UNSIGNED NOT NULL',
                    'type' => 'VARCHAR(64) NOT NULL',
                    'text' => 'LONGTEXT NOT NULL',
                    'visibility' => "VARCHAR(32) NOT NULL DEFAULT 'intern'",
                    'status' => "VARCHAR(32) NOT NULL DEFAULT 'actief'",
                    'meta_data' => "LONGTEXT NULL DEFAULT NULL",
                    'visible_from' => 'DATETIME NULL DEFAULT NULL',
                    'visible_until' => 'DATETIME NULL DEFAULT NULL',
                    'created_at' => 'DATETIME NOT NULL',
                    'updated_at' => 'DATETIME NOT NULL',
                ],
                'primary_key' => ['id'],
                'indexes' => [
                    ['event_id'],
                    ['type'],
                    ['visibility'],
                    ['status'],
                ],
                'foreign_keys' => [
                    [
                        'column' => 'event_id',
                        'references' => 'events.id',
                        'on_delete' => 'CASCADE',
                    ],
                ],
            ],

            'certificates' => [
                'columns' => [
                    'id' => 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT',
                    'event_id' => 'BIGINT UNSIGNED NOT NULL',
                    'team_id' => 'BIGINT UNSIGNED NOT NULL',
                    'file_path' => 'VARCHAR(255) NOT NULL',
                    'generated_at' => 'DATETIME NOT NULL',
                    'delivery_status' => "VARCHAR(32) NOT NULL DEFAULT 'pending'",
                    'created_at' => 'DATETIME NOT NULL',
                    'updated_at' => 'DATETIME NOT NULL',
                ],
                'primary_key' => ['id'],
                'indexes' => [
                    ['event_id'],
                    ['team_id'],
                    ['delivery_status'],
                ],
                'foreign_keys' => [
                    [
                        'column' => 'event_id',
                        'references' => 'events.id',
                        'on_delete' => 'CASCADE',
                    ],
                    [
                        'column' => 'team_id',
                        'references' => 'teams.id',
                        'on_delete' => 'CASCADE',
                    ],
                ],
            ],

            'audit_logs' => [
                'columns' => [
                    'id' => 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT',
                    'event_id' => 'BIGINT UNSIGNED NULL DEFAULT NULL',
                    'entity_type' => 'VARCHAR(64) NOT NULL',
                    'entity_id' => 'BIGINT UNSIGNED NOT NULL',
                    'action' => 'VARCHAR(64) NOT NULL',
                    'old_value' => 'LONGTEXT NULL DEFAULT NULL',
                    'new_value' => 'LONGTEXT NULL DEFAULT NULL',
                    'changed_by' => 'VARCHAR(191) NOT NULL',
                    'created_at' => 'DATETIME NOT NULL',
                ],
                'primary_key' => ['id'],
                'indexes' => [
                    ['event_id'],
                    ['entity_type', 'entity_id'],
                    ['action'],
                    ['created_at'],
                ],
                'foreign_keys' => [
                    [
                        'column' => 'event_id',
                        'references' => 'events.id',
                        'on_delete' => 'SET NULL',
                    ],
                ],
            ],

            'email_templates' => [
                'columns' => [
                    'id' => 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT',
                    'template_key' => 'VARCHAR(128) NOT NULL',
                    'subject' => 'VARCHAR(255) NOT NULL',
                    'html_body' => 'LONGTEXT NOT NULL',
                    'is_active' => 'TINYINT(1) NOT NULL DEFAULT 1',
                    'updated_by' => 'VARCHAR(191) NOT NULL',
                    'created_at' => 'DATETIME NOT NULL',
                    'updated_at' => 'DATETIME NOT NULL',
                ],
                'primary_key' => ['id'],
                'unique_keys' => [
                    ['template_key'],
                ],
                'indexes' => [
                    ['is_active'],
                ],
            ],

            'email_outbox' => [
                'columns' => [
                    'id' => 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT',
                    'event_id' => 'BIGINT UNSIGNED NOT NULL',
                    'recipient' => 'VARCHAR(191) NOT NULL',
                    'template_key' => 'VARCHAR(128) NOT NULL',
                    'subject_snapshot' => 'VARCHAR(255) NOT NULL',
                    'body_snapshot' => 'LONGTEXT NOT NULL',
                    'status' => "VARCHAR(32) NOT NULL DEFAULT 'queued'",
                    'attempt_count' => 'INT UNSIGNED NOT NULL DEFAULT 0',
                    'next_attempt_at' => 'DATETIME NOT NULL',
                    'sent_at' => 'DATETIME NULL DEFAULT NULL',
                    'last_error' => 'LONGTEXT NULL DEFAULT NULL',
                    'dedupe_key' => 'VARCHAR(191) NOT NULL',
                    'created_at' => 'DATETIME NOT NULL',
                    'updated_at' => 'DATETIME NOT NULL',
                ],
                'primary_key' => ['id'],
                'unique_keys' => [
                    ['dedupe_key'],
                ],
                'indexes' => [
                    ['event_id'],
                    ['status'],
                    ['next_attempt_at'],
                ],
                'foreign_keys' => [
                    [
                        'column' => 'event_id',
                        'references' => 'events.id',
                        'on_delete' => 'CASCADE',
                    ],
                ],
            ],

            'event_publications' => [
                'columns' => [
                    'id' => 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT',
                    'event_id' => 'BIGINT UNSIGNED NOT NULL',
                    'headline' => 'VARCHAR(255) NOT NULL',
                    'published_at' => 'VARCHAR(64) NOT NULL',
                    'top_3_json' => 'LONGTEXT NOT NULL',
                    'final_standings_json' => 'LONGTEXT NOT NULL',
                    'raw_publication_json' => 'LONGTEXT NOT NULL',
                    'changed_by' => 'VARCHAR(191) NOT NULL',
                    'created_at' => 'DATETIME NOT NULL',
                    'updated_at' => 'DATETIME NOT NULL',
                ],
                'primary_key' => ['id'],
                'unique_keys' => [
                    ['event_id'],
                ],
                'indexes' => [
                    ['published_at'],
                ],
                'foreign_keys' => [
                    [
                        'column' => 'event_id',
                        'references' => 'events.id',
                        'on_delete' => 'CASCADE',
                    ],
                ],
            ],
        ];
    }
}

<?php

namespace BSO\Survival\Database;

class Migrator {
    public const SCHEMA_VERSION = '2.0.0';
    private const OPTION_SCHEMA_VERSION = 'bso_survival_schema_version';

    public static function migrate(): void {
        if (!function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        global $wpdb;

        if (!isset($wpdb) || !is_object($wpdb)) {
            return;
        }

        $charsetCollate = $wpdb->get_charset_collate();
        $tableDefinitions = Schema::tables();

        foreach ($tableDefinitions as $tableName => $definition) {
            $sql = self::buildCreateTableSql($wpdb->prefix . 'bso_survival_' . $tableName, $definition, $charsetCollate);
            dbDelta($sql);
        }

        self::applyForeignKeys($tableDefinitions);

        update_option(self::OPTION_SCHEMA_VERSION, self::SCHEMA_VERSION);
    }

    /**
     * @param array<string, mixed> $definition
     */
    private static function buildCreateTableSql(string $fullTableName, array $definition, string $charsetCollate): string {
        $columns = $definition['columns'] ?? [];
        $primaryKey = $definition['primary_key'] ?? [];
        $uniqueKeys = $definition['unique_keys'] ?? [];
        $indexes = $definition['indexes'] ?? [];

        $lines = [];
        foreach ($columns as $name => $sqlType) {
            $lines[] = "{$name} {$sqlType}";
        }

        if (!empty($primaryKey)) {
            $lines[] = 'PRIMARY KEY  (' . implode(', ', $primaryKey) . ')';
        }

        foreach ($uniqueKeys as $keyColumns) {
            $indexName = 'uniq_' . implode('_', $keyColumns);
            $lines[] = 'UNIQUE KEY ' . $indexName . ' (' . implode(', ', $keyColumns) . ')';
        }

        foreach ($indexes as $keyColumns) {
            $indexName = 'idx_' . implode('_', $keyColumns);
            $lines[] = 'KEY ' . $indexName . ' (' . implode(', ', $keyColumns) . ')';
        }

        return "CREATE TABLE {$fullTableName} (\n  " . implode(",\n  ", $lines) . "\n) {$charsetCollate};";
    }

    /**
     * @param array<string, array<string, mixed>> $tableDefinitions
     */
    private static function applyForeignKeys(array $tableDefinitions): void {
        global $wpdb;

        foreach ($tableDefinitions as $tableName => $definition) {
            $foreignKeys = $definition['foreign_keys'] ?? [];
            if (empty($foreignKeys)) {
                continue;
            }

            $fullTableName = $wpdb->prefix . 'bso_survival_' . $tableName;

            foreach ($foreignKeys as $fk) {
                $column = $fk['column'] ?? null;
                $references = $fk['references'] ?? null;
                $onDelete = $fk['on_delete'] ?? 'RESTRICT';

                if (!$column || !$references) {
                    continue;
                }

                [$refTable, $refColumn] = explode('.', $references, 2);
                $fullRefTable = $wpdb->prefix . 'bso_survival_' . $refTable;
                $constraintName = 'fk_' . $tableName . '_' . $column;

                // Drop first to keep activation idempotent on environments that support FKs.
                $wpdb->query("ALTER TABLE {$fullTableName} DROP FOREIGN KEY {$constraintName}");
                $wpdb->query(
                    "ALTER TABLE {$fullTableName} " .
                    "ADD CONSTRAINT {$constraintName} FOREIGN KEY ({$column}) " .
                    "REFERENCES {$fullRefTable}({$refColumn}) ON DELETE {$onDelete}"
                );
            }
        }
    }
}

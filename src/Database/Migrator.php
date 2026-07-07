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

        if (!isset($wpdb) || !is_object($wpdb)) {
            return;
        }

        $previousSuppressState = $wpdb->suppress_errors(true);

        try {
            foreach ($tableDefinitions as $tableName => $definition) {
                $foreignKeys = $definition['foreign_keys'] ?? [];
                if (empty($foreignKeys)) {
                    continue;
                }

                $fullTableName = $wpdb->prefix . 'bso_survival_' . $tableName;
                if (!self::tableExists($fullTableName)) {
                    continue;
                }

                foreach ($foreignKeys as $fk) {
                    $column = $fk['column'] ?? null;
                    $references = $fk['references'] ?? null;
                    $onDelete = $fk['on_delete'] ?? 'RESTRICT';

                    if (!$column || !$references || strpos($references, '.') === false) {
                        continue;
                    }

                    [$refTable, $refColumn] = explode('.', $references, 2);
                    $fullRefTable = $wpdb->prefix . 'bso_survival_' . $refTable;
                    if (!self::tableExists($fullRefTable)) {
                        continue;
                    }

                    $constraintName = 'fk_' . $tableName . '_' . $column;

                    if (self::foreignKeyExists($fullTableName, $constraintName)) {
                        $wpdb->query("ALTER TABLE `{$fullTableName}` DROP FOREIGN KEY `{$constraintName}`");
                    }

                    if (!self::foreignKeyExists($fullTableName, $constraintName)) {
                        $wpdb->query(
                            "ALTER TABLE `{$fullTableName}` " .
                            "ADD CONSTRAINT `{$constraintName}` FOREIGN KEY (`{$column}`) " .
                            "REFERENCES `{$fullRefTable}`(`{$refColumn}`) ON DELETE {$onDelete}"
                        );
                    }
                }
            }
        } finally {
            $wpdb->suppress_errors($previousSuppressState);
        }
    }

    private static function tableExists(string $tableName): bool {
        global $wpdb;

        $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $tableName));
        return $found === $tableName;
    }

    private static function foreignKeyExists(string $tableName, string $constraintName): bool {
        global $wpdb;

        $sql = "
            SELECT CONSTRAINT_NAME
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = DATABASE()
              AND TABLE_NAME = %s
              AND CONSTRAINT_NAME = %s
              AND CONSTRAINT_TYPE = 'FOREIGN KEY'
            LIMIT 1
        ";

        $found = $wpdb->get_var($wpdb->prepare($sql, $tableName, $constraintName));
        return !empty($found);
    }
}

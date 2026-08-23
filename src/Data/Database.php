<?php
/**
 * Slate — Database wrapper.
 *
 * Static-class PDO singleton. Every DB call in the shell and every
 * plugin goes through this. Prepared statements only — never
 * interpolate user input into SQL.
 */

declare(strict_types=1);

namespace Slate\Data;

// Phase 1 A3: migrated from includes/Database.php into Slate\Data.
// The global name `Database` is provided by a class_alias in
// src/compat/aliases.php. Behavior is identical to the pre-move class.

class Database {
    private static ?\PDO $pdo = null;

    public static function get(): \PDO {
        if (self::$pdo === null) {
            $sqlite = (getenv('SLATE_TEST_SQLITE') === '1');
            $dsn  = $sqlite ? 'sqlite:' . (getenv('SLATE_SQLITE_PATH') ?: sys_get_temp_dir() . '/slate-integration.sqlite') : 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
            $opts = [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            self::$pdo = new \PDO($dsn, DB_USER, DB_PASS, $opts);
            // MySQL-only isolation setting. SQLite is intentionally used only
            // by the isolated integration harness and does not support it.
            if (!$sqlite) self::$pdo->exec("SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED");
        }
        return self::$pdo;
    }

    /**
     * Execute a query with parameters. Returns the PDOStatement.
     */
    public static function query(string $sql, array $params = []): \PDOStatement {
        if (getenv('SLATE_TEST_SQLITE') === '1') {
            $sql = str_replace('INSERT IGNORE', 'INSERT OR IGNORE', $sql);
            $sql = str_replace('NOW()', 'CURRENT_TIMESTAMP', $sql);
            $sql = preg_replace('/\b(?:BIGINT|INT|SMALLINT)(?:\s+UNSIGNED)?\s+NOT\s+NULL\s+AUTO_INCREMENT/i', 'INTEGER PRIMARY KEY AUTOINCREMENT', $sql) ?? $sql;
            if (preg_match('/^\s*SHOW\s+TABLES\s*$/i', trim($sql))) {
                $sql = "SELECT name AS Tables_in_slate FROM sqlite_master WHERE type = 'table'";
            } elseif (preg_match('/^\s*SHOW\s+TABLES\s+LIKE\s+(.+)$/i', trim($sql), $show)) {
                $sql = "SELECT name FROM sqlite_master WHERE type = 'table' AND name LIKE " . $show[1];
            } elseif (preg_match('/^\s*SHOW\s+TABLES\s+LIKE\s+\?\s*$/i', trim($sql))) {
                $sql = "SELECT name FROM sqlite_master WHERE type = 'table' AND name LIKE ?";
            } elseif (preg_match('/^\s*SHOW\s+COLUMNS\s+FROM\s+`?([a-zA-Z0-9_]+)`?\s*$/i', trim($sql), $columns)) {
                $sql = "PRAGMA table_info(`" . $columns[1] . "`)";
                // The existing test reads MySQL's Field key; alias SQLite's name.
                $sql = "SELECT name AS Field, name FROM pragma_table_info('" . $columns[1] . "')";
            }
            $sql = preg_replace('/information_schema\.tables/i', 'sqlite_master', $sql) ?? $sql;
            $sql = preg_replace('/table_schema\s*=\s*DATABASE\(\)\s+AND\s+/i', '', $sql) ?? $sql;
            $sql = preg_replace('/\btable_name\b/i', 'name', $sql) ?? $sql;
            $sql = preg_replace('/,?\s+KEY\s+\w+\s*\([^)]*\)/i', '', $sql) ?? $sql;
            $sql = preg_replace('/,?\s+UNIQUE\s+KEY\s+\w+\s*\([^)]*\)/i', '', $sql) ?? $sql;
            $sql = preg_replace('/,?\s+CONSTRAINT\s+\w+.*?\bON\s+DELETE\s+\w+/i', '', $sql) ?? $sql;
            $sql = preg_replace('/\s+AUTO_INCREMENT\b/i', '', $sql) ?? $sql;
            $sql = preg_replace('/,\s*PRIMARY\s+KEY\s*\(\s*`?id`?\s*\)/i', '', $sql) ?? $sql;
            $sql = preg_replace('/,\s*\)/', ')', $sql) ?? $sql;
        }
        $stmt = self::get()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Fetch a single row or null.
     */
    public static function row(string $sql, array $params = []): ?array {
        $r = self::query($sql, $params)->fetch();
        return $r ?: null;
    }

    /**
     * Fetch all rows.
     */
    public static function rows(string $sql, array $params = []): array {
        return self::query($sql, $params)->fetchAll();
    }

    /**
     * Alias for rows(). Some callers prefer this name.
     */
    public static function all(string $sql, array $params = []): array {
        return self::rows($sql, $params);
    }

    /**
     * Fetch a single scalar value from the first column of the first row.
     */
    public static function value(string $sql, array $params = []) {
        $row = self::query($sql, $params)->fetch(\PDO::FETCH_NUM);
        return $row ? $row[0] : null;
    }

    /**
     * Insert a row, returning the new id.
     */
    public static function insert(string $table, array $data): int {
        $cols         = implode(', ', array_map(fn($k) => "`$k`", array_keys($data)));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        self::query("INSERT INTO `$table` ($cols) VALUES ($placeholders)", array_values($data));
        return (int) self::get()->lastInsertId();
    }

    /**
     * Update rows. Returns rows-affected count.
     */
    public static function update(string $table, array $data, string $where, array $whereParams = []): int {
        $set    = implode(', ', array_map(fn($k) => "`$k` = ?", array_keys($data)));
        $params = array_merge(array_values($data), $whereParams);
        $stmt   = self::query("UPDATE `$table` SET $set WHERE $where", $params);
        return $stmt->rowCount();
    }

    /**
     * Delete rows. Returns rows-affected count.
     */
    public static function delete(string $table, string $where, array $params = []): int {
        return self::query("DELETE FROM `$table` WHERE $where", $params)->rowCount();
    }

    /**
     * Read a tenant-scoped setting.
     */
    public static function setting(string $key, ?int $tenantId = null) {
        if ($tenantId === null) $tenantId = current_tenant_id();
        return self::value(
            "SELECT setting_value FROM settings WHERE tenant_id = ? AND setting_key = ?",
            [$tenantId, $key]
        );
    }

    /**
     * Write a tenant-scoped setting (upsert).
     */
    public static function setSetting(string $key, $value, ?int $tenantId = null): void {
        if ($tenantId === null) $tenantId = current_tenant_id();
        $sql = getenv('SLATE_TEST_SQLITE') === '1'
            ? "INSERT INTO settings (tenant_id, setting_key, setting_value) VALUES (?, ?, ?) ON CONFLICT(tenant_id, setting_key) DO UPDATE SET setting_value = excluded.setting_value"
            : "INSERT INTO settings (tenant_id, setting_key, setting_value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
        self::query($sql,
            [$tenantId, $key, $value]
        );
    }

    /** Test-only reset hook; production callers should never need this. */
    public static function reset(): void { self::$pdo = null; }
}

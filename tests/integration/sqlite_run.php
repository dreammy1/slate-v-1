<?php
/**
 * Isolated SQLite integration runner.
 * Usage: php tests/integration/sqlite_run.php
 * This never changes production DB constants or files.
 */
declare(strict_types=1);

$path = sys_get_temp_dir() . '/slate-integration-' . getmypid() . '.sqlite';
@unlink($path);
putenv('SLATE_TEST_SQLITE=1');
putenv('SLATE_SQLITE_PATH=' . $path);

require __DIR__ . '/../../config.php';
$pdo = Database::get();
$pdo->exec('PRAGMA foreign_keys = OFF');

$translate = static function (string $sql): string {
    $sql = preg_replace('/--.*$/m', '', $sql) ?? $sql;
    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql) ?? $sql;
    $sql = preg_replace('/\s+AUTO_INCREMENT\b/i', '', $sql) ?? $sql;
    $sql = preg_replace('/\b(?:INT|BIGINT|SMALLINT)(?:\s+UNSIGNED)?(?:\s+NOT\s+NULL)?(?:\s+AUTO_INCREMENT)?/i', 'INTEGER', $sql) ?? $sql;
    $sql = preg_replace('/\bTINYINT\(\d+\)/i', 'INTEGER', $sql) ?? $sql;
    $sql = preg_replace('/\b(?:VARCHAR|CHAR)\(\d+\)/i', 'TEXT', $sql) ?? $sql;
    $sql = preg_replace('/\b(?:LONGTEXT|MEDIUMTEXT|DATETIME|TIMESTAMP|JSON)\b/i', 'TEXT', $sql) ?? $sql;
    $sql = preg_replace('/\s+ON\s+UPDATE\s+CURRENT_TIMESTAMP/i', '', $sql) ?? $sql;
    $sql = preg_replace('/\bENUM\s*\([^)]*\)/i', 'TEXT', $sql) ?? $sql;
    $sql = preg_replace('/^\s*UNIQUE\s+KEY\s+`[^`]+`\s*(\([^)]*\))\s*,?\s*$/im', 'UNIQUE $1,', $sql) ?? $sql;
    $sql = preg_replace('/^\s*(?:KEY|INDEX)\s+`[^`]+`\s*\([^)]*\)\s*,?\s*$/im', '', $sql) ?? $sql;
    $sql = preg_replace('/^\s*CONSTRAINT\s+`[^`]+`.*$/im', '', $sql) ?? $sql;
    $sql = preg_replace('/\)\s*ENGINE=.*$/is', ')', $sql) ?? $sql;
    $sql = preg_replace('/\)\s*;?\s*$/s', ')', trim($sql)) ?? trim($sql);
    $sql = preg_replace('/,\s*\)/', ')', $sql) ?? $sql;
    return trim($sql);
};

$files = [SLATE_ROOT . '/db/schema.sql'];
foreach (glob(SLATE_ROOT . '/plugins/*/install.sql') ?: [] as $file) $files[] = $file;
foreach ($files as $file) {
    $raw = (string)file_get_contents($file);
    foreach (preg_split('/;\s*(?:\n|$)/', $raw) ?: [] as $statement) {
        $candidate = preg_replace('/--.*$/m', '', $statement) ?? $statement;
        if (!preg_match('/^\s*CREATE\s+TABLE/i', $candidate)) continue;
        $sql = $translate($candidate);
        try { $pdo->exec($sql); } catch (Throwable $e) {
            fwrite(STDERR, "SQLite schema skip {$file}: {$e->getMessage()}\n");
        }
    }
}

// The integration suite expects a normal tenant/admin context.
$pdo->exec("INSERT OR IGNORE INTO tenants (id, name, slug, status) VALUES (1, 'SQLite Test Tenant', 'sqlite-test', 'active')");
$pdo->exec("INSERT OR IGNORE INTO roles (id, tenant_id, name, slug, is_system) VALUES (1, 1, 'Super Admin', 'super-admin', 1)");
$pdo->exec("INSERT OR IGNORE INTO users (id, tenant_id, email, password_hash, name, role_id, status) VALUES (1, 1, 'sqlite@example.test', 'not-used', 'SQLite Test', 1, 'active')");

foreach (glob(SLATE_ROOT . '/plugins/*/plugin.json') ?: [] as $manifestFile) {
    $manifest = json_decode((string)file_get_contents($manifestFile), true) ?: [];
    $slug = basename(dirname($manifestFile));
    $name = (string)($manifest['name'] ?? $slug);
    $version = (string)($manifest['version'] ?? '1.0.0');
    $json = json_encode($manifest, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    $stmt = $pdo->prepare("INSERT OR IGNORE INTO plugins (slug, name, version, status, manifest_json) VALUES (?, ?, ?, 'active', ?)");
    $stmt->execute([$slug, $name, $version, $json]);
}
PluginLoader::boot();

// Reuse the canonical test list without re-requiring config.php. Requiring the
// canonical runner would redefine constants and duplicate bootstrap side effects.
require __DIR__ . '/../unit/harness.php';
echo "# Slate SQLite integration tests\n";
foreach (glob(__DIR__ . '/*Test.php') ?: [] as $file) {
    if (str_starts_with(basename($file), 'Studio') && !is_file(__DIR__ . '/../../plugins/studio/StudioAPI.php')) {
        echo '# SKIP optional Studio plugin is not present: ' . basename($file) . "\n";
        continue;
    }
    require $file;
}
$exit = unit_summary();
@unlink($path);
exit($exit);

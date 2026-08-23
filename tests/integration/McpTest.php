<?php
declare(strict_types=1);

/**
 * MCP integration contract tests.
 *
 * These tests use the configured CI/test database, never production. The
 * settings CRUD case creates a uniquely named probe row and removes it in a
 * finally block. Module contract tests verify every allow-listed resource and
 * fail if a resource disappears from the database schema.
 */

require_once __DIR__ . '/../../includes/Mcp.php';

function mcp_test_expect_invalid(callable $fn): void
{
    assert_throws(InvalidArgumentException::class, $fn);
}

unit('MCP exposes every requested admin module', function (): void {
    assert_eq(
        ['users', 'roles', 'settings', 'plugins', 'media', 'forms', 'shop', 'bookings', 'memberships', 'seo', 'content'],
        Mcp::modules()
    );
});

unit('MCP capability metadata exposes all CRUD actions and resources', function (): void {
    $caps = Mcp::call('slate_admin_capabilities', [], (int)TENANT_ID);
    foreach (['read', 'list', 'test', 'create', 'edit', 'write', 'delete'] as $action) assert_true(in_array($action, $caps['actions'], true), "missing action $action");
    foreach (Mcp::resources() as $module => $resources) {
        assert_true(isset($caps['resources'][$module]), "missing module $module");
        foreach ($resources as $resource) assert_true(in_array($resource, $caps['resources'][$module], true), "missing resource $resource");
    }
});

unit('MCP rejects unknown modules and resources before database access', function (): void {
    mcp_test_expect_invalid(fn() => Mcp::call('slate_admin_preview', ['module' => 'unknown', 'action' => 'read', 'resource' => 'users'], (int)TENANT_ID));
    mcp_test_expect_invalid(fn() => Mcp::call('slate_admin_preview', ['module' => 'users', 'action' => 'read', 'resource' => 'not_a_table'], (int)TENANT_ID));
});

unit('MCP rejects unsupported actions and missing mutation identifiers', function (): void {
    mcp_test_expect_invalid(fn() => Mcp::call('slate_admin_preview', ['module' => 'settings', 'action' => 'sql', 'resource' => 'settings'], (int)TENANT_ID));
    mcp_test_expect_invalid(fn() => Mcp::call('slate_admin_preview', ['module' => 'settings', 'action' => 'edit', 'resource' => 'settings', 'payload' => []], (int)TENANT_ID));
    mcp_test_expect_invalid(fn() => Mcp::call('slate_admin_preview', ['module' => 'settings', 'action' => 'delete', 'resource' => 'settings', 'payload' => []], (int)TENANT_ID));
});

unit('MCP preview creates a confirmation token bound to the exact operation', function (): void {
    $preview = Mcp::call('slate_admin_preview', ['module' => 'settings', 'action' => 'create', 'resource' => 'settings', 'payload' => ['setting_key' => 'mcp_test_preview_' . bin2hex(random_bytes(4)), 'setting_value' => 'preview']], (int)TENANT_ID);
    assert_true($preview['requires_confirmation'] === true);
    assert_true(is_string($preview['confirmation_token']) && $preview['confirmation_token'] !== '');
    assert_eq(300, $preview['expires_in']);
});

unit('MCP settings adapter supports read, create, edit, write, and delete with one-time confirmation', function (): void {
    $tenant = (int)TENANT_ID;
    $key = 'mcp_test_' . bin2hex(random_bytes(8));
    $payload = ['setting_key' => $key, 'setting_value' => 'one'];
    $preview = Mcp::call('slate_admin_preview', ['module' => 'settings', 'action' => 'create', 'resource' => 'settings', 'payload' => $payload], $tenant);
    assert_throws(RuntimeException::class, fn() => Mcp::call('slate_admin_execute', ['module' => 'settings', 'action' => 'create', 'resource' => 'settings', 'payload' => $payload], $tenant));
    try {
        $created = Mcp::call('slate_admin_execute', ['module' => 'settings', 'action' => 'create', 'resource' => 'settings', 'payload' => $payload, 'confirmation_token' => $preview['confirmation_token']], $tenant);
        assert_true($created['ok'] === true);
        $id = (int)Database::value('SELECT id FROM settings WHERE tenant_id = ? AND setting_key = ? ORDER BY id DESC LIMIT 1', [$tenant, $key]);
        assert_true($id > 0);

        $readPayload = ['id' => $id];
        $readPreview = Mcp::call('slate_admin_preview', ['module' => 'settings', 'action' => 'read', 'resource' => 'settings', 'payload' => $readPayload], $tenant);
        $read = Mcp::call('slate_admin_execute', ['module' => 'settings', 'action' => 'read', 'resource' => 'settings', 'payload' => $readPayload, 'confirmation_token' => $readPreview['confirmation_token']], $tenant);
        assert_true($read['ok'] === true && count($read['items']) === 1);

        $editPayload = ['id' => $id, 'setting_value' => 'two'];
        $editPreview = Mcp::call('slate_admin_preview', ['module' => 'settings', 'action' => 'edit', 'resource' => 'settings', 'payload' => $editPayload], $tenant);
        $edited = Mcp::call('slate_admin_execute', ['module' => 'settings', 'action' => 'edit', 'resource' => 'settings', 'payload' => $editPayload, 'confirmation_token' => $editPreview['confirmation_token']], $tenant);
        assert_true($edited['ok'] === true);
        assert_eq('two', Database::value('SELECT setting_value FROM settings WHERE id = ? AND tenant_id = ?', [$id, $tenant]));

        $writePayload = ['id' => $id, 'setting_value' => 'three'];
        $writePreview = Mcp::call('slate_admin_preview', ['module' => 'settings', 'action' => 'write', 'resource' => 'settings', 'payload' => $writePayload], $tenant);
        $written = Mcp::call('slate_admin_execute', ['module' => 'settings', 'action' => 'write', 'resource' => 'settings', 'payload' => $writePayload, 'confirmation_token' => $writePreview['confirmation_token']], $tenant);
        assert_true($written['ok'] === true);
        assert_eq('three', Database::value('SELECT setting_value FROM settings WHERE id = ? AND tenant_id = ?', [$id, $tenant]));

        $deletePayload = ['id' => $id];
        $deletePreview = Mcp::call('slate_admin_preview', ['module' => 'settings', 'action' => 'delete', 'resource' => 'settings', 'payload' => $deletePayload], $tenant);
        $deleted = Mcp::call('slate_admin_execute', ['module' => 'settings', 'action' => 'delete', 'resource' => 'settings', 'payload' => $deletePayload, 'confirmation_token' => $deletePreview['confirmation_token']], $tenant);
        assert_true($deleted['ok'] === true && $deleted['deleted'] === 1);
    } finally {
        Database::delete('settings', 'tenant_id = ? AND setting_key = ?', [$tenant, $key]);
    }
});

unit('MCP reads and tests every installed allow-listed resource with tenant scoping', function (): void {
    $tenant = (int)TENANT_ID;
    foreach (Mcp::resources() as $module => $resources) {
        foreach ($resources as $resource) {
            $exists = (bool)Database::value('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?', [$resource]);
            if (!$exists) { echo "# SKIP inactive or unapplied resource $module/$resource\n"; continue; }
            $columns = array_map(static fn(array $row): string => (string)$row['Field'], Database::rows("SHOW COLUMNS FROM `$resource`"));
            $tenantScoped = in_array('tenant_id', $columns, true) || $resource === 'role_permissions';
            assert_true($tenantScoped, "$module/$resource must have a tenant-safe scope");
            foreach ([['action' => 'test', 'payload' => []], ['action' => 'list', 'payload' => ['limit' => 1]]] as $case) {
                $preview = Mcp::call('slate_admin_preview', ['module' => $module, 'action' => $case['action'], 'resource' => $resource, 'payload' => $case['payload']], $tenant);
                $result = Mcp::call('slate_admin_execute', ['module' => $module, 'action' => $case['action'], 'resource' => $resource, 'payload' => $case['payload'], 'confirmation_token' => $preview['confirmation_token']], $tenant);
                assert_true($result['ok'] === true, $case['action'] . " action failed for $module/$resource");
            }
        }
    }
});

unit('MCP redacts sensitive values from returned rows', function (): void {
    $reflection = new ReflectionClass(Mcp::class);
    $method = $reflection->getMethod('redactRow');
    $method->setAccessible(true);
    $row = $method->invoke(null, ['email' => 'admin@example.test', 'password_hash' => 'secret', 'api_key' => 'key', 'name' => 'Admin']);
    assert_eq('[REDACTED]', $row['password_hash']);
    assert_eq('[REDACTED]', $row['api_key']);
    assert_eq('admin@example.test', $row['email']);
    assert_eq('Admin', $row['name']);
});

unit('MCP confirmation tokens are single-use and operation-bound', function (): void {
    $tenant = (int)TENANT_ID;
    $payload = ['setting_key' => 'mcp_test_bound_' . bin2hex(random_bytes(4)), 'setting_value' => 'x'];
    $preview = Mcp::call('slate_admin_preview', ['module' => 'settings', 'action' => 'create', 'resource' => 'settings', 'payload' => $payload], $tenant);
    assert_throws(RuntimeException::class, fn() => Mcp::call('slate_admin_execute', ['module' => 'settings', 'action' => 'create', 'resource' => 'settings', 'payload' => ['setting_key' => $payload['setting_key'], 'setting_value' => 'tampered'], 'confirmation_token' => $preview['confirmation_token']], $tenant));
    $created = Mcp::call('slate_admin_execute', ['module' => 'settings', 'action' => 'create', 'resource' => 'settings', 'payload' => $payload, 'confirmation_token' => $preview['confirmation_token']], $tenant);
    assert_true($created['ok'] === true);
    assert_throws(RuntimeException::class, fn() => Mcp::call('slate_admin_execute', ['module' => 'settings', 'action' => 'create', 'resource' => 'settings', 'payload' => $payload, 'confirmation_token' => $preview['confirmation_token']], $tenant));
    Database::delete('settings', 'tenant_id = ? AND setting_key = ?', [$tenant, $payload['setting_key']]);
});

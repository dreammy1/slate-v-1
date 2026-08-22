<?php
declare(strict_types=1);

/**
 * Slate MCP foundation.
 *
 * The endpoint is intentionally allow-listed. It never exposes raw SQL,
 * arbitrary filesystem access, PHP execution, or unrestricted admin POSTs.
 */
final class Mcp
{
    private const TOKEN_TABLE = 'mcp_tokens';
    private const CONFIRM_TABLE = 'mcp_confirmations';
    private const TOKEN_TTL = 31536000; // one year; rotate/revoke from admin.

    public static function ensureSchema(): void
    {
        Database::query("CREATE TABLE IF NOT EXISTS mcp_tokens (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id BIGINT UNSIGNED NOT NULL,
            token_hash VARCHAR(255) NOT NULL,
            token_prefix VARCHAR(16) NOT NULL,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            last_used_at DATETIME NULL,
            revoked_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY idx_mcp_tokens_tenant (tenant_id, revoked_at),
            UNIQUE KEY uq_mcp_tokens_hash (token_hash)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        Database::query("CREATE TABLE IF NOT EXISTS mcp_confirmations (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id BIGINT UNSIGNED NOT NULL,
            token_hash VARCHAR(255) NOT NULL,
            operation_hash CHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY idx_mcp_confirmations_lookup (tenant_id, operation_hash, used_at, expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public static function issueToken(?int $tenantId = null, ?int $createdBy = null): array
    {
        self::ensureSchema();
        $tenantId = $tenantId ?? current_tenant_id();
        Database::update(self::TOKEN_TABLE, ['revoked_at' => date('Y-m-d H:i:s')], 'tenant_id = ? AND revoked_at IS NULL', [$tenantId]);
        $raw = 'slate_mcp_' . rtrim(strtr(base64_encode(random_bytes(36)), '+/', '-_'), '=');
        Database::insert(self::TOKEN_TABLE, [
            'tenant_id' => $tenantId,
            'token_hash' => password_hash($raw, PASSWORD_DEFAULT),
            'token_prefix' => substr($raw, 0, 16),
            'created_by' => $createdBy,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        AuditLog::record('mcp.token_issued', 'mcp', ['token_prefix' => substr($raw, 0, 16)]);
        return ['token' => $raw, 'prefix' => substr($raw, 0, 16), 'expires_in' => self::TOKEN_TTL];
    }

    public static function revokeAll(?int $tenantId = null): void
    {
        self::ensureSchema();
        $tenantId = $tenantId ?? current_tenant_id();
        Database::update(self::TOKEN_TABLE, ['revoked_at' => date('Y-m-d H:i:s')], 'tenant_id = ? AND revoked_at IS NULL', [$tenantId]);
        AuditLog::record('mcp.token_revoked', 'mcp');
    }

    public static function tokenStatus(?int $tenantId = null): ?array
    {
        self::ensureSchema();
        return Database::row('SELECT id, token_prefix, created_at, last_used_at, revoked_at FROM mcp_tokens WHERE tenant_id = ? ORDER BY id DESC LIMIT 1', [$tenantId ?? current_tenant_id()]);
    }

    public static function authenticate(string $token): array
    {
        self::ensureSchema();
        $tenantId = (int)TENANT_ID;
        $rows = Database::rows('SELECT * FROM mcp_tokens WHERE tenant_id = ? AND revoked_at IS NULL ORDER BY id DESC', [$tenantId]);
        foreach ($rows as $row) {
            if (password_verify($token, (string)$row['token_hash'])) {
                Database::update(self::TOKEN_TABLE, ['last_used_at' => date('Y-m-d H:i:s')], 'id = ?', [(int)$row['id']]);
                return ['ok' => true, 'tenant_id' => $tenantId, 'token_id' => (int)$row['id']];
            }
        }
        return ['ok' => false, 'tenant_id' => $tenantId];
    }

    public static function confirmation(string $operation, array $arguments, int $tenantId): string
    {
        self::ensureSchema();
        $raw = 'confirm_' . bin2hex(random_bytes(24));
        $operationHash = hash('sha256', $operation . ':' . json_encode($arguments, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        Database::insert(self::CONFIRM_TABLE, [
            'tenant_id' => $tenantId,
            'token_hash' => password_hash($raw, PASSWORD_DEFAULT),
            'operation_hash' => $operationHash,
            'expires_at' => date('Y-m-d H:i:s', time() + 300),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        AuditLog::record('mcp.confirmation_created', $operation, ['operation_hash' => $operationHash]);
        return $raw;
    }

    public static function consumeConfirmation(string $token, string $operation, array $arguments, int $tenantId): bool
    {
        self::ensureSchema();
        $hash = hash('sha256', $operation . ':' . json_encode($arguments, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $rows = Database::rows('SELECT * FROM mcp_confirmations WHERE tenant_id = ? AND operation_hash = ? AND used_at IS NULL AND expires_at > NOW() ORDER BY id DESC', [$tenantId, $hash]);
        foreach ($rows as $row) {
            if (password_verify($token, (string)$row['token_hash'])) {
                $changed = Database::update(self::CONFIRM_TABLE, ['used_at' => date('Y-m-d H:i:s')], 'id = ? AND used_at IS NULL', [(int)$row['id']]);
                return $changed === 1;
            }
        }
        return false;
    }

    public static function modules(): array
    {
        return [
            'users', 'roles', 'settings', 'plugins', 'media', 'forms', 'shop',
            'bookings', 'memberships', 'seo', 'content',
        ];
    }

    public static function call(string $tool, array $args, int $tenantId): array
    {
        if ($tool === 'slate_admin_health') {
            return ['ok' => true, 'tenant_id' => $tenantId, 'version' => defined('SLATE_VERSION') ? SLATE_VERSION : null, 'modules' => self::modules()];
        }
        if ($tool === 'slate_admin_capabilities') {
            return ['modules' => self::modules(), 'read_tools' => ['settings', 'users', 'plugins', 'audit'], 'mutation_policy' => 'preview_then_execute_with_confirmation_token', 'unsupported_direct_access' => ['sql', 'filesystem', 'php_execution']];
        }
        if ($tool === 'slate_admin_preview') {
            $operation = self::operation($args);
            if (!in_array($operation['action'], ['create', 'edit', 'write', 'delete', 'test'], true)) throw new InvalidArgumentException('Unsupported mutation action.');
            return ['ok' => true, 'requires_confirmation' => true, 'operation' => $operation, 'confirmation_token' => self::confirmation($operation['name'], $operation['arguments'], $tenantId), 'expires_in' => 300];
        }
        if ($tool === 'slate_admin_execute') {
            $operation = self::operation($args);
            $confirm = (string)($args['confirmation_token'] ?? '');
            if ($confirm === '' || !self::consumeConfirmation($confirm, $operation['name'], $operation['arguments'], $tenantId)) throw new RuntimeException('A valid, unexpired confirmation token is required and can only be used once.');
            $result = self::execute($operation, $tenantId);
            AuditLog::record('mcp.admin.' . $operation['action'], $operation['name'], ['arguments' => $operation['arguments'], 'result' => $result]);
            return $result;
        }
        throw new InvalidArgumentException('Unknown MCP tool.');
    }

    private static function operation(array $args): array
    {
        $module = strtolower(trim((string)($args['module'] ?? '')));
        $action = strtolower(trim((string)($args['action'] ?? '')));
        $resource = trim((string)($args['resource'] ?? ''));
        if (!in_array($module, self::modules(), true) || $action === '' || $resource === '') throw new InvalidArgumentException('module, action, and resource are required and must be allow-listed.');
        return ['name' => $module . '.' . $resource, 'module' => $module, 'action' => $action, 'resource' => $resource, 'arguments' => ['module' => $module, 'action' => $action, 'resource' => $resource, 'payload' => $args['payload'] ?? []]];
    }

    private static function execute(array $operation, int $tenantId): array
    {
        $module = $operation['module'];
        $resource = $operation['resource'];
        $payload = $operation['arguments']['payload'];
        if ($module === 'settings' && $resource === 'setting' && in_array($operation['action'], ['write', 'edit', 'create'], true)) {
            $key = trim((string)($payload['setting_key'] ?? ''));
            if ($key === '' || strlen($key) > 120) throw new InvalidArgumentException('A valid setting_key is required.');
            Database::setSetting($key, (string)($payload['setting_value'] ?? ''), $tenantId);
            return ['ok' => true, 'module' => $module, 'resource' => $resource, 'setting_key' => $key];
        }
        throw new RuntimeException('This module/resource adapter is not enabled yet. It is listed in capabilities and must be implemented through a reviewed Slate adapter; arbitrary SQL is intentionally unavailable.');
    }
}

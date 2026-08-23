<?php
declare(strict_types=1);

/**
 * Secure Slate MCP service. Tables and columns are allow-listed through the
 * module map and schema introspection; arbitrary SQL, PHP, and filesystem
 * access are intentionally unavailable.
 */
final class Mcp
{
    private const TOKEN_TABLE = 'mcp_tokens';
    private const CONFIRM_TABLE = 'mcp_confirmations';
    private const TOKEN_TTL = 31536000;

    private const RESOURCES = [
        'users' => ['users', 'customers', 'customer_auth_tokens'],
        'roles' => ['roles', 'role_permissions'],
        'settings' => ['settings'],
        'plugins' => ['plugins'],
        'media' => ['media_files', 'media_usage', 'medialibrary_files'],
        'forms' => ['forms_definitions', 'forms_submissions', 'forms_webhooks', 'forms_spam_log', 'forms_webhook_log', 'contact_forms', 'contact_form_submissions'],
        'shop' => ['shop_categories', 'shop_products', 'shop_product_variants', 'shop_coupons', 'shop_orders', 'shop_order_items', 'shop_customers', 'shop_carts', 'shop_cart_items'],
        'bookings' => ['booking_services', 'booking_categories', 'booking_providers', 'booking_provider_services', 'booking_provider_hours', 'booking_provider_breaks', 'booking_date_overrides', 'booking_appointments', 'booking_customers', 'booking_locations', 'booking_resources', 'booking_service_resources', 'booking_service_addons', 'booking_custom_fields', 'bookingplus_appointment_meta', 'bookingplus_service_config', 'bookingplus_slot_restrictions'],
        'memberships' => ['membership_plans', 'membership_profiles', 'membership_subscriptions', 'membership_wallet', 'membership_wallet_txns'],
        'seo' => ['seo_settings'],
        'content' => ['contentbuilder_posts', 'contentbuilder_post_meta', 'contentbuilder_post_types', 'contentbuilder_menus', 'contentbuilder_taxonomies', 'contentbuilder_terms', 'contentbuilder_term_relations'],
    ];

    private const REDACTED_COLUMNS = ['password', 'password_hash', 'token', 'token_hash', 'secret', 'api_key', 'private_key', 'smtp_pass', 'stripe_secret_key', 'access_token', 'refresh_token'];

    public static function ensureSchema(): void
    {
        if (getenv('SLATE_TEST_SQLITE') === '1') {
            Database::query("CREATE TABLE IF NOT EXISTS mcp_tokens (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, token_hash TEXT NOT NULL, token_prefix TEXT NOT NULL, created_by INTEGER NULL, created_at TEXT NOT NULL, last_used_at TEXT NULL, revoked_at TEXT NULL, UNIQUE(token_hash))");
            Database::query("CREATE TABLE IF NOT EXISTS mcp_confirmations (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, token_hash TEXT NOT NULL, operation_hash TEXT NOT NULL, expires_at TEXT NOT NULL, used_at TEXT NULL, created_at TEXT NOT NULL)");
            return;
        }
        Database::query("CREATE TABLE IF NOT EXISTS mcp_tokens (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, tenant_id BIGINT UNSIGNED NOT NULL, token_hash VARCHAR(255) NOT NULL, token_prefix VARCHAR(16) NOT NULL, created_by BIGINT UNSIGNED NULL, created_at DATETIME NOT NULL, last_used_at DATETIME NULL, revoked_at DATETIME NULL, PRIMARY KEY (id), KEY idx_mcp_tokens_tenant (tenant_id, revoked_at), UNIQUE KEY uq_mcp_tokens_hash (token_hash)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        Database::query("CREATE TABLE IF NOT EXISTS mcp_confirmations (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, tenant_id BIGINT UNSIGNED NOT NULL, token_hash VARCHAR(255) NOT NULL, operation_hash CHAR(64) NOT NULL, expires_at DATETIME NOT NULL, used_at DATETIME NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id), KEY idx_mcp_confirmations_lookup (tenant_id, operation_hash, used_at, expires_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public static function issueToken(?int $tenantId = null, ?int $createdBy = null): array
    {
        self::ensureSchema(); $tenantId = $tenantId ?? current_tenant_id();
        Database::update(self::TOKEN_TABLE, ['revoked_at' => date('Y-m-d H:i:s')], 'tenant_id = ? AND revoked_at IS NULL', [$tenantId]);
        $raw = 'slate_mcp_' . rtrim(strtr(base64_encode(random_bytes(36)), '+/', '-_'), '=');
        Database::insert(self::TOKEN_TABLE, ['tenant_id' => $tenantId, 'token_hash' => password_hash($raw, PASSWORD_DEFAULT), 'token_prefix' => substr($raw, 0, 16), 'created_by' => $createdBy, 'created_at' => date('Y-m-d H:i:s')]);
        AuditLog::record('mcp.token_issued', 'mcp', ['token_prefix' => substr($raw, 0, 16)]);
        return ['token' => $raw, 'prefix' => substr($raw, 0, 16), 'expires_in' => self::TOKEN_TTL];
    }

    public static function revokeAll(?int $tenantId = null): void
    {
        self::ensureSchema(); $tenantId = $tenantId ?? current_tenant_id();
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
        self::ensureSchema(); $tenantId = (int)TENANT_ID;
        foreach (Database::rows('SELECT * FROM mcp_tokens WHERE tenant_id = ? AND revoked_at IS NULL ORDER BY id DESC', [$tenantId]) as $row) {
            if (password_verify($token, (string)$row['token_hash'])) {
                Database::update(self::TOKEN_TABLE, ['last_used_at' => date('Y-m-d H:i:s')], 'id = ?', [(int)$row['id']]);
                return ['ok' => true, 'tenant_id' => $tenantId, 'token_id' => (int)$row['id']];
            }
        }
        return ['ok' => false, 'tenant_id' => $tenantId];
    }

    public static function modules(): array { return array_keys(self::RESOURCES); }
    public static function resources(): array { return self::RESOURCES; }

    public static function confirmation(string $operation, array $arguments, int $tenantId): string
    {
        self::ensureSchema(); $raw = 'confirm_' . bin2hex(random_bytes(24));
        $hash = self::operationHash($operation, $arguments);
        Database::insert(self::CONFIRM_TABLE, ['tenant_id' => $tenantId, 'token_hash' => password_hash($raw, PASSWORD_DEFAULT), 'operation_hash' => $hash, 'expires_at' => date('Y-m-d H:i:s', time() + 300), 'created_at' => date('Y-m-d H:i:s')]);
        AuditLog::record('mcp.confirmation_created', $operation, ['operation_hash' => $hash]);
        return $raw;
    }

    public static function consumeConfirmation(string $token, string $operation, array $arguments, int $tenantId): bool
    {
        self::ensureSchema(); $hash = self::operationHash($operation, $arguments);
        foreach (Database::rows('SELECT * FROM mcp_confirmations WHERE tenant_id = ? AND operation_hash = ? AND used_at IS NULL AND expires_at > NOW() ORDER BY id DESC', [$tenantId, $hash]) as $row) {
            if (password_verify($token, (string)$row['token_hash'])) return Database::update(self::CONFIRM_TABLE, ['used_at' => date('Y-m-d H:i:s')], 'id = ? AND used_at IS NULL', [(int)$row['id']]) === 1;
        }
        return false;
    }

    public static function call(string $tool, array $args, int $tenantId): array
    {
        if ($tool === 'slate_admin_health') return ['ok' => true, 'tenant_id' => $tenantId, 'version' => defined('SLATE_VERSION') ? SLATE_VERSION : null, 'modules' => self::modules()];
        if ($tool === 'slate_admin_capabilities') return ['modules' => self::modules(), 'resources' => self::RESOURCES, 'actions' => ['read', 'list', 'test', 'create', 'edit', 'write', 'delete'], 'mutation_policy' => 'preview_then_execute_with_single_use_confirmation_token', 'redacted_columns' => self::REDACTED_COLUMNS, 'unsupported_direct_access' => ['sql', 'filesystem', 'php_execution']];
        if ($tool === 'slate_admin_preview') {
            $op = self::operation($args);
            return ['ok' => true, 'requires_confirmation' => true, 'operation' => $op, 'confirmation_token' => self::confirmation($op['name'], $op['arguments'], $tenantId), 'expires_in' => 300];
        }
        if ($tool === 'slate_admin_execute') {
            $op = self::operation($args); $confirm = (string)($args['confirmation_token'] ?? '');
            if ($confirm === '' || !self::consumeConfirmation($confirm, $op['name'], $op['arguments'], $tenantId)) throw new RuntimeException('A valid, unexpired confirmation token is required and can only be used once.');
            $result = self::execute($op, $tenantId);
            AuditLog::record('mcp.admin.' . $op['action'], $op['name'], ['arguments' => $op['arguments'], 'result' => $result]);
            return $result;
        }
        throw new InvalidArgumentException('Unknown MCP tool.');
    }

    private static function operation(array $args): array
    {
        $module = strtolower(trim((string)($args['module'] ?? ''))); $action = strtolower(trim((string)($args['action'] ?? ''))); $resource = trim((string)($args['resource'] ?? ''));
        if (!isset(self::RESOURCES[$module]) || !in_array($resource, self::RESOURCES[$module], true)) throw new InvalidArgumentException('module and resource must be explicitly allow-listed.');
        if (!in_array($action, ['read', 'list', 'test', 'create', 'edit', 'write', 'delete'], true)) throw new InvalidArgumentException('Unsupported action.');
        $payload = is_array($args['payload'] ?? null) ? $args['payload'] : [];
        if (in_array($action, ['edit', 'delete'], true) && empty($payload['id'])) throw new InvalidArgumentException('payload.id is required for edit/delete.');
        return ['name' => $module . '.' . $resource, 'module' => $module, 'action' => $action, 'resource' => $resource, 'arguments' => ['module' => $module, 'action' => $action, 'resource' => $resource, 'payload' => $payload]];
    }

    private static function execute(array $op, int $tenantId): array
    {
        $table = $op['resource']; $columns = self::columns($table);
        $action = $op['action']; $payload = $op['arguments']['payload'];
        $params = [];
        $scope = self::tenantScope($table, $columns, $tenantId, $params);
        if ($action === 'test') return ['ok' => true, 'resource' => $table, 'count' => (int)Database::value("SELECT COUNT(*) FROM `$table` WHERE $scope", $params)];
        if (in_array($action, ['read', 'list'], true)) {
            $limit = min(max((int)($payload['limit'] ?? 50), 1), 200); $where = [$scope];
            if (!empty($payload['id']) && in_array('id', $columns, true)) { $where[] = 'id = ?'; $params[] = (int)$payload['id']; }
            foreach (($payload['filters'] ?? []) as $key => $value) { if (is_string($key) && in_array($key, $columns, true) && !self::redacted($key)) { $where[] = "`$key` = ?"; $params[] = is_scalar($value) ? $value : json_encode($value); } }
            $order = in_array('id', $columns, true) ? ' ORDER BY id DESC' : '';
            $rows = Database::rows("SELECT * FROM `$table` WHERE " . implode(' AND ', $where) . $order . " LIMIT $limit", $params);
            return ['ok' => true, 'resource' => $table, 'items' => array_map([self::class, 'redactRow'], $rows), 'count' => count($rows)];
        }
        if ($action === 'write' && !empty($payload['id'])) $action = 'edit';
        $safe = [];
        foreach ($payload as $key => $value) if (is_string($key) && in_array($key, $columns, true) && !in_array($key, ['id', 'tenant_id', 'created_at', 'updated_at'], true) && !self::redacted($key)) $safe[$key] = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value;
        if (!$safe && $action !== 'delete') throw new InvalidArgumentException('No writable allow-listed fields were supplied.');
        if (in_array($action, ['create', 'write'], true)) {
            if (in_array('tenant_id', $columns, true)) $safe['tenant_id'] = $tenantId;
            elseif ($table === 'role_permissions') { if (empty($safe['role_id']) || !Database::value('SELECT id FROM roles WHERE id = ? AND tenant_id = ?', [(int)$safe['role_id'], $tenantId])) throw new InvalidArgumentException('role_id must belong to the current tenant.'); }
            else throw new RuntimeException('This resource has no safe tenant scope.');
            $id = Database::insert($table, $safe); return ['ok' => true, 'action' => 'create', 'resource' => $table, 'id' => $id];
        }
        if ($action === 'edit') { unset($safe['tenant_id']); $changed = Database::update($table, $safe, 'id = ? AND ' . $scope, array_merge([(int)$payload['id']], $params)); return ['ok' => true, 'action' => 'edit', 'resource' => $table, 'id' => (int)$payload['id'], 'changed' => $changed]; }
        if ($action === 'delete') { $deleted = Database::delete($table, 'id = ? AND ' . $scope, array_merge([(int)$payload['id']], $params)); return ['ok' => true, 'action' => 'delete', 'resource' => $table, 'id' => (int)$payload['id'], 'deleted' => $deleted]; }
        throw new InvalidArgumentException('Unsupported resource action.');
    }

    private static function columns(string $table): array
    {
        if (getenv('SLATE_TEST_SQLITE') === '1') {
            $safe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
            return array_map(static fn(array $row): string => (string)$row['name'], Database::rows("PRAGMA table_info(`$safe`)") );
        }
        return array_map(static fn(array $row): string => (string)$row['Field'], Database::rows("SHOW COLUMNS FROM `$table`"));
    }
    private static function tenantScope(string $table, array $columns, int $tenantId, array &$params): string
    {
        if (in_array('tenant_id', $columns, true)) { $params[] = $tenantId; return 'tenant_id = ?'; }
        if ($table === 'role_permissions') { $params[] = $tenantId; return 'role_id IN (SELECT id FROM roles WHERE tenant_id = ?)'; }
        throw new RuntimeException('This resource has no safe tenant scope.');
    }
    private static function redacted(string $key): bool { $key = strtolower($key); foreach (self::REDACTED_COLUMNS as $needle) if (str_contains($key, $needle)) return true; return false; }
    private static function redactRow(array $row): array { foreach (array_keys($row) as $key) if (self::redacted((string)$key)) $row[$key] = '[REDACTED]'; return $row; }
    private static function operationHash(string $operation, array $arguments): string { return hash('sha256', $operation . ':' . json_encode($arguments, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)); }
}

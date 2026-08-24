<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function mcp_response($id, array $result): never {
    echo json_encode(['jsonrpc' => '2.0', 'id' => $id, 'result' => $result], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
function mcp_error($id, int $code, string $message, array $data = []): never {
    http_response_code($code === -32600 ? 400 : 200);
    echo json_encode(['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message] + ($data ? ['data' => $data] : [])], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$auth = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? '');
if (!preg_match('/^Bearer\s+(.+)$/i', $auth, $match)) {
    header('WWW-Authenticate: Bearer');
    http_response_code(401);
    echo json_encode(['error' => 'Bearer token required']);
    exit;
}
$identity = Mcp::authenticate(trim($match[1]));
if (!$identity['ok']) {
    header('WWW-Authenticate: Bearer');
    http_response_code(401);
    echo json_encode(['error' => 'Invalid or revoked MCP token']);
    exit;
}

$raw = file_get_contents('php://input');
$request = json_decode($raw ?: '', true);
if (!is_array($request) || ($request['jsonrpc'] ?? '') !== '2.0') {
    mcp_error(null, -32600, 'Invalid JSON-RPC request.');
}
$method = (string)($request['method'] ?? '');
// JSON-RPC notifications intentionally have no id. MCP clients send this
// notification immediately after initialize; it must be accepted before the
// request-id validation used for methods that return a response.
if ($method === 'notifications/initialized') {
    http_response_code(202);
    exit;
}
if (!array_key_exists('id', $request)) {
    mcp_error(null, -32600, 'Invalid JSON-RPC request.');
}
$id = $request['id'];
$params = is_array($request['params'] ?? null) ? $request['params'] : [];

try {
    if ($method === 'initialize') {
        mcp_response($id, [
            'protocolVersion' => '2024-11-05',
            'capabilities' => ['tools' => ['listChanged' => false]],
            'serverInfo' => ['name' => 'slate-admin-mcp', 'version' => defined('SLATE_VERSION') ? SLATE_VERSION : '1.0.0'],
            'instructions' => 'Use slate_admin_preview before every mutation, then pass its single-use confirmation_token to slate_admin_execute.',
        ]);
    }
    if ($method === 'tools/list') {
        mcp_response($id, ['tools' => [
            ['name' => 'slate_admin_health', 'description' => 'Check Slate MCP availability and tenant context.', 'inputSchema' => ['type' => 'object', 'properties' => [], 'additionalProperties' => false]],
            ['name' => 'slate_admin_capabilities', 'description' => 'List the allow-listed Slate admin modules and mutation policy.', 'inputSchema' => ['type' => 'object', 'properties' => [], 'additionalProperties' => false]],
            ['name' => 'slate_admin_preview', 'description' => 'Preview an admin create, edit, write, delete, or test operation and issue a short-lived one-time confirmation token.', 'inputSchema' => ['type' => 'object', 'required' => ['module', 'action', 'resource'], 'properties' => ['module' => ['type' => 'string', 'enum' => Mcp::modules()], 'action' => ['type' => 'string', 'enum' => ['create', 'edit', 'write', 'delete', 'test']], 'resource' => ['type' => 'string'], 'payload' => ['type' => 'object']]]],
            ['name' => 'slate_admin_execute', 'description' => 'Execute a previously previewed allow-listed admin operation using its single-use confirmation token.', 'inputSchema' => ['type' => 'object', 'required' => ['module', 'action', 'resource', 'confirmation_token'], 'properties' => ['module' => ['type' => 'string', 'enum' => Mcp::modules()], 'action' => ['type' => 'string', 'enum' => ['create', 'edit', 'write', 'delete', 'test']], 'resource' => ['type' => 'string'], 'payload' => ['type' => 'object'], 'confirmation_token' => ['type' => 'string']]]],
        ]]);
    }
    if ($method === 'tools/call') {
        $name = (string)($params['name'] ?? '');
        $arguments = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];
        $result = Mcp::call($name, $arguments, (int)$identity['tenant_id']);
        mcp_response($id, ['content' => [['type' => 'text', 'text' => json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]], 'structuredContent' => $result]);
    }
    mcp_error($id, -32601, 'Method not found.');
} catch (InvalidArgumentException $e) {
    mcp_error($id, -32602, $e->getMessage());
} catch (Throwable $e) {
    error_log('Slate MCP error: ' . $e->getMessage());
    mcp_error($id, -32000, 'MCP operation failed. Check the Slate audit log and server logs.');
}

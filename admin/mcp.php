<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
Auth::require();
Auth::requirePerm('settings.view');

$pageTitle = 'AI / MCP Connection';
$currentNav = 'mcp';
$flash = null;
$issued = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $flash = ['type' => 'error', 'msg' => 'Security token expired. Please try again.'];
    } elseif (($_POST['action'] ?? '') === 'issue') {
        $issued = Mcp::issueToken(current_tenant_id(), (int)Auth::userId());
        $flash = ['type' => 'success', 'msg' => 'Token generated. Copy it now; it will not be shown again.'];
    } elseif (($_POST['action'] ?? '') === 'revoke') {
        Mcp::revokeAll();
        $flash = ['type' => 'success', 'msg' => 'All MCP tokens for this tenant were revoked.'];
    }
}

$status = Mcp::tokenStatus();
$endpoint = rtrim(SLATE_URL, '/') . '/mcp.php';
include __DIR__ . '/partials/header.php';
?>
<main class="page-content">
  <div class="page-header"><div><h1><?= e($pageTitle) ?></h1><p class="muted">Connect an AI client to controlled Slate administration tools.</p></div></div>
  <?php if ($flash): ?><div class="alert alert-<?= e($flash['type']) ?>" role="status"><?= e($flash['msg']) ?></div><?php endif; ?>
  <?php if ($issued): ?>
    <div class="card" style="border-color:#d97706">
      <h2>Copy this token now</h2>
      <p class="muted">This is the only time the raw token is displayed. Store it in your AI client’s secure secret storage.</p>
      <textarea readonly rows="3" style="width:100%;font-family:monospace" onclick="this.select()"><?= e($issued['token']) ?></textarea>
      <p><strong>Endpoint:</strong> <code><?= e($endpoint) ?></code></p>
    </div>
  <?php endif; ?>
  <div class="card">
    <h2>Connection details</h2>
    <p>Use this remote HTTPS MCP endpoint in Manus, Claude Desktop, Cursor, or another compatible client.</p>
    <p><strong>Server URL:</strong> <code><?= e($endpoint) ?></code></p>
    <p><strong>Authentication:</strong> HTTP header <code>Authorization: Bearer &lt;token&gt;</code></p>
    <p><strong>Current token:</strong> <?= $status && empty($status['revoked_at']) ? 'active (' . e($status['token_prefix']) . '...)' : 'not configured' ?></p>
    <p class="muted">The token is long-lived but revocable. Mutations require a separate short-lived confirmation token and are written to the Slate audit log. Raw SQL, arbitrary PHP, and unrestricted filesystem access are not exposed.</p>
  </div>
  <div class="card">
    <h2>Token management</h2>
    <form method="post" style="display:inline-block;margin-right:1rem">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="issue">
      <button class="btn btn-primary" type="submit">Generate / rotate token</button>
    </form>
    <form method="post" style="display:inline-block" onsubmit="return confirm('Revoke all MCP tokens for this tenant?')">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="revoke">
      <button class="btn btn-danger" type="submit">Revoke token</button>
    </form>
  </div>
  <div class="card">
    <h2>Connection test</h2>
    <p>After copying the token, call <code>initialize</code> and <code>tools/list</code> from your AI client. The health tool confirms tenant context without changing data.</p>
    <pre><code>curl -sS -X POST <?= e($endpoint) ?> \
  -H 'Authorization: Bearer YOUR_TOKEN' \
  -H 'Content-Type: application/json' \
  --data '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"slate_admin_health","arguments":{}}}'</code></pre>
  </div>
</main>
<?php include __DIR__ . '/partials/footer.php'; ?>

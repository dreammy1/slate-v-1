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
        $flash = ['type' => 'success', 'msg' => 'A new MCP token is ready. Copy it now; it will not be shown again.'];
    } elseif (($_POST['action'] ?? '') === 'revoke') {
        Mcp::revokeAll();
        $flash = ['type' => 'success', 'msg' => 'All MCP tokens for this tenant were revoked.'];
    }
}

$status = Mcp::tokenStatus();
$endpoint = rtrim(SLATE_URL, '/') . '/mcp.php';
$active = $status && empty($status['revoked_at']);
$lastUsed = !empty($status['last_used_at']) ? date('M j, Y · g:i A', strtotime((string)$status['last_used_at'])) : 'Not connected yet';
$created = $status && !empty($status['created_at']) ? date('M j, Y · g:i A', strtotime((string)$status['created_at'])) : '—';
include __DIR__ . '/partials/header.php';
?>
<style>
  .mcp-shell{max-width:1180px;margin:0 auto;padding-bottom:48px;color:#111827}
  .mcp-hero{display:flex;align-items:flex-start;justify-content:space-between;gap:24px;margin-bottom:26px}
  .mcp-eyebrow{display:flex;align-items:center;gap:8px;text-transform:uppercase;letter-spacing:.11em;font-size:11px;font-weight:800;color:#2563eb;margin-bottom:8px}
  .mcp-eyebrow span{width:7px;height:7px;border-radius:50%;background:#2563eb;box-shadow:0 0 0 4px #dbeafe}
  .mcp-hero h1{margin:0;font-size:clamp(25px,3vw,36px);letter-spacing:-.035em;line-height:1.1}
  .mcp-hero p{margin:10px 0 0;color:#64748b;font-size:15px;max-width:650px;line-height:1.6}
  .mcp-status-pill{display:inline-flex;align-items:center;gap:8px;padding:9px 13px;border:1px solid #bbf7d0;background:#f0fdf4;border-radius:999px;font-size:12px;font-weight:800;color:#166534;white-space:nowrap}
  .mcp-status-pill.off{border-color:#e2e8f0;background:#f8fafc;color:#64748b}
  .mcp-status-dot{width:8px;height:8px;border-radius:50%;background:#22c55e}.mcp-status-pill.off .mcp-status-dot{background:#94a3b8}
  .mcp-grid{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(300px,.65fr);gap:18px;align-items:start}
  .mcp-card{background:#fff;border:1px solid #e2e8f0;border-radius:16px;box-shadow:0 6px 24px rgba(15,23,42,.045);padding:24px}
  .mcp-card h2{font-size:17px;letter-spacing:-.015em;margin:0}.mcp-card h3{font-size:13px;margin:0 0 6px}.mcp-muted{color:#64748b;font-size:13px;line-height:1.55}
  .mcp-connection{background:linear-gradient(135deg,#eff6ff 0%,#fff 58%);border-color:#bfdbfe}.mcp-connection-head{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;margin-bottom:22px}
  .mcp-kicker{font-size:11px;text-transform:uppercase;letter-spacing:.1em;font-weight:800;color:#3b82f6;margin-bottom:7px}
  .mcp-endpoint{display:flex;align-items:center;gap:10px;background:#fff;border:1px solid #dbeafe;border-radius:10px;padding:12px 13px;margin:12px 0 18px;min-width:0}.mcp-endpoint code{font:600 12px ui-monospace,SFMono-Regular,Menlo,monospace;color:#1e3a8a;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1}.mcp-copy{border:0;background:#eff6ff;color:#2563eb;border-radius:7px;padding:7px 10px;font-size:12px;font-weight:800;cursor:pointer}.mcp-copy:hover{background:#dbeafe}
  .mcp-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}.mcp-stat{padding:13px 14px;background:rgba(255,255,255,.78);border:1px solid #dbeafe;border-radius:10px}.mcp-stat-label{color:#64748b;font-size:11px;font-weight:700;margin-bottom:6px}.mcp-stat-value{font-size:13px;font-weight:800;color:#0f172a;line-height:1.35}
  .mcp-token{margin-top:18px;border:1px solid #fed7aa;background:#fff7ed;border-radius:12px;padding:17px}.mcp-token h3{color:#9a3412}.mcp-token textarea{width:100%;box-sizing:border-box;border:1px solid #fdba74;border-radius:8px;background:#fff;color:#7c2d12;font:600 12px ui-monospace,SFMono-Regular,Menlo,monospace;padding:11px;resize:none}.mcp-warning{display:flex;gap:9px;align-items:flex-start;color:#9a3412;font-size:12px;line-height:1.45;margin-top:10px}
  .mcp-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:18px}.mcp-btn{appearance:none;border:0;border-radius:9px;padding:10px 14px;font-weight:800;font-size:13px;cursor:pointer}.mcp-btn.primary{background:#2563eb;color:#fff;box-shadow:0 2px 4px rgba(37,99,235,.2)}.mcp-btn.primary:hover{background:#1d4ed8}.mcp-btn.ghost{background:#f8fafc;border:1px solid #e2e8f0;color:#334155}.mcp-btn.danger{background:#fff1f2;border:1px solid #fecdd3;color:#be123c}.mcp-btn.danger:hover{background:#ffe4e6}
  .mcp-side{display:grid;gap:18px}.mcp-list{display:grid;gap:14px;margin-top:17px}.mcp-list-row{display:flex;gap:12px;align-items:flex-start}.mcp-icon{display:grid;place-items:center;width:28px;height:28px;border-radius:9px;background:#eff6ff;color:#2563eb;font-size:14px;font-weight:900;flex:0 0 auto}.mcp-list-row strong{display:block;font-size:13px;margin-bottom:2px}.mcp-list-row span{display:block;color:#64748b;font-size:12px;line-height:1.45}
  .mcp-steps{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-top:18px}.mcp-step{padding:14px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:11px}.mcp-step-num{display:grid;place-items:center;width:24px;height:24px;border-radius:50%;background:#dbeafe;color:#1d4ed8;font-size:12px;font-weight:900;margin-bottom:10px}.mcp-step strong{display:block;font-size:12px;margin-bottom:5px}.mcp-step span{display:block;color:#64748b;font-size:11px;line-height:1.45}
  .mcp-alert{border-radius:10px;padding:12px 14px;margin-bottom:18px;font-size:13px;font-weight:700}.mcp-alert.success{background:#f0fdf4;color:#166534;border:1px solid #bbf7d0}.mcp-alert.error{background:#fff1f2;color:#be123c;border:1px solid #fecdd3}
  .mcp-code{background:#0f172a;color:#cbd5e1;border-radius:10px;padding:14px;overflow:auto;font:11px/1.6 ui-monospace,SFMono-Regular,Menlo,monospace;margin:14px 0 0}.mcp-code .accent{color:#93c5fd}.mcp-card-footer{border-top:1px solid #f1f5f9;margin:20px -24px -24px;padding:15px 24px;color:#64748b;font-size:11px}
  @media(max-width:850px){.mcp-grid{grid-template-columns:1fr}.mcp-hero{display:block}.mcp-status-pill{margin-top:16px}.mcp-hero p{max-width:none}}
  @media(max-width:560px){.mcp-card{padding:18px;border-radius:13px}.mcp-stats,.mcp-steps{grid-template-columns:1fr}.mcp-card-footer{margin-left:-18px;margin-right:-18px;padding-left:18px;padding-right:18px}.mcp-connection-head{display:block}}
</style>
<main class="content">
  <div class="mcp-shell">
    <div class="mcp-hero">
      <div>
        <div class="mcp-eyebrow"><span></span> Secure AI connection</div>
        <h1>AI / MCP Connection</h1>
        <p>Connect your AI tools to Slate with a single managed endpoint. Every request stays tenant-scoped, permission-aware, and visible in the audit log.</p>
      </div>
      <div class="mcp-status-pill <?= $active ? '' : 'off' ?>"><span class="mcp-status-dot"></span><?= $active ? 'Connection ready' : 'Not connected' ?></div>
    </div>
    <?php if ($flash): ?><div class="mcp-alert <?= e($flash['type']) ?>" role="status"><?= e($flash['msg']) ?></div><?php endif; ?>

    <div class="mcp-grid">
      <section class="mcp-card mcp-connection" aria-labelledby="connection-title">
        <div class="mcp-connection-head"><div><div class="mcp-kicker">Connection status</div><h2 id="connection-title">Your Slate MCP endpoint</h2></div><span class="mcp-status-pill <?= $active ? '' : 'off' ?>"><span class="mcp-status-dot"></span><?= $active ? 'Active' : 'Inactive' ?></span></div>
        <p class="mcp-muted">Use this endpoint in Manus, Claude Desktop, Cursor, or any MCP-compatible client. Keep the token in the client’s secure secret storage.</p>
        <div class="mcp-endpoint"><code id="mcp-endpoint"><?= e($endpoint) ?></code><button class="mcp-copy" type="button" data-copy-target="mcp-endpoint">Copy URL</button></div>
        <div class="mcp-stats"><div class="mcp-stat"><div class="mcp-stat-label">Token status</div><div class="mcp-stat-value"><?= $active ? 'Active · ' . e($status['token_prefix']) . '…' : 'Not configured' ?></div></div><div class="mcp-stat"><div class="mcp-stat-label">Last connected</div><div class="mcp-stat-value"><?= e($lastUsed) ?></div></div><div class="mcp-stat"><div class="mcp-stat-label">Created</div><div class="mcp-stat-value"><?= e($created) ?></div></div></div>
        <?php if ($issued): ?><div class="mcp-token" aria-labelledby="new-token-title"><h3 id="new-token-title">Copy your new token now</h3><p class="mcp-muted">For your security, this raw token will not appear again after leaving this page.</p><textarea id="mcp-token" readonly rows="3" aria-label="New MCP token" onclick="this.select()"><?= e($issued['token']) ?></textarea><div class="mcp-warning"><strong>One time only.</strong><span>Copy it now and store it in your AI client. Generating another token revokes the previous one.</span></div><div class="mcp-actions"><button class="mcp-btn primary" type="button" data-copy-target="mcp-token">Copy token</button></div></div><?php endif; ?>
        <div class="mcp-actions"><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="issue"><button class="mcp-btn primary" type="submit"><?= $active ? 'Rotate token' : 'Generate token' ?></button></form><?php if ($active): ?><form method="post" onsubmit="return confirm('Revoke the active MCP token? Connected AI clients will stop working immediately.')"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="revoke"><button class="mcp-btn danger" type="submit">Revoke access</button></form><?php endif; ?></div>
        <div class="mcp-card-footer">Token access is long-lived for convenience but always revocable. Mutations require a separate, short-lived confirmation token.</div>
      </section>

      <div class="mcp-side">
        <section class="mcp-card" aria-labelledby="security-title"><div class="mcp-kicker">Built-in safeguards</div><h2 id="security-title">Secure by default</h2><div class="mcp-list"><div class="mcp-list-row"><div class="mcp-icon">✓</div><div><strong>Tenant-scoped access</strong><span>AI tools can only reach this Slate tenant.</span></div></div><div class="mcp-list-row"><div class="mcp-icon">◈</div><div><strong>Hashed credentials</strong><span>Only a one-way token hash is stored.</span></div></div><div class="mcp-list-row"><div class="mcp-icon">↗</div><div><strong>Audited actions</strong><span>Token events and admin operations are recorded.</span></div></div><div class="mcp-list-row"><div class="mcp-icon">!</div><div><strong>Confirmed mutations</strong><span>Writes and deletes need a single-use confirmation.</span></div></div></div></section>
        <section class="mcp-card" aria-labelledby="clients-title"><div class="mcp-kicker">Works with your tools</div><h2 id="clients-title">Connect any MCP client</h2><p class="mcp-muted" style="margin:9px 0 0">Manus, Claude Desktop, Cursor, and other compatible clients can use the same HTTPS endpoint.</p><div class="mcp-code"><span class="accent">URL</span> <?= e($endpoint) ?><br><span class="accent">Auth</span> Bearer &lt;your-token&gt;</div></section>
      </div>
    </div>

    <section class="mcp-card" style="margin-top:18px" aria-labelledby="steps-title"><div class="mcp-kicker">Quick setup</div><h2 id="steps-title">Connect in three steps</h2><div class="mcp-steps"><div class="mcp-step"><div class="mcp-step-num">1</div><strong>Generate a token</strong><span>Choose Generate token above. The raw value is shown only once.</span></div><div class="mcp-step"><div class="mcp-step-num">2</div><strong>Add it to your client</strong><span>Paste the endpoint and bearer token into your client’s secure MCP settings.</span></div><div class="mcp-step"><div class="mcp-step-num">3</div><strong>Test the connection</strong><span>Call <code>slate_admin_health</code> to confirm the tenant is reachable.</span></div></div></section>
  </div>
</main>
<script>
(function(){
  document.querySelectorAll('[data-copy-target]').forEach(function(button){
    button.addEventListener('click', function(){
      var target=document.getElementById(button.getAttribute('data-copy-target')); if(!target) return;
      var value=target.value || target.textContent;
      navigator.clipboard.writeText(value).then(function(){var old=button.textContent;button.textContent='Copied';setTimeout(function(){button.textContent=old},1500)}).catch(function(){if(target.select){target.select();document.execCommand('copy')}});
    });
  });
})();
</script>
<?php include __DIR__ . '/partials/footer.php'; ?>

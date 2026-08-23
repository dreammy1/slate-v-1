<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
Auth::require();
Auth::requirePerm('settings.view');
$veStore = new \Slate\Services\Presentation\VisualEditorDocumentStore();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    if (!csrf_verify($_POST['_csrf'] ?? '')) { http_response_code(419); echo json_encode(['ok'=>false,'error'=>'Security token expired.']); exit; }
    $action = (string)($_POST['action'] ?? ''); $payload = json_decode((string)($_POST['document'] ?? ''), true);
    try {
        $page = $veStore->load((string)($_POST['slug'] ?? 'visual-editor-home'));
        $id = (int)$page['id']; $uid = class_exists('Auth') ? (int)Auth::userId() : null;
        $result = match ($action) {
            'save' => is_array($payload) ? $veStore->saveDraft($id, $payload, $uid) : ['ok'=>false,'error'=>'Invalid document.'],
            'publish' => $veStore->publish($id, $uid),
            'rollback' => $veStore->rollback($id, (int)($_POST['revision_id'] ?? 0), $uid),
            'history' => ['ok'=>true,'history'=>$veStore->history($id)],
            default => ['ok'=>false,'error'=>'Unknown editor action.'],
        };
        echo json_encode($result, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); exit;
    } catch (Throwable $e) { http_response_code(422); echo json_encode(['ok'=>false,'error'=>$e->getMessage()]); exit; }
}
$pageTitle = 'Visual Editor';
$currentNav = 'visual-editor';
$pageData = $veStore->load();
$seoSettings = [];
$seoIssues = [];
$seoActive = class_exists('SEOAPI');
if (!$seoActive && defined('SLATE_ROOT')) {
    $seoApi = SLATE_ROOT . '/plugins/seo/SEOAPI.php';
    if (is_file($seoApi)) { require_once $seoApi; $seoActive = class_exists('SEOAPI'); }
}
if ($seoActive) {
    try {
        $seoSettings = SEOAPI::allSettings();
        [, $seoIssues] = SEOAPI::validateSettings($seoSettings);
    } catch (Throwable $e) { $seoIssues = ['control_plane' => 'SEO settings are not available yet.']; }
}
include __DIR__ . '/partials/header.php';
?>
<link rel="stylesheet" href="<?= e(SLATE_URL) ?>/assets/css/visual-editor.v1.css?v=1">
<main class="content"><div class="ve-shell">
<div class="ve-top"><div class="ve-brand">Slate Visual Editor</div><span class="ve-pill">MVP · local draft</span><span class="ve-pill" style="background:<?= $seoIssues ? '#fffbeb' : '#f0fdf4' ?>;color:<?= $seoIssues ? '#92400e' : '#166534' ?>;border-color:<?= $seoIssues ? '#fde68a' : '#bbf7d0' ?>"><?= $seoActive ? ($seoIssues ? 'SEO needs review' : 'SEO linked') : 'SEO inactive' ?></span><span class="ve-status" id="ve-save-state">Ready</span><span class="ve-spacer"></span><button class="ve-btn" id="ve-undo" type="button">Undo</button><button class="ve-btn" id="ve-redo" type="button">Redo</button><button class="ve-btn" id="ve-import" type="button">Import HTML</button><button class="ve-btn" id="ve-preview" type="button">Preview</button><button class="ve-btn primary" id="ve-publish" type="button">Validate &amp; publish</button></div>
<div class="ve-body"><aside class="ve-panel ve-left"><div class="ve-panel-head"><span>Layers</span><span id="ve-node-count"></span></div><div class="ve-warning">Drag layers to reorder. Import static HTML to parse editable tags and classes into the normalized draft. Database-backed pages and production publishing remain gated tasks.</div><div class="ve-layers" id="ve-layers"></div></aside>
<section class="ve-canvas-wrap"><div class="ve-canvas-tools"><button class="ve-device active" data-device="desktop" type="button">Desktop</button><button class="ve-device" data-device="tablet" type="button">Tablet</button><button class="ve-device" data-device="mobile" type="button">Mobile</button></div><div class="ve-canvas"><div class="ve-page" id="ve-page"></div></div></section><div id="ve-import-panel" style="display:none;position:absolute;inset:70px 300px 40px 250px;z-index:20;background:#fff;border:1px solid #dbe3ef;border-radius:12px;box-shadow:0 20px 60px rgba(15,23,42,.2);padding:18px"><div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px"><strong>Import static HTML</strong><button class="ve-btn" id="ve-import-close" type="button">Close</button></div><textarea id="ve-import-source" style="width:100%;height:calc(100% - 100px);box-sizing:border-box;border:1px solid #dbe3ef;border-radius:8px;padding:12px;font:12px ui-monospace,monospace" placeholder="Paste a safe static HTML fragment here..."></textarea><div style="margin-top:10px;display:flex;justify-content:flex-end;gap:8px"><span class="ve-status">Scripts and inline event handlers are removed in the browser preview.</span><button class="ve-btn primary" id="ve-import-apply" type="button">Parse into canvas</button></div></div>
<aside class="ve-panel ve-right"><div class="ve-panel-head"><span>Inspector</span><span id="ve-selected-tag">—</span></div><div class="ve-inspector" id="ve-inspector"><div class="ve-empty">Select a layer or element on the canvas to edit its content and style.</div></div><div class="ve-warning" style="margin-top:0;background:<?= $seoIssues ? '#fffbeb' : '#f0fdf4' ?>;color:<?= $seoIssues ? '#92400e' : '#166534' ?>;border-color:<?= $seoIssues ? '#fde68a' : '#bbf7d0' ?>"><strong>SEO control plane:</strong> <?= $seoActive ? ($seoIssues ? count($seoIssues) . ' setting issue(s) need review before publish.' : 'Tenant defaults are loaded. Page overrides will be validated at publish time.') : 'Enable the SEO plugin to load tenant defaults and validation.' ?></div></aside></div></div></main>
<script>window.SLATE_VISUAL_EDITOR_CONFIG=<?= json_encode(['pageId'=>(int)$pageData['id'],'csrf'=>csrf_token(),'serverDoc'=>$pageData['document'],'seoDefaults'=>$seoSettings], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;</script>
<script src="<?= e(SLATE_URL) ?>/assets/js/visual-editor.v1.js?v=1" defer></script>
<?php include __DIR__ . '/partials/footer.php'; ?>

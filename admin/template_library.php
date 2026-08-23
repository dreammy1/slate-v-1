<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
Auth::require(); Auth::requirePerm('settings.view');
$pageTitle='Template Library'; $currentNav='template-library'; $flash=null;
$repo=new \Slate\Services\Presentation\ThemeTemplateRepository();
if ($_SERVER['REQUEST_METHOD']==='POST' && csrf_verify()) {
    try {
        $action=(string)($_POST['_action']??'');
        if ($action==='import') {
            $name=trim((string)($_POST['name']??'Untitled template')); $slug=strtolower(trim((string)($_POST['slug']??'')));
            $slug=preg_replace('/[^a-z0-9]+/','-', $slug) ?? ''; $slug=trim($slug,'-'); if($slug==='')$slug='template-'.date('YmdHis');
            $html=(string)($_POST['source_html']??''); if(strlen($html)>2*1024*1024)throw new RuntimeException('HTML template exceeds 2 MB.');
            $importer=new \Slate\Services\Import\HtmlTemplateImporter(); $result=$importer->import($html); $result['document']=$result['document']->toArray();
            $def=new \Slate\Presentation\Templates\TemplateDefinition('tpl-'.bin2hex(random_bytes(5)), $name, $slug, ['header','main','footer'], ['page','landing'], [], '1.0', 'draft');
            $id=$repo->createTemplate($def,(int)Auth::userId()); $repo->saveTemplateVersion($id,$def,(array)($result['document']??[]),$html,(array)($result['warnings']??[]),(int)Auth::userId(),'v1');
            $flash=['type'=>'success','msg'=>'Template imported into the tenant library.'];
        } elseif ($action==='activate') {
            $id=(int)($_POST['template_id']??0); $version=(string)($_POST['version']??'v1'); if(!$repo->activateTemplate($id,$version))throw new RuntimeException('Template version not found.'); Database::setSetting('front_page_template_id',(string)$id); Database::setSetting('front_page_template_version',$version); $flash=['type'=>'success','msg'=>'Template activated for the front page.'];
        }
    } catch(Throwable $e){$flash=['type'=>'error','msg'=>$e->getMessage()];}
} elseif ($_SERVER['REQUEST_METHOD']==='POST') {$flash=['type'=>'error','msg'=>'Security check failed.'];}
$templates=$repo->listTemplates('landing'); $activeId=(int)Database::setting('front_page_template_id'); $activeVersion=(string)Database::setting('front_page_template_version');
include __DIR__.'/partials/header.php';
?>
<style>.tl-wrap{max-width:1100px}.tl-grid{display:grid;grid-template-columns:1fr 1.2fr;gap:18px}.tl-card{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:18px}.tl-label{display:block;font-size:11px;font-weight:800;color:#64748b;text-transform:uppercase;letter-spacing:.08em;margin:12px 0 6px}.tl-input,.tl-area{width:100%;box-sizing:border-box;border:1px solid #dbe3ef;border-radius:8px;padding:10px;font:inherit}.tl-area{min-height:280px;font-family:ui-monospace,monospace;font-size:12px}.tl-row{display:flex;justify-content:space-between;gap:12px;align-items:center;padding:13px 0;border-bottom:1px solid #eef2f7}.tl-row:last-child{border-bottom:0}.tl-muted{color:#64748b;font-size:12px;line-height:1.5}.tl-badge{padding:4px 8px;border-radius:999px;background:#eff6ff;color:#1d4ed8;font-size:10px;font-weight:800}.tl-active{background:#f0fdf4;color:#166534}@media(max-width:850px){.tl-grid{grid-template-columns:1fr}}</style>
<main class="content"><div class="tl-wrap"><div class="page-header"><div><h1>Template Library</h1><p class="text-muted">Upload safe HTML templates once, reuse them across the front page, and keep every version tenant-scoped.</p></div></div>
<?php if($flash): ?><div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['msg']) ?></div><?php endif; ?>
<div class="tl-grid"><section class="tl-card"><h2>Import HTML template</h2><p class="tl-muted">Static HTML is normalized through the safe importer. Scripts, inline event handlers, unsafe URLs, and active behavior are removed or rejected.</p><form method="post"><?= csrf_field() ?><input type="hidden" name="_action" value="import"><label class="tl-label">Template name</label><input class="tl-input" name="name" required placeholder="Conversion landing page"><label class="tl-label">Slug</label><input class="tl-input" name="slug" required placeholder="conversion-landing"><label class="tl-label">HTML source</label><textarea class="tl-area" name="source_html" required placeholder="Paste a static HTML template..."></textarea><button class="btn btn-primary" type="submit" style="margin-top:12px">Import to library</button></form></section>
<section class="tl-card"><div style="display:flex;justify-content:space-between;align-items:center"><h2>Reusable templates</h2><span class="tl-badge"><?= count($templates) ?> templates</span></div><p class="tl-muted">Activating a template changes the selected front-page template setting; it does not delete existing page content or revisions.</p><?php if(!$templates): ?><div class="tl-muted" style="padding:30px 0">No templates yet. Import your first static HTML template.</div><?php else: foreach($templates as $t): $isActive=(int)$t['id']===$activeId; ?><div class="tl-row"><div><strong><?= e($t['name']) ?></strong><div class="tl-muted"><?= e($t['slug']) ?> · <?= e($t['content_type']) ?><?php if($isActive): ?> · <span class="tl-badge tl-active">Front page active</span><?php endif; ?></div></div><form method="post" style="margin:0"><?= csrf_field() ?><input type="hidden" name="_action" value="activate"><input type="hidden" name="template_id" value="<?= (int)$t['id'] ?>"><input type="hidden" name="version" value="<?= e((string)($t['active_version']?:'v1')) ?>"><button class="btn btn-sm <?= $isActive?'':'btn-primary' ?>" type="submit"><?= $isActive?'Active':'Use on front page' ?></button></form></div><?php endforeach; endif; ?></section></div></div></main>
<?php include __DIR__.'/partials/footer.php'; ?>

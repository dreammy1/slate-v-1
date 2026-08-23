<?php
/**
 * Slate Visual Editor entry point.
 *
 * The content-builder plugin owns the actual block editor at
 * plugins/content-builder/admin/post-edit.php. This core admin page provides
 * the stable dashboard destination and a small workspace launcher so the
 * Visual Editor navigation item is never a dead or missing route.
 */
require_once dirname(__DIR__) . '/config.php';

Auth::require();
Auth::requirePerm('content.view');

if (!class_exists('ContentBuilderAPI') || !class_exists('PostType')) {
    http_response_code(503);
    echo 'The content builder is not available.';
    exit;
}

$pageTitle  = 'Visual Editor';
$currentNav = 'visual-editor';
$pages      = ContentBuilderAPI::listPosts('page', ['status' => 'any', 'limit' => 50]);

require SLATE_ROOT . '/admin/partials/header.php';
?>

<?php slate_breadcrumbs([
    ['label' => 'Dashboard', 'href' => SLATE_URL . '/admin/'],
    ['label' => 'Visual Editor'],
]); ?>

<div class="page-header">
    <div>
        <h1>Visual Editor</h1>
        <p class="page-header-sub">Build pages visually with reusable sections, templates, and content blocks.</p>
    </div>
    <a class="btn btn-primary" href="<?= e(SLATE_URL) ?>/plugins/content-builder/admin/post-edit.php?type=page">
        + Create a page
    </a>
</div>

<div class="card" style="margin-bottom:16px">
    <div class="card-header"><h2>Page builder</h2></div>
    <p class="text-sub">Choose a page to edit, or start from a ready-made template. Changes are saved through Slate’s normal permission and CSRF-protected editor flow.</p>
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:14px">
        <a class="btn btn-primary" href="<?= e(SLATE_URL) ?>/plugins/content-builder/admin/post-edit.php?type=page">New blank page</a>
        <a class="btn" href="<?= e(SLATE_URL) ?>/plugins/content-builder/admin/posts.php?type=page">Manage all pages</a>
        <a class="btn" href="<?= e(SLATE_URL) ?>/plugins/content-builder/admin/posts.php?type=post">Edit blog posts</a>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2>Recent pages</h2></div>
    <?php if (!$pages): ?>
        <p class="text-muted">No pages have been created yet. Start with a blank page or choose a template.</p>
    <?php else: ?>
        <div class="cb-rows">
            <?php foreach ($pages as $page): ?>
                <div class="cb-row">
                    <div class="cb-row-main">
                        <a href="<?= e(SLATE_URL) ?>/plugins/content-builder/admin/post-edit.php?id=<?= (int)$page['id'] ?>&type=page">
                            <strong><?= e($page['title'] ?: '(untitled)') ?></strong>
                        </a>
                        <div class="text-muted text-sm">
                            <?= e(ucfirst((string)($page['status'] ?? 'draft'))) ?>
                            · <?= e((string)($page['slug'] ?? '')) ?>
                        </div>
                    </div>
                    <a class="btn btn-sm" href="<?= e(SLATE_URL) ?>/plugins/content-builder/admin/post-edit.php?id=<?= (int)$page['id'] ?>&type=page">Edit</a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require SLATE_ROOT . '/admin/partials/footer.php'; ?>

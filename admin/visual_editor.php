<?php
/**
 * Stable Visual Editor entry point.
 *
 * The content-builder plugin owns the actual editor. Keeping this route as a
 * small authenticated redirect avoids duplicating plugin bootstrap logic and
 * ensures the dashboard link always opens the functional page builder.
 */
require_once dirname(__DIR__) . '/config.php';

Auth::require();
Auth::requirePerm('content.view');

header('Location: ' . SLATE_URL . '/plugins/content-builder/admin/post-edit.php?type=page', true, 302);
exit;

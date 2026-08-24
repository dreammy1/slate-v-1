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
// The content-builder editor performs its own capability checks. Avoid a
// second role_permissions lookup here, which can fail on older installations
// before the editor has a chance to render its normal 403 response.
header('Location: ' . SLATE_URL . '/plugins/content-builder/admin/post-edit.php?type=page', true, 302);
exit;

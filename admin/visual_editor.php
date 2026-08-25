<?php
/**
 * Visual Editor launcher.
 *
 * Keep this entry point dependency-free: the content-builder editor owns
 * authentication, permission checks, and all editor rendering. A direct
 * redirect prevents a broken optional admin bootstrap dependency from
 * producing a blank page or HTTP 500 before the editor can load.
 */
header('Location: /plugins/content-builder/admin/post-edit.php?type=page', true, 302);
exit;

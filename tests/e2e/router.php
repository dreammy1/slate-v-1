<?php
/**
 * PHP built-in-server router for Slate E2E tests.
 *
 * Production uses Apache/LiteSpeed .htaccess rules. PHP's development server
 * does not read .htaccess, so this small router mirrors the critical deny rules
 * needed to keep E2E security assertions representative.
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$uri = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = '/' . ltrim(rawurldecode($uri), '/');

$blocked = str_starts_with($path, '/db/')
    || str_starts_with($path, '/includes/')
    || str_starts_with($path, '/scripts/')
    || str_starts_with($path, '/docs/')
    || str_starts_with($path, '/data/')
    || preg_match('/(?:^|\/)(?:\.env|error_log|[^\/]+\.(?:log|bak|orig|save|swp|sql))$/i', $path);

if ($blocked) {
    http_response_code(403);
    require $root . '/403.php';
    exit;
}

$file = realpath($root . $path);
if ($file !== false && str_starts_with($file, $root . DIRECTORY_SEPARATOR) && is_file($file)) {
    return false;
}

// Let the built-in server produce its normal 404 for unknown paths.
return false;

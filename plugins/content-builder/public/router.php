<?php
/**
 * Content Builder — public route handler.
 *
 * Registered on the `public_routes` filter under the 'p' prefix for pages
 * and one prefix per non-page post type. Receives from PublicRouter:
 *   $_GET['_route_prefix']  e.g. 'p' or 'post'
 *   $_GET['_route_path']    e.g. 'about' or 'hello-world'
 *
 * Pages:  /p/<slug>           → type=page
 * Posts:  /<type>/<slug>      → type=<prefix>
 *
 * Draft preview (admin only): append ?preview=1; requires content.view.
 *
 * The full HTML document (header, nav, footer) is produced by Theme.php.
 */

$prefix = (string)($_GET['_route_prefix'] ?? 'p');
$path   = trim((string)($_GET['_route_path'] ?? ''), '/');

$type = ($prefix === 'p') ? 'page' : $prefix;
$slug = $path !== '' ? $path : 'home';

// Only the last path segment is the slug (ignore any nested remainder).
if (str_contains($slug, '/')) {
    $slug = substr($slug, strrpos($slug, '/') + 1);
}

$preview = !empty($_GET['preview']);

$post = ContentBuilderAPI::getPostBySlug($type, $slug);

// Tenant-selected reusable front-page template. Published template documents are
// rendered through a small allow-listed serializer; legacy page content remains
// the fallback when no active template is configured.
if ($slug === 'home') {
    $templateId = (int)Database::setting('front_page_template_id');
    $templateVersion = (string)Database::setting('front_page_template_version');
    if ($templateId > 0 && $templateVersion !== '') {
        $version = Database::row('SELECT tv.definition, t.name FROM template_versions tv JOIN templates t ON t.id = tv.template_id WHERE tv.tenant_id = ? AND tv.template_id = ? AND tv.version = ? AND t.status = ?', [current_tenant_id(), $templateId, $templateVersion, 'active']);
        $payload = $version ? json_decode((string)$version['definition'], true) : null;
        $renderTemplate = static function (array $node) use (&$renderTemplate): string {
            $allowed = ['div','section','header','footer','main','nav','article','aside','h1','h2','h3','p','a','button','img','ul','li','span'];
            $tag = in_array(strtolower((string)($node['tag'] ?? 'div')), $allowed, true) ? strtolower((string)$node['tag']) : 'div';
            $attrs = '';
            foreach ((array)($node['attributes'] ?? []) as $key => $value) {
                $key = preg_replace('/[^a-zA-Z0-9_:.-]/', '', (string)$key) ?? '';
                if ($key === '' || str_starts_with(strtolower($key), 'on') || in_array(strtolower($key), ['style','srcdoc'], true)) continue;
                $value = (string)$value; if (preg_match('/^(?:javascript|data):/i', $value)) continue;
                $attrs .= ' ' . e($key) . '=\"' . e($value) . '\"';
            }
            $inner = trim((string)($node['text'] ?? '')) !== '' ? e((string)$node['text']) : implode('', array_map($renderTemplate, (array)($node['children'] ?? [])));
            return '<' . $tag . $attrs . '>' . $inner . ($tag === 'img' ? '' : '</' . $tag . '>');
        };
        if (is_array($payload['document'] ?? null)) $payload = $payload['document'];
        if (is_array($payload)) {
            $body = implode('', array_map($renderTemplate, (array)($payload['children'] ?? [])));
            echo Theme::renderPage('<title>' . e((string)$version['name']) . '</title>', '<main class=\"cb-page\">' . $body . '</main>', null);
            return;
        }
    }
}

// Preview mode: allow viewing a draft, but only for logged-in users with
// content.view. Everyone else gets the published-only behaviour.
$canPreview = $preview && class_exists('Auth') && Auth::check() && Auth::can('content.view');

if (!$post || ($post['status'] !== 'published' && !$canPreview)) {
    http_response_code(404);
    $headTags = '<title>404 — Not found</title>';
    $bodyHtml = '<main class="cb-page"><h1>404</h1><p>That page could not be found.</p></main>';
    echo Theme::renderPage($headTags, $bodyHtml, null);
    return;
}

$default  = '<title>' . e($post['title'] ?: 'Untitled') . '</title>';
$headTags = Hook::applyFilters('content_head_tags', $default, $post);

// Render mode: 'full_html' outputs the page's raw HTML verbatim (no theme/
// header/footer shell) — for pasting a complete standalone document.
$mode = (string)ContentBuilderAPI::getMeta((int)$post['id'], 'cb_render_mode', 'builder');
if ($mode === 'full_html') {
    // Concatenate the raw content of every 'html' block, output as-is.
    $html = '';
    foreach (($post['layout'] ?? []) as $block) {
        if (($block['type'] ?? '') === 'html') {
            $html .= (string)($block['props']['html'] ?? '');
        }
    }
    if ($post['status'] !== 'published') {
        echo '<div style="background:#fef3c7;color:#92400e;padding:.5rem 1rem;text-align:center;font:14px system-ui">Draft preview — not publicly visible.</div>';
    }
    echo $html;
    return;
}

$banner = ($post['status'] !== 'published')
    ? '<div class="cb-preview-banner">Draft preview — not publicly visible.</div>'
    : '';

// Phase 3B render cutover: route the body through the core Slate\Presentation
// renderer via the bridge, which self-verifies against the legacy renderer and
// serves legacy on any divergence/error (so visitor output is unchanged). Falls
// back to the direct legacy call if the bridge isn't loaded.
$body = class_exists('ContentCoreBridge')
    ? ContentCoreBridge::renderLayoutForPublic($post['layout'])
    : ContentBuilderAPI::renderLayout($post['layout']);

$bodyHtml = $banner . '<main class="cb-page">' . $body . '</main>';

echo Theme::renderPage($headTags, $bodyHtml, $post);

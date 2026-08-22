<?php
/**
 * ContentCoreBridge — Phase 3A B5 bridge between content-builder and the core
 * Slate\Presentation content spine.
 *
 * Two jobs, both purely additive (the live editor and public render path are
 * untouched):
 *
 *   1. REVISION SNAPSHOTS. On every save/publish, append an immutable snapshot of
 *      the post's document to content_revisions (via RevisionStore). Fail-soft:
 *      if the core migration hasn't run (table absent) or anything errors, the
 *      save is never affected.
 *
 *   2. OPT-IN CORE RENDER + PARITY GATE. Expose a path that renders a stored
 *      layout through the core PageRenderer, and a check that it is BYTE-IDENTICAL
 *      to content-builder's legacy Renderer::render(). This proves the core
 *      renderer is at parity on real pages before any future cutover (the Phase-2A
 *      dual-write/parity pattern), without switching production onto it here.
 *
 * Parity is guaranteed by construction: each core Block is a thin adapter that
 * DELEGATES to the legacy \Renderer::renderBlock() for its type, and the adapters
 * carry NO defaults so the core renderer passes props through verbatim. A legacy
 * layout normalizes to one default-layout implicit Section, which the core
 * renderer emits transparently (no wrapper) — so the core output equals the
 * legacy per-block concatenation exactly.
 *
 * Lives in the plugin (not core): it depends on the plugin's global \Renderer /
 * \BlockRegistry / \ContentBuilderAPI, and core must never depend on a plugin.
 */

declare(strict_types=1);

use Slate\Presentation\DocumentSchema;
use Slate\Presentation\FieldSchema;
use Slate\Presentation\RenderContext;
use Slate\Presentation\Rendering\CallbackBlock;
use Slate\Presentation\Rendering\InMemoryBlockRegistry;
use Slate\Presentation\Rendering\PageRenderer;
use Slate\Presentation\Theme\TenantThemeResolver;
use Slate\Presentation\Tokens\TokenEmitter;
use Slate\Services\Content\RevisionStore;
use Slate\Tenancy\TenantContext;

final class ContentCoreBridge
{
    /** owner_type used for content-builder posts in content_revisions. */
    public const OWNER_TYPE = 'content-builder:post';

    private static ?bool $revisionsTable = null;

    /** Wire the hooks. Called from ContentBuilder::boot() after blocks register. */
    public static function register(): void
    {
        Hook::addAction('content_post_saved', [self::class, 'onPostSaved']);
        Hook::addFilter('content_head_tags', [self::class, 'injectHeadTokens']);
        Hook::addAction('admin_head', [self::class, 'injectAdminHead']);
    }

    /**
     * Inject the --slate-* design-token vocabulary into a content page's <head>
     * (Phase 3B, item 2). Themed by the tenant's brand accent. Emitted WITHOUT the
     * `color-scheme` declaration, so on existing pages — which do not yet consume
     * the tokens — this is inert (defines unused custom properties only), a safe
     * prerequisite for blocks/Components adopting them in 3C.
     *
     * Kill-switch: site setting `inject_slate_tokens` = anything but 'on' disables it.
     * Fail-soft: never breaks head assembly.
     */
    public static function injectHeadTokens($headTags, $post = null): string
    {
        $headTags = (string) $headTags;
        if ((string) ContentBuilderAPI::getSiteSetting('inject_slate_tokens', 'on') !== 'on') {
            return $headTags;
        }
        try {
            return $headTags . self::tokenHeadCss() . self::bridgeCss();
        } catch (\Throwable $e) {
            if (function_exists('slate_log')) {
                slate_log('ContentCoreBridge token injection failed: ' . $e->getMessage(), 'warning');
            }
            return $headTags;
        }
    }

    /**
     * Emit the --slate-* vocabulary + consolidation bridge into the ADMIN head
     * (admin_head fires after slate_brand_accent_emit, so the bridge alias wins the
     * cascade). Gated by `token_bridge` = 'on' so admin pages carry nothing extra
     * until consolidation is enabled. Value-preserving: admin `--accent` already
     * resolves to brand_accent_color, which is what `--slate-color-accent` resolves
     * to. Fail-soft.
     */
    public static function injectAdminHead(): void
    {
        if ((string) ContentBuilderAPI::getSiteSetting('token_bridge', 'off') !== 'on') {
            return;
        }
        try {
            echo self::tokenHeadCss() . self::bridgeCss();
        } catch (\Throwable $e) {
            if (function_exists('slate_log')) {
                slate_log('ContentCoreBridge admin head injection failed: ' . $e->getMessage(), 'warning');
            }
        }
    }

    /** The themed --slate-* token block (no color-scheme), shared by public + admin. */
    private static function tokenHeadCss(): string
    {
        $accent = class_exists('Database') ? (string) Database::setting('brand_accent_color') : '';
        $theme  = TenantThemeResolver::fromBrandAccent($accent !== '' ? $accent : null);
        return TokenEmitter::css($theme->tokens(), true, false);
    }

    /**
     * Token-consolidation bridge, slice 1 — ACCENT (docs/09-Roadmap/
     * phase3-consolidation-design.md). Aliases the legacy `--accent`/`--on-accent`
     * to the single `--slate-*` source so both share one value. VALUE-PRESERVING:
     * both already resolve to `brand_accent_color`, so this changes the source of
     * truth, not the rendered color, regardless of cascade order.
     *
     * OFF by default (site setting `token_bridge` must equal 'on'); dormant until a
     * reviewed live verification flips it. Emitted after the token block so the
     * `--slate-*` values it references are defined.
     */
    public static function bridgeCss(): string
    {
        if ((string) ContentBuilderAPI::getSiteSetting('token_bridge', 'off') !== 'on') {
            return '';
        }
        return '<style id="slate-token-bridge">:root{'
            . '--accent:var(--slate-color-accent);'
            . '--on-accent:var(--slate-color-on-accent);'
            . '}</style>';
    }

    // ── 1. Revision snapshots ─────────────────────────────────

    /** Listener for the 'content_post_saved' action (positional: $postId). */
    public static function onPostSaved($postId, $data = null): void
    {
        self::snapshotPost((int) $postId);
    }

    /**
     * Append a revision snapshot of a post's current document. Fail-soft — never
     * throws into a save. Draft/any status → 'working'; published → 'published';
     * trashed posts are skipped.
     */
    public static function snapshotPost(int $postId): void
    {
        if ($postId <= 0 || !self::revisionsAvailable()) {
            return;
        }
        try {
            if (!class_exists('ContentBuilderAPI')) {
                return;
            }
            $post = ContentBuilderAPI::getPost($postId);
            if (!$post) {
                return;
            }
            $status = (string) ($post['status'] ?? 'draft');
            if ($status === 'trash') {
                return;
            }

            $env = DocumentSchema::normalize($post['layout'] ?? [], (string) ($post['type'] ?? 'page'));

            (new RevisionStore(new TenantContext()))->snapshot(
                self::OWNER_TYPE,
                $postId,
                $env,
                $status === 'published' ? RevisionStore::STATUS_PUBLISHED : RevisionStore::STATUS_WORKING,
                self::currentUserId(),
                null,
                (int) $env['schema'],
            );
        } catch (\Throwable $e) {
            if (function_exists('slate_log')) {
                slate_log('ContentCoreBridge snapshot failed: ' . $e->getMessage(), 'warning');
            }
        }
    }

    // ── 2. Core render + parity gate ──────────────────────────

    /**
     * Build a core BlockRegistry whose blocks delegate to the legacy renderer.
     * Rebuilt per call (cheap; ~20 blocks) so newly-registered blocks are picked
     * up. Adapters carry the legacy fields but NO defaults, so the core renderer
     * passes props through verbatim (exact parity).
     */
    public static function coreRegistry(): InMemoryBlockRegistry
    {
        $registry = new InMemoryBlockRegistry();
        if (!class_exists('BlockRegistry') || !class_exists('Renderer')) {
            return $registry;
        }
        foreach (BlockRegistry::all() as $type => $def) {
            $fields = $def['fields'] ?? [];
            $registry->register(new CallbackBlock(
                (string) $type,
                FieldSchema::of($fields, []),                 // no defaults → verbatim props
                static fn (array $props, RenderContext $ctx): string =>
                    Renderer::renderBlock(['type' => (string) $type, 'props' => $props])
            ));
        }
        return $registry;
    }

    /** Render a stored layout through the core PageRenderer. */
    public static function render($layout, ?RenderContext $ctx = null): string
    {
        $ctx ??= RenderContext::for(self::tenantId());
        $page = DocumentSchema::toPage($layout, 'page');
        return (new PageRenderer(self::coreRegistry()))->renderPage($page, $ctx);
    }

    /**
     * True when the core renderer's output for $layout is byte-identical to the
     * legacy Renderer::render(). The parity gate.
     */
    public static function parityHolds($layout): bool
    {
        return self::render($layout) === self::legacyRender($layout);
    }

    /** The legacy render of a layout (accepts a JSON string or an array). */
    public static function legacyRender($layout): string
    {
        if (is_string($layout)) {
            $layout = json_decode($layout, true);
        }
        return class_exists('Renderer') ? Renderer::render(is_array($layout) ? $layout : []) : '';
    }

    /**
     * The public render path (Phase 3B render cutover). Routes a page's body
     * through the core PageRenderer, but is CONSTRUCTED to never change visitor
     * output: it renders both the core and legacy paths and serves the core result
     * ONLY when it is byte-identical to legacy; on any divergence or error it serves
     * legacy and logs. So the live output is provably the same as before, while the
     * core spine is exercised in production and edge cases surface in the log.
     *
     * Kill-switch: set site setting `render_engine` = 'legacy' to bypass the core
     * path entirely (no code change).
     *
     * This is intentionally belt-and-suspenders for the first production cutover;
     * the self-check can be dropped to core-only once confidence is established.
     */
    public static function renderLayoutForPublic($layout): string
    {
        $legacy = self::legacyRender($layout);

        $engine = (string) ContentBuilderAPI::getSiteSetting('render_engine', 'core');
        if ($engine !== 'core') {
            return $legacy;
        }

        try {
            $core = self::render($layout);
        } catch (\Throwable $e) {
            if (function_exists('slate_log')) {
                slate_log('ContentCoreBridge public render failed, using legacy: ' . $e->getMessage(), 'warning');
            }
            return $legacy;
        }

        if ($core !== $legacy) {
            if (function_exists('slate_log')) {
                slate_log('ContentCoreBridge public render diverged from legacy; served legacy for safety', 'warning');
            }
            return $legacy;
        }

        return $core;
    }

    // ── internals ─────────────────────────────────────────────

    private static function revisionsAvailable(): bool
    {
        if (self::$revisionsTable !== null) {
            return self::$revisionsTable;
        }
        try {
            $found = Database::row("SHOW TABLES LIKE 'content_revisions'");
            return self::$revisionsTable = (bool) $found;
        } catch (\Throwable $e) {
            return self::$revisionsTable = false;
        }
    }

    private static function tenantId(): int
    {
        return function_exists('current_tenant_id') ? (int) current_tenant_id() : 1;
    }

    private static function currentUserId(): ?int
    {
        if (class_exists('Auth') && method_exists('Auth', 'userId')) {
            $uid = Auth::userId();
            return $uid ? (int) $uid : null;
        }
        return null;
    }
}

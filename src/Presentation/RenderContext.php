<?php
/**
 * Slate — RenderContext value object (Presentation).
 *
 * The ambient inputs a single render pass carries down the Page → Section →
 * Block traversal (docs/05-Rendering/rendering-pipeline.md): the tenant, the
 * surface being emitted, whether this is a draft preview, and the active Theme.
 *
 * In Phase 3A the Theme layer does not exist yet (that is 3B), so `theme` is a
 * nullable slot: the renderer treats a null theme as "unskinned". Carrying the
 * slot now means 3B plugs a Theme in WITHOUT changing the Renderer contract's
 * signatures — the whole point of threading a context object instead of loose
 * arguments.
 *
 * `surface` selects chrome/fragment behavior downstream (page vs widget vs a
 * bare API fragment, rendering-pipeline.md §1). `preview` flips the SEO stage to
 * noindex and bypasses caching (rendering-pipeline.md §5); the Renderer itself
 * stays identical — preview is the same path with two inputs flipped.
 *
 * Immutable value object (platform-foundation §5). Pure — no DB, no rendering.
 */

declare(strict_types=1);

namespace Slate\Presentation;

final class RenderContext
{
    public const SURFACE_PAGE     = 'page';
    public const SURFACE_WIDGET   = 'widget';
    public const SURFACE_FRAGMENT = 'fragment';

    public function __construct(
        public readonly int $tenantId = 1,
        public readonly string $surface = self::SURFACE_PAGE,
        public readonly bool $preview = false,
        public readonly mixed $theme = null,   // Slate\Presentation\Theme in 3B; null = unskinned
    ) {}

    public static function for(int $tenantId, string $surface = self::SURFACE_PAGE): self
    {
        return new self($tenantId, $surface);
    }

    public function isPreview(): bool { return $this->preview; }
    public function isFragment(): bool { return $this->surface === self::SURFACE_FRAGMENT; }

    public function withPreview(bool $preview = true): self
    {
        return new self($this->tenantId, $this->surface, $preview, $this->theme);
    }

    public function withSurface(string $surface): self
    {
        return new self($this->tenantId, $surface, $this->preview, $this->theme);
    }

    public function withTheme(mixed $theme): self
    {
        return new self($this->tenantId, $this->surface, $this->preview, $theme);
    }
}

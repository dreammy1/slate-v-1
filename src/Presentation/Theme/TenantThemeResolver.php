<?php
/**
 * Slate — TenantThemeResolver: build a Theme from a tenant's brand inputs
 * (Presentation, Phase 3B B2).
 *
 * Maps a tenant's stored brand color to --slate-* token overrides — the new-
 * vocabulary equivalent of the legacy slate_brand_accent_emit(). Overriding the
 * one accent role re-brands every surface that reads it (design-tokens.md §5).
 *
 * PURE by design: Presentation must not read the database (it sits above the
 * Services/Data layers). The caller reads `brand_accent_color` and passes the hex
 * in; this class only transforms values. A missing/default/invalid color yields
 * the DefaultTheme (no overrides), so default installs stay on the token defaults.
 */

declare(strict_types=1);

namespace Slate\Presentation\Theme;

final class TenantThemeResolver
{
    /** The platform default accent — a brand color equal to this adds no override. */
    public const DEFAULT_ACCENT = '#2563EB';

    /**
     * @param string|null $brandAccent  a #RRGGBB hex, or null/empty for the default
     */
    public static function fromBrandAccent(?string $brandAccent): Theme
    {
        $hex = self::normalizeHex($brandAccent);
        if ($hex === null || $hex === self::DEFAULT_ACCENT) {
            return new DefaultTheme();
        }

        return new ArrayTheme(
            tokens: [
                'slate-color-accent'     => $hex,
                'slate-color-on-accent'  => self::readableOn($hex),
                'slate-color-focus-ring' => $hex,
            ],
        );
    }

    // ── internals ─────────────────────────────────────────────

    /** Validate + upper-case a #RRGGBB hex; null if it isn't one. */
    private static function normalizeHex(?string $v): ?string
    {
        $v = trim((string) $v);
        return preg_match('/^#[0-9a-fA-F]{6}$/', $v) ? strtoupper($v) : null;
    }

    /**
     * A readable foreground (#fff or near-black) for text/icons on an accent fill,
     * chosen by relative luminance (WCAG) — the same rule as the legacy emitter.
     */
    private static function readableOn(string $hex): string
    {
        $h = ltrim($hex, '#');
        $lin = static fn (float $c): float =>
            $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        $lum = 0.2126 * $lin(hexdec(substr($h, 0, 2)) / 255)
             + 0.7152 * $lin(hexdec(substr($h, 2, 2)) / 255)
             + 0.0722 * $lin(hexdec(substr($h, 4, 2)) / 255);
        return $lum > 0.55 ? '#16181D' : '#FFFFFF';
    }
}

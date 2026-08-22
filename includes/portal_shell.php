<?php
/**
 * Slate — shared customer portal shell.
 *
 * The customer-side counterpart to admin/partials/header.php. That file is
 * what makes the admin section feel like one product despite eighteen plugins
 * contributing to it: each plugin ships its own pages, and they all `require`
 * one shell that renders one navigation built from `admin_nav_items`.
 *
 * This does the same for customers, off `customer_nav_items`. A plugin's
 * customer page requires this shell instead of hand-rolling its own chrome:
 *
 *     $currentPortalNav = 'studio';                 // marks the active tab
 *     slate_portal_shell_head(__('studio_my_studio', 'My studio'));
 *     slate_portal_shell_open();
 *         … page content, using the portal-app.css kit (pcard/stat/pill/…) …
 *     slate_portal_shell_close();
 *
 * Navigation renders twice from one item list, exactly as admin does: pill
 * tabs in the top bar on desktop, and a fixed bottom tab bar under 768px with
 * the overflow behind "More". CustomerNav (src/Presentation) does the pure
 * work — normalise, sort, group, split — so the rules are unit-tested without
 * a database; everything here is markup and wiring.
 *
 * Gating note: customers have no permission system, so unlike the admin shell
 * this does NOT filter items centrally. Each plugin decides in its own
 * callback whether the item applies to this customer — see the contract
 * documented on CustomerNav.
 */

if (!defined('SLATE_ROOT')) { exit; }

require_once __DIR__ . '/portal_ui.php';   // slate_icon(), slate_portal_asset_url()

use Slate\Presentation\CustomerNav;

if (!function_exists('slate_portal_nav')) {
    /**
     * Resolve the customer navigation for the signed-in customer.
     *
     * Cached per request: the shell renders the same list three times (top
     * tabs, bottom bar, More sheet) and a plugin callback may hit the database
     * to decide whether its item applies.
     *
     * @return array{items: array, groups: array, tabs: array, more: array}
     */
    function slate_portal_nav(?string $activeSlug = null): array
    {
        static $cache = [];
        $key = (string) $activeSlug;
        if (isset($cache[$key])) { return $cache[$key]; }

        // Core destinations seed the list, exactly as $coreNav seeds
        // admin_nav_items — plugins append to them rather than replacing.
        // Low orders keep Home first and Account last whatever plugins pick.
        $core = [
            ['slug' => 'home',    'label' => __('portal_home', 'Home'),
             'href' => SLATE_URL . '/customer/',            'icon' => 'home', 'order' => 10,  'group' => 'main'],
            ['slug' => 'account', 'label' => __('portal_account', 'Account'),
             'href' => SLATE_URL . '/customer/profile.php', 'icon' => 'user', 'order' => 990, 'group' => 'main'],
        ];

        $raw = class_exists('Hook') ? Hook::applyFilters('customer_nav_items', $core) : $core;
        if (!is_array($raw)) { $raw = $core; }

        $items = CustomerNav::resolve($raw, $activeSlug);
        $split = CustomerNav::split($items);

        return $cache[$key] = [
            'items'  => $items,
            'groups' => CustomerNav::group($items),
            'tabs'   => $split['tabs'],
            'more'   => $split['more'],
        ];
    }
}

require_once __DIR__ . '/brand_tokens.php';

if (!function_exists('slate_portal_accent_tokens')) {
    /**
     * Derive readable companions for a tenant's brand accent.
     *
     * A brand colour is chosen to look right, not to be legible under text, and
     * this tenant's (#F89DC1) makes the point: white on it is 1.98:1, so every
     * primary button in the kit — "Save QR", "Pay now" — was failing AA while
     * looking perfectly deliberate. Dark ink on the same pink is 8.96:1.
     *
     * So rather than hardcode #fff, pick per tenant:
     *   on_accent  — text placed ON the accent (buttons, filled avatars)
     *   accent_ink — the accent used AS text, darkened until it clears 4.5:1
     *                on the pale accent-soft tint (active tabs, pills, the
     *                name in the hero). Keeps the brand hue, gains contrast.
     *
     * The maths moved to includes/brand_tokens.php when the admin chrome
     * needed it too. This name stays as the portal's way in.
     *
     * @return array{on_accent:string,accent_ink:string}
     */
    function slate_portal_accent_tokens(string $hex): array
    {
        return slate_brand_accent_tokens($hex);
    }
}

if (!function_exists('slate_portal_avatar')) {
    /**
     * Avatar for a signed-in customer: supplied image → Gravatar → initials.
     *
     * Core has no avatar column (membership keeps one on its profile), so the
     * image is asked for via a filter rather than assumed. Gravatar is loaded
     * with d=404 and the <img> removes itself on error, which lets the initials
     * underneath show through without a flash of a broken image.
     */
    function slate_portal_avatar(?array $cust, string $class = 'mapp-avatar'): string
    {
        $name  = trim((string) ($cust['name'] ?? $cust['email'] ?? ''));
        $email = strtolower(trim((string) ($cust['email'] ?? '')));

        $initials = '';
        foreach (preg_split('/\s+/', $name) as $w) {
            if ($w !== '') { $initials .= mb_substr($w, 0, 1); }
            if (mb_strlen($initials) >= 2) { break; }
        }
        $initials = mb_strtoupper($initials !== '' ? $initials : '?');

        $url = '';
        if (class_exists('Hook')) {
            $url = (string) Hook::applyFilters('customer_portal_avatar', '', (int) ($cust['id'] ?? 0));
        }
        if ($url === '' && $email !== '' && str_contains($email, '@')) {
            $url = 'https://www.gravatar.com/avatar/' . md5($email) . '?s=96&d=404';
        }

        $html = '<span class="' . e($class) . '" title="' . e($name) . '">' . e($initials);
        if ($url !== '') {
            $html .= '<img src="' . e($url) . '" alt="" loading="lazy" onerror="this.remove()">';
        }
        return $html . '</span>';
    }
}

if (!function_exists('slate_portal_shell_head')) {
    /**
     * Opens the document. Mirrors slate_portal_head() but loads the app kit
     * (portal-app.css) rather than portal.css, and sets the brand accent as an
     * inline custom property the way the member app already did — the kit is
     * built on --accent / --accent-deep / --accent-soft / --accent-ring.
     */
    function slate_portal_shell_head(string $title, string $bodyClass = ''): void
    {
        $siteName = Database::setting('site_name') ?: 'Slate';

        $accent = trim((string) Database::setting('brand_accent_color'));
        if (!preg_match('/^#[0-9a-fA-F]{3,8}$/', $accent)) { $accent = '#2563EB'; }
        $accentTokens = slate_portal_accent_tokens($accent);

        $cls = trim('mapp ' . $bodyClass);
        ?>
<!DOCTYPE html>
<html lang="<?= e(I18n::currentLocale()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="<?= e($accent) ?>">
    <title><?= e($title) ?> — <?= e($siteName) ?></title>
    <meta name="csrf" content="<?= e(csrf_token()) ?>">
    <?php slate_ui_emit_css(); ?>
    <link rel="stylesheet" href="<?= e(slate_portal_asset_url('assets/css/portal-app.css')) ?>">
    <style>
    :root{
        --accent:<?= $accent ?>;
        --accent-deep:color-mix(in srgb,<?= $accent ?> 84%, #000);
        --accent-soft:color-mix(in srgb,<?= $accent ?> 9%, #fff);
        --accent-ring:color-mix(in srgb,<?= $accent ?> 22%, transparent);
        /* Derived per tenant — see slate_portal_accent_tokens(). --on-accent is
           whichever of white/ink reads on this brand; --accent-ink is the brand
           hue darkened until it clears AA as text. */
        --on-accent:<?= $accentTokens['on_accent'] ?>;
        --accent-ink:<?= $accentTokens['accent_ink'] ?>;
    }
    <?php slate_portal_shell_css(); ?>
    </style>
    <?php require SLATE_ROOT . '/includes/a11y_head.php'; ?>
    <?php
    if (class_exists('Hook')) { Hook::doAction('customer_head'); }
    if (class_exists('PluginLoader')) { echo PluginLoader::renderQueuedStyles(); }
    ?>
</head>
<body class="<?= e($cls) ?>">
<a class="skip-link" href="#portal-main"><?= __('skip_to_content', 'Skip to main content') ?></a>
<?php
    }
}

if (!function_exists('slate_portal_shell_css')) {
    /** The few rules the app kit doesn't already ship: More sheet + empty nav. */
    function slate_portal_shell_css(): void
    {
        ?>
    /* "More" overflow sheet — the mobile counterpart to the admin nav's More
       sheet. Closed by default; opened by the tab bar's More button. */
    .mapp-more[hidden]{display:none;}
    .mapp-more{position:fixed;inset:0;z-index:60;display:grid;align-items:end;}
    .mapp-more-scrim{position:absolute;inset:0;background:rgba(15,18,26,.42);border:0;padding:0;}
    .mapp-more-panel{position:relative;background:var(--m-surface,#fff);border-radius:20px 20px 0 0;
        padding:10px 10px calc(14px + env(safe-area-inset-bottom));max-height:70vh;overflow:auto;}
    .mapp-more-grip{width:38px;height:4px;border-radius:999px;background:var(--m-line,#ECEEF1);margin:6px auto 10px;}
    .mapp-more a{display:flex;align-items:center;gap:13px;padding:13px 14px;border-radius:13px;
        font-size:15px;font-weight:600;color:var(--m-ink,#15181E);}
    .mapp-more a:hover{background:var(--m-bg,#F4F5F7);}
    .mapp-more a.on{background:var(--accent-soft);color:var(--accent);}
    .mapp-more a svg{width:20px;height:20px;flex:none;}
    .mapp-tabbar button.mapp-more-btn{flex:1;display:flex;flex-direction:column;align-items:center;gap:3px;
        padding:7px 2px;border:0;background:none;border-radius:12px;color:var(--m-muted,#737886);
        font:inherit;font-size:10.5px;font-weight:600;cursor:pointer;}
    .mapp-tabbar button.mapp-more-btn svg{width:23px;height:23px;}
    /* The kit's .stat-grid is a fixed 3-up. The portal can't know how many KPIs
       plugins will contribute, and a 4th on a 3-col grid strands itself on its
       own row — so the portal home uses an auto-fit track instead. */
    .stat-grid--auto{grid-template-columns:repeat(auto-fit,minmax(132px,1fr));}
    /* Section sub-nav — the local counterpart to the global tabs, for a plugin
       area with several pages of its own (Membership: Plans/Card/Schedule/…).
       Global nav lives in the bar; this sits at the top of the content, the way
       admin pairs a global sidebar with page-level tabs. */
    /* Sticks directly under the top bar (62px) instead of scrolling away behind
       it — otherwise the section tabs slide half under the bar the moment you
       scroll, which is what the portal screenshot caught. Own background so
       content can't show through the gap. */
    .mapp-subnav{
        position:sticky;top:62px;z-index:30;
        display:flex;gap:4px;overflow-x:auto;
        margin:0 0 20px;padding:10px 0 12px;
        background:var(--m-bg,#F4F5F7);
        box-shadow:0 1px 0 var(--m-line,#ECEEF1);
        scrollbar-width:none;
    }
    .mapp-subnav::-webkit-scrollbar{display:none;}
    .mapp-subnav a{display:flex;align-items:center;gap:7px;padding:9px 14px;border-radius:999px;
        font-size:14px;font-weight:600;color:var(--m-muted,#737886);white-space:nowrap;transition:.15s;}
    .mapp-subnav a:hover{background:var(--m-bg,#F4F5F7);color:var(--m-ink,#15181E);}
    .mapp-subnav a.on{background:var(--accent-soft);color:var(--accent);}
    .mapp-subnav a svg{width:18px;height:18px;}
    /* A nav badge (e.g. unpaid tuition) on both the pill tab and the bar. */
    .mapp-badge{display:inline-grid;place-items:center;min-width:17px;height:17px;padding:0 5px;
        border-radius:999px;background:var(--m-amber,#D97706);color:#fff;font-size:10.5px;font-weight:800;}
    .mapp-tabbar .mapp-badge{position:absolute;transform:translate(12px,-8px);}
    .mapp-tabbar a{position:relative;}
        <?php
    }
}

if (!function_exists('slate_portal_shell_open')) {
    /**
     * Renders the top bar (brand + nav + account) and opens <main>.
     *
     * @param array $opts 'active' => slug (defaults to $currentPortalNav),
     *                    'title'  => page title shown to assistive tech.
     */
    function slate_portal_shell_open(array $opts = []): void
    {
        $active = (string) ($opts['active'] ?? $GLOBALS['currentPortalNav'] ?? '');
        $nav    = slate_portal_nav($active !== '' ? $active : null);

        $siteName = Database::setting('site_name') ?: 'Slate';
        $logoRel  = trim((string) Database::setting('brand_logo_path'));
        $logoUrl  = $logoRel !== '' ? SLATE_URL . '/' . ltrim($logoRel, '/') : '';
        $cust     = class_exists('Auth') ? Auth::customer() : null;
        $name     = (string) ($cust['name'] ?? $cust['email'] ?? '');
        $initial  = mb_strtoupper(mb_substr($name !== '' ? $name : $siteName, 0, 1));
        $home     = SLATE_URL . '/customer/';
        ?>
<header class="mapp-bar">
    <div class="mapp-bar-in">
        <a href="<?= e($home) ?>" class="mapp-brand" aria-label="<?= e($siteName) ?>">
            <?php if ($logoUrl !== ''): ?>
                <img src="<?= e($logoUrl) ?>" alt="<?= e($siteName) ?>">
            <?php else: ?>
                <span class="mapp-brand-mark"><?= e(mb_strtoupper(mb_substr($siteName, 0, 1))) ?></span>
            <?php endif; ?>
        </a>

        <?php if ($nav['items']): ?>
        <nav class="mapp-toptabs" aria-label="<?= e(__('portal_nav', 'Portal')) ?>">
            <?php foreach ($nav['items'] as $i): ?>
                <a href="<?= e($i['href']) ?>" class="<?= $i['is_active'] ? 'on' : '' ?>"
                   <?= $i['is_active'] ? 'aria-current="page"' : '' ?>>
                    <?= slate_icon($i['icon'], 'icon') ?><span><?= e($i['label']) ?></span>
                    <?php if ($i['badge'] !== ''): ?><span class="mapp-badge"><?= e($i['badge']) ?></span><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <?php endif; ?>

        <div class="mapp-bar-end">
            <?php
            // Plugin-supplied bar controls, mirroring admin_topbar_actions.
            // Membership's FR/EN switch is the reason this exists: it belongs to
            // the bar, not the page, and would otherwise be lost when a plugin
            // stops rendering its own chrome.
            $barActions = class_exists('Hook') ? Hook::applyFilters('customer_portal_bar_actions', []) : [];
            if (is_array($barActions)) { foreach ($barActions as $html) { echo $html; } }
            ?>
            <?php if ($cust): ?>
                <?= slate_portal_avatar($cust) ?>
                <a href="<?= e(SLATE_URL) ?>/customer/logout.php?csrf=<?= e(csrf_token()) ?>"
                   class="mapp-signout" title="<?= e(__('sign_out', 'Sign out')) ?>"
                   aria-label="<?= e(__('sign_out', 'Sign out')) ?>">
                    <?= slate_icon('logout', 'icon') ?>
                </a>
            <?php else: ?>
                <a href="<?= e(SLATE_URL) ?>/customer/login.php" class="mbtn mbtn-ghost"><?= __('sign_in', 'Sign in') ?></a>
            <?php endif; ?>
        </div>
    </div>
</header>

<main class="mapp-main" id="portal-main">
<?php
    }
}

if (!function_exists('slate_portal_welcome')) {
    /**
     * The welcome hero — the customer-side twin of the admin dashboard's.
     *
     * Named _welcome, not _hero, because portal_ui.php already has
     * slate_portal_hero() (the older gradient banner) and both files are loaded
     * on these pages.
     *
     * @param array $o eyebrow, title, name (accent-gradient), sub,
     *                 status => ['label'=>…, 'tone'=>'green'|'amber'], clock => bool
     */
    function slate_portal_welcome(array $o = []): void
    {
        $name   = trim((string) ($o['name'] ?? ''));
        $status = is_array($o['status'] ?? null) ? $o['status'] : null;
        ?>
        <div class="phero">
            <div class="phero-main">
                <?php if (!empty($o['eyebrow'])): ?>
                    <div class="phero-eyebrow"><?= e((string) $o['eyebrow']) ?></div>
                <?php endif; ?>
                <h1 class="phero-title"><?= e((string) ($o['title'] ?? ''))
                    ?><?php if ($name !== ''): ?>, <span class="name"><?= e($name) ?></span><?php endif; ?>.</h1>
                <?php if (!empty($o['sub'])): ?>
                    <p class="phero-sub"><?= e((string) $o['sub']) ?></p>
                <?php endif; ?>
            </div>
            <div class="phero-side">
                <?php if ($status !== null && ($status['label'] ?? '') !== ''): ?>
                    <span class="phero-status<?= ($status['tone'] ?? '') === 'amber' ? ' is-amber' : '' ?>">
                        <span class="dot"></span> <?= e((string) $status['label']) ?>
                    </span>
                <?php endif; ?>
                <?php if (!empty($o['clock'])): ?>
                    <span class="phero-clock"><?= e(date('D · j M Y')) ?> · <span class="t"><?= e(date('g:i a')) ?></span></span>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}

if (!function_exists('slate_portal_stats')) {
    /**
     * The KPI card row. Same shape plugins already use for
     * `customer_dashboard_kpis`: label, value, icon, and an optional `sub`
     * caption and `href` that makes the whole card a link.
     *
     * @param array $stats [['label'=>…, 'value'=>…, 'icon'=>…, 'sub'=>…, 'href'=>…], …]
     */
    function slate_portal_stats(array $stats): void
    {
        $stats = array_values(array_filter($stats, 'is_array'));
        if (!$stats) { return; }
        echo '<div class="pstats">';
        foreach ($stats as $s) {
            $icon  = (string) ($s['icon'] ?? 'chart');
            $value = (string) ($s['value'] ?? '—');
            $href  = trim((string) ($s['href'] ?? ''));
            // A currency figure needs more room than a count before it wraps.
            $money = $value !== '' && preg_match('/[^\d\s,.\-]/u', $value) === 1;

            echo $href !== '' ? '<a class="pstat" href="' . e($href) . '">' : '<div class="pstat">';
            echo   '<div class="pstat-top">'
                 . '<span class="pstat-badge">' . slate_icon($icon, '') . '</span>'
                 . '<span class="pstat-num' . ($money ? ' is-money' : '') . '">' . e($value) . '</span>'
                 . '</div>'
                 . '<div class="pstat-k">' . e((string) ($s['label'] ?? '')) . '</div>';
            if (($s['sub'] ?? '') !== '') {
                echo '<div class="pstat-s">' . e((string) $s['sub']) . '</div>';
            }
            echo   '<span class="pstat-wm">' . slate_icon($icon, '') . '</span>';
            echo $href !== '' ? '</a>' : '</div>';
        }
        echo '</div>';
    }
}

if (!function_exists('slate_portal_subnav')) {
    /**
     * Section navigation for a plugin area with several pages of its own.
     *
     * The global nav in the bar answers "which part of my account am I in";
     * this answers "which page within it" — the same split admin makes between
     * its sidebar and a page's own tabs. Call it as the first thing inside
     * slate_portal_shell_open().
     *
     * @param array $items  [['href'=>…, 'label'=>…, 'icon'=>…, 'active'=>bool], …]
     */
    function slate_portal_subnav(array $items): void
    {
        $items = array_values(array_filter($items, 'is_array'));
        if (count($items) < 2) { return; }   // one tab is not navigation
        echo '<nav class="mapp-subnav" aria-label="' . e(__('portal_section_nav', 'Section')) . '">';
        foreach ($items as $i) {
            $on = !empty($i['active']);
            echo '<a href="' . e((string) ($i['href'] ?? '#')) . '"' . ($on ? ' class="on" aria-current="page"' : '') . '>'
               . (isset($i['icon']) ? slate_icon((string) $i['icon'], 'icon') : '')
               . '<span>' . e((string) ($i['label'] ?? '')) . '</span></a>';
        }
        echo '</nav>';
    }
}

if (!function_exists('slate_portal_shell_close')) {
    /** Closes <main>, then renders the mobile tab bar + More sheet. */
    function slate_portal_shell_close(): void
    {
        $nav = slate_portal_nav($GLOBALS['currentPortalNav'] ?? null);
        if (!$nav['items']) { echo "</main>\n</body>\n</html>\n"; return; }
        ?>
</main>

<nav class="mapp-tabbar" aria-label="<?= e(__('portal_nav', 'Portal')) ?>">
    <?php foreach ($nav['tabs'] as $i): ?>
        <a href="<?= e($i['href']) ?>" class="<?= $i['is_active'] ? 'on' : '' ?>"
           <?= $i['is_active'] ? 'aria-current="page"' : '' ?>>
            <?= slate_icon($i['icon'], 'icon') ?><span><?= e($i['label']) ?></span>
            <?php if ($i['badge'] !== ''): ?><span class="mapp-badge"><?= e($i['badge']) ?></span><?php endif; ?>
        </a>
    <?php endforeach; ?>
    <?php if ($nav['more']): ?>
        <button type="button" class="mapp-more-btn" data-portal-more
                aria-expanded="false" aria-controls="portal-more">
            <?= slate_icon('menu', 'icon') ?><span><?= __('more', 'More') ?></span>
        </button>
    <?php endif; ?>
</nav>

<?php if ($nav['more']): ?>
<div class="mapp-more" id="portal-more" hidden>
    <button type="button" class="mapp-more-scrim" data-portal-more-close
            aria-label="<?= e(__('close', 'Close')) ?>"></button>
    <div class="mapp-more-panel" role="dialog" aria-modal="true"
         aria-label="<?= e(__('more', 'More')) ?>">
        <div class="mapp-more-grip"></div>
        <?php foreach ($nav['more'] as $i): ?>
            <a href="<?= e($i['href']) ?>" class="<?= $i['is_active'] ? 'on' : '' ?>">
                <?= slate_icon($i['icon'], 'icon') ?><span><?= e($i['label']) ?></span>
                <?php if ($i['badge'] !== ''): ?><span class="mapp-badge" style="margin-left:auto"><?= e($i['badge']) ?></span><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>
<script>
(function () {
    var sheet = document.getElementById('portal-more');
    var btn   = document.querySelector('[data-portal-more]');
    if (!sheet || !btn) return;
    function set(open) {
        sheet.hidden = !open;
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        // Stop the page scrolling behind an open sheet.
        document.body.style.overflow = open ? 'hidden' : '';
        if (open) { var a = sheet.querySelector('a'); if (a) a.focus(); }
    }
    btn.addEventListener('click', function () { set(sheet.hidden); });
    sheet.addEventListener('click', function (e) {
        if (e.target.closest('[data-portal-more-close]')) set(false);
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !sheet.hidden) { set(false); btn.focus(); }
    });
})();
</script>
<?php endif; ?>

</body>
</html>
<?php
    }
}

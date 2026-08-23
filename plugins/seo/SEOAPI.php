<?php
/**
 * SEO — Public API.
 *
 * Other plugins/themes can call SEOAPI::renderHeadTags($post) to get the
 * SEO <head> markup, or read/write site-wide defaults.
 */

class SEOAPI {

    /* ---- Site-wide settings ---- */

    public static function getSetting(string $key, $default = '', ?int $tenantId = null) {
        $tenantId = $tenantId ?? current_tenant_id();
        $val = Database::value(
            "SELECT setting_val FROM seo_settings WHERE tenant_id = ? AND setting_key = ?",
            [$tenantId, $key]);
        return $val === null ? $default : $val;
    }

    public static function setSetting(string $key, ?string $val, ?int $tenantId = null): void {
        $tenantId = $tenantId ?? current_tenant_id();
        Database::query(
            "INSERT INTO seo_settings (tenant_id, setting_key, setting_val)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE setting_val = VALUES(setting_val)",
            [$tenantId, $key, $val]);
    }

    public static function allSettings(?int $tenantId = null): array {
        $tenantId = $tenantId ?? current_tenant_id();
        $rows = Database::rows(
            "SELECT setting_key, setting_val FROM seo_settings WHERE tenant_id = ?",
            [$tenantId]);
        $out = [];
        foreach ($rows as $r) $out[$r['setting_key']] = $r['setting_val'];
        return $out;
    }

    /**
     * Normalise and validate site-wide SEO values before persistence.
     * Returns [clean values, validation errors].
     */
    public static function validateSettings(array $input): array {
        $clean = [];
        $errors = [];
        $textKeys = [
            'site_name' => 120,
            'title_suffix' => 120,
            'default_description' => 320,
            'organization_name' => 160,
            'social_twitter' => 80,
        ];
        foreach ($textKeys as $key => $max) {
            $value = (string)($input[$key] ?? '');
            $value = preg_replace('/[\\x00-\\x08\\x0B-\\x1F\\x7F]+/', '', $value) ?? '';
            $value = trim(strip_tags($value));
            $clean[$key] = mb_substr($value, 0, $max);
        }

        foreach (['canonical_host', 'default_og_image', 'organization_url', 'organization_logo'] as $key) {
            $value = trim((string)($input[$key] ?? ''));
            if ($value !== '' && !filter_var($value, FILTER_VALIDATE_URL)) {
                $errors[$key] = 'Enter a complete HTTPS URL.';
            } elseif ($value !== '' && (string)parse_url($value, PHP_URL_SCHEME) !== 'https') {
                $errors[$key] = 'SEO URLs must use HTTPS.';
            }
            $clean[$key] = mb_substr($value, 0, 500);
        }

        $locale = trim((string)($input['locale'] ?? 'en_US'));
        if (!preg_match('/^[a-z]{2}(?:_[A-Z]{2})?$/', $locale)) {
            $errors['locale'] = 'Use a locale such as en_US or bn_BD.';
            $locale = 'en_US';
        }
        $clean['locale'] = $locale;

        $robots = (string)($input['robots_default'] ?? 'index,follow');
        if (!in_array($robots, ['index,follow', 'noindex,nofollow', 'noindex,follow'], true)) {
            $errors['robots_default'] = 'Choose a supported robots policy.';
            $robots = 'index,follow';
        }
        $clean['robots_default'] = $robots;
        return [$clean, $errors];
    }

    /**
     * Build the SEO <head> markup for a given post.
     * Falls back to the post title and site-wide defaults.
     */
    public static function renderHeadTags(array $post, string $prepend = ''): string {
        $postId = (int)($post['id'] ?? 0);

        $meta = (class_exists('ContentBuilderAPI') && $postId)
            ? ContentBuilderAPI::getAllMeta($postId)
            : [];

        $settings = self::allSettings();
        $siteName = trim((string)($settings['site_name'] ?? ''));
        $suffix = trim((string)($settings['title_suffix'] ?? ''));
        $defaultDesc = trim((string)($settings['default_description'] ?? ''));
        $defaultImage = trim((string)($settings['default_og_image'] ?? ''));
        $canonicalHost = rtrim(trim((string)($settings['canonical_host'] ?? '')), '/');
        $locale = trim((string)($settings['locale'] ?? 'en_US')) ?: 'en_US';
        $robotsDefault = trim((string)($settings['robots_default'] ?? 'index,follow')) ?: 'index,follow';

        $title = trim((string)($meta['seo_title'] ?? '')) ?: trim((string)($post['title'] ?? 'Untitled'));
        if ($suffix !== '' && stripos($title, $suffix) === false) $title .= ' ' . $suffix;
        $desc = trim((string)($meta['seo_description'] ?? '')) ?: $defaultDesc;
        $canon = trim((string)($meta['seo_canonical'] ?? ''));
        if ($canon === '' && $canonicalHost !== '') {
            $slug = trim((string)($post['slug'] ?? $post['post_slug'] ?? ''));
            if ($slug !== '') $canon = $canonicalHost . '/' . ltrim($slug, '/');
        }
        $ogimg = trim((string)($meta['seo_og_image'] ?? '')) ?: $defaultImage;
        $noindex = !empty($meta['seo_noindex']);
        $robots = $noindex ? 'noindex,nofollow' : $robotsDefault;

        $out = $prepend;
        $out = preg_replace('#<title>.*?</title>#i', '', $out);
        $out .= '<title>' . e($title) . '</title>' . "\n";
        if ($desc !== '') $out .= '<meta name="description" content="' . e($desc) . '">' . "\n";
        $out .= '<meta name="robots" content="' . e($robots) . '">' . "\n";
        $out .= '<meta property="og:type" content="website">' . "\n";
        $out .= '<meta property="og:title" content="' . e($title) . '">' . "\n";
        if ($desc !== '') $out .= '<meta property="og:description" content="' . e($desc) . '">' . "\n";
        if ($siteName !== '') $out .= '<meta property="og:site_name" content="' . e($siteName) . '">' . "\n";
        $out .= '<meta property="og:locale" content="' . e($locale) . '">' . "\n";
        if ($canon !== '') {
            $out .= '<link rel="canonical" href="' . e($canon) . '">' . "\n";
            $out .= '<meta property="og:url" content="' . e($canon) . '">' . "\n";
        }
        if ($ogimg !== '') {
            $out .= '<meta property="og:image" content="' . e($ogimg) . '">' . "\n";
            $out .= '<meta name="twitter:card" content="summary_large_image">' . "\n";
            $out .= '<meta name="twitter:image" content="' . e($ogimg) . '">' . "\n";
        } else {
            $out .= '<meta name="twitter:card" content="summary">' . "\n";
        }
        $out .= '<meta name="twitter:title" content="' . e($title) . '">' . "\n";
        if ($desc !== '') $out .= '<meta name="twitter:description" content="' . e($desc) . '">' . "\n";

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => $title,
            'inLanguage' => str_replace('_', '-', $locale),
        ];
        if ($desc !== '') $schema['description'] = $desc;
        if ($canon !== '') $schema['url'] = $canon;
        $orgName = trim((string)($settings['organization_name'] ?? $siteName));
        $orgUrl = trim((string)($settings['organization_url'] ?? $canonicalHost));
        $orgLogo = trim((string)($settings['organization_logo'] ?? ''));
        if ($orgName !== '') {
            $schema['isPartOf'] = ['@type' => 'WebSite', 'name' => $orgName];
            if ($orgUrl !== '') $schema['isPartOf']['url'] = $orgUrl;
        }
        $jsonFlags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
        $out .= '<script type="application/ld+json">' . json_encode($schema, $jsonFlags) . '</script>' . "\n";
        if ($orgName !== '' && ($orgUrl !== '' || $orgLogo !== '')) {
            $org = ['@context'=>'https://schema.org', '@type'=>'Organization', 'name'=>$orgName];
            if ($orgUrl !== '') $org['url'] = $orgUrl;
            if ($orgLogo !== '') $org['logo'] = $orgLogo;
            $out .= '<script type="application/ld+json">' . json_encode($org, $jsonFlags) . '</script>' . "\n";
        }
        return $out;
    }
}

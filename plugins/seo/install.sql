-- SEO plugin schema.
-- Per-post SEO data is stored via ContentBuilderAPI::setMeta() (the shared
-- contentbuilder_post_meta table), so SEO needs only a small table for
-- site-wide defaults.

CREATE TABLE IF NOT EXISTS `seo_settings` (
    `tenant_id`     INT UNSIGNED NOT NULL DEFAULT 1,
    `setting_key`   VARCHAR(64)  NOT NULL,
    `setting_val`   TEXT NULL,
    PRIMARY KEY (`tenant_id`, `setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `seo_settings` (tenant_id, setting_key, setting_val) VALUES
    (1, 'site_name', ''),
    (1, 'title_suffix', ''),
    (1, 'default_description', ''),
    (1, 'canonical_host', ''),
    (1, 'default_og_image', ''),
    (1, 'locale', 'en_US'),
    (1, 'robots_default', 'index,follow'),
    (1, 'organization_name', ''),
    (1, 'organization_url', ''),
    (1, 'organization_logo', ''),
    (1, 'social_twitter', '');

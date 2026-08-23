<?php
/**
 * 0005_theme_template_library — reusable themes/templates and asset quarantine.
 * Additive only: existing renderers and media_files remain unchanged.
 */
declare(strict_types=1);

use Slate\Data\Schema\Schema;
use Slate\Data\Schema\Table;

return new class extends \Slate\Data\Migration {
    public function up(Schema $s): void
    {
        $s->create('themes', function (Table $t) {
            $t->id();
            $t->int('tenant_id')->unsigned()->default(1);
            $t->string('name', 160);
            $t->string('slug', 160);
            $t->string('status', 20)->default('draft');
            $t->string('active_version', 32)->nullable();
            $t->string('schema_version', 16)->default('1.0');
            $t->string('preview_path', 500)->nullable();
            $t->datetime('created_at')->useCurrent();
            $t->datetime('updated_at')->useCurrent();
            $t->unique(['tenant_id', 'slug'], 'uniq_theme_slug');
            $t->index(['tenant_id', 'status'], 'idx_theme_status');
        });
        $s->create('theme_versions', function (Table $t) {
            $t->id();
            $t->int('tenant_id')->unsigned()->default(1);
            $t->int('theme_id')->unsigned();
            $t->string('version', 32);
            $t->json('definition');
            $t->string('package_checksum', 128)->nullable();
            $t->int('author_id')->unsigned()->nullable();
            $t->datetime('created_at')->useCurrent();
            $t->unique(['tenant_id', 'theme_id', 'version'], 'uniq_theme_version');
            $t->index(['tenant_id', 'theme_id'], 'idx_theme_versions');
        });
        $s->create('templates', function (Table $t) {
            $t->id();
            $t->int('tenant_id')->unsigned()->default(1);
            $t->string('name', 160);
            $t->string('slug', 160);
            $t->string('status', 20)->default('draft');
            $t->string('content_type', 64)->default('landing');
            $t->string('active_version', 32)->nullable();
            $t->string('schema_version', 16)->default('1.0');
            $t->string('preview_path', 500)->nullable();
            $t->datetime('created_at')->useCurrent();
            $t->datetime('updated_at')->useCurrent();
            $t->unique(['tenant_id', 'slug'], 'uniq_template_slug');
            $t->index(['tenant_id', 'content_type', 'status'], 'idx_template_type');
        });
        $s->create('template_versions', function (Table $t) {
            $t->id();
            $t->int('tenant_id')->unsigned()->default(1);
            $t->int('template_id')->unsigned();
            $t->string('version', 32);
            $t->json('definition');
            $t->longText('source_html')->nullable();
            $t->longText('source_css')->nullable();
            $t->json('warnings')->nullable();
            $t->string('package_checksum', 128)->nullable();
            $t->int('author_id')->unsigned()->nullable();
            $t->datetime('created_at')->useCurrent();
            $t->unique(['tenant_id', 'template_id', 'version'], 'uniq_template_version');
            $t->index(['tenant_id', 'template_id'], 'idx_template_versions');
        });
        $s->create('asset_quarantine', function (Table $t) {
            $t->id();
            $t->int('tenant_id')->unsigned()->default(1);
            $t->string('original_name', 255);
            $t->string('stored_path', 500);
            $t->string('mime', 120)->default('');
            $t->int('size_bytes')->unsigned()->default(0);
            $t->string('checksum', 128);
            $t->enum('status', ['quarantined', 'approved', 'rejected'])->default('quarantined');
            $t->string('reason', 500)->nullable();
            $t->int('media_id')->unsigned()->nullable();
            $t->int('uploaded_by')->unsigned()->nullable();
            $t->datetime('created_at')->useCurrent();
            $t->datetime('reviewed_at')->nullable();
            $t->index(['tenant_id', 'status'], 'idx_quarantine_status');
            $t->unique(['tenant_id', 'checksum'], 'uniq_quarantine_checksum');
        });
    }

    public function down(Schema $s): void
    {
        $s->dropIfExists('asset_quarantine');
        $s->dropIfExists('template_versions');
        $s->dropIfExists('templates');
        $s->dropIfExists('theme_versions');
        $s->dropIfExists('themes');
    }
};

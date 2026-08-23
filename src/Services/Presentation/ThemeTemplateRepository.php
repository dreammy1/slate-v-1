<?php
declare(strict_types=1);

namespace Slate\Services\Presentation;

use Slate\Presentation\Theme\ThemeDefinition;
use Slate\Presentation\Templates\TemplateDefinition;

final class ThemeTemplateRepository
{
    public function createTheme(ThemeDefinition $definition, ?int $authorId = null): int
    {
        $tid = current_tenant_id();
        $id = (int)\Database::insert('themes', ['tenant_id'=>$tid,'name'=>$definition->name,'slug'=>$definition->slug,'status'=>$definition->status,'schema_version'=>$definition->schemaVersion]);
        $this->saveThemeVersion($id, $definition, $authorId, '1.0');
        return $id;
    }

    public function saveThemeVersion(int $themeId, ThemeDefinition $definition, ?int $authorId = null, string $version = ''): int
    {
        $tid = current_tenant_id();
        $version = $version !== '' ? $version : $this->nextVersion('theme_versions', 'theme_id', $themeId);
        return (int)\Database::insert('theme_versions', ['tenant_id'=>$tid,'theme_id'=>$themeId,'version'=>$version,'definition'=>json_encode($definition->toArray(), JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),'author_id'=>$authorId]);
    }

    public function createTemplate(TemplateDefinition $definition, ?int $authorId = null): int
    {
        $tid = current_tenant_id();
        $id = (int)\Database::insert('templates', ['tenant_id'=>$tid,'name'=>$definition->name,'slug'=>$definition->slug,'status'=>$definition->status,'content_type'=>$definition->contentTypes[0] ?? 'landing','schema_version'=>$definition->schemaVersion]);
        $this->saveTemplateVersion($id, $definition, [], '', [], $authorId, '1.0');
        return $id;
    }

    /** @param array<string,mixed> $document @param list<string> $warnings */
    public function saveTemplateVersion(int $templateId, TemplateDefinition $definition, array $document = [], string $sourceHtml = '', array $warnings = [], ?int $authorId = null, string $version = ''): int
    {
        $tid = current_tenant_id();
        $version = $version !== '' ? $version : $this->nextVersion('template_versions', 'template_id', $templateId);
        $payload = $definition->toArray();
        if ($document !== []) $payload['document'] = $document;
        return (int)\Database::insert('template_versions', ['tenant_id'=>$tid,'template_id'=>$templateId,'version'=>$version,'definition'=>json_encode($payload, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),'source_html'=>$sourceHtml,'source_css'=>'','warnings'=>json_encode(array_values($warnings), JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),'author_id'=>$authorId]);
    }

    public function activateTheme(int $themeId, string $version): bool
    {
        $tid = current_tenant_id();
        $exists = \Database::value('SELECT id FROM theme_versions WHERE tenant_id = ? AND theme_id = ? AND version = ?', [$tid, $themeId, $version]);
        if (!$exists) return false;
        \Database::update('themes', ['status'=>'active','active_version'=>$version,'updated_at'=>date('Y-m-d H:i:s')], 'tenant_id = ? AND id = ?', [$tid, $themeId]);
        return true;
    }

    public function activateTemplate(int $templateId, string $version): bool
    {
        $tid = current_tenant_id();
        $exists = \Database::value('SELECT id FROM template_versions WHERE tenant_id = ? AND template_id = ? AND version = ?', [$tid, $templateId, $version]);
        if (!$exists) return false;
        \Database::update('templates', ['status'=>'active','active_version'=>$version,'updated_at'=>date('Y-m-d H:i:s')], 'tenant_id = ? AND id = ?', [$tid, $templateId]);
        return true;
    }

    /** @return list<array<string,mixed>> */
    public function listThemes(): array { return \Database::rows('SELECT * FROM themes WHERE tenant_id = ? ORDER BY updated_at DESC, id DESC', [current_tenant_id()]); }
    /** @return list<array<string,mixed>> */
    public function listTemplates(?string $contentType = null): array {
        $sql = 'SELECT * FROM templates WHERE tenant_id = ?'; $args = [current_tenant_id()];
        if ($contentType !== null && $contentType !== '') { $sql .= ' AND content_type = ?'; $args[] = $contentType; }
        return \Database::rows($sql . ' ORDER BY updated_at DESC, id DESC', $args);
    }

    private function nextVersion(string $table, string $ownerColumn, int $ownerId): string
    {
        $max = (int)\Database::value("SELECT COUNT(*) FROM {$table} WHERE tenant_id = ? AND {$ownerColumn} = ?", [current_tenant_id(), $ownerId]);
        return 'v' . ($max + 1);
    }
}

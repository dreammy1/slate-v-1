<?php
declare(strict_types=1);
namespace Slate\Services\Presentation;

use Slate\Data\Database;
use Slate\Services\Content\RevisionStore;

final class VisualEditorDocumentStore
{
    private const TABLE = 'visual_editor_documents';
    private const OWNER = 'visual-editor';
    private const TAGS = ['div','section','header','footer','main','nav','article','aside','h1','h2','h3','p','a','button','img','ul','li','span'];

    public static function ensureSchema(): void
    {
        Database::query("CREATE TABLE IF NOT EXISTS visual_editor_documents (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id INT UNSIGNED NOT NULL DEFAULT 1,
            slug VARCHAR(190) NOT NULL,
            title VARCHAR(190) NOT NULL DEFAULT 'Untitled page',
            draft_revision_id BIGINT UNSIGNED NULL,
            published_revision_id BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id), UNIQUE KEY tenant_slug (tenant_id, slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public static function load(string $slug = 'visual-editor-home'): array
    {
        self::ensureSchema();
        $row = Database::row('SELECT * FROM ' . self::TABLE . ' WHERE tenant_id = ? AND slug = ?', [current_tenant_id(), $slug]);
        if (!$row) {
            $id = Database::insert(self::TABLE, ['tenant_id'=>current_tenant_id(),'slug'=>$slug,'title'=>'Visual editor page']);
            return ['id'=>$id,'slug'=>$slug,'title'=>'Visual editor page','document'=>null,'history'=>[],'published'=>false];
        }
        $revision = !empty($row['draft_revision_id']) ? Database::row('SELECT * FROM content_revisions WHERE id = ? AND tenant_id = ?', [(int)$row['draft_revision_id'], current_tenant_id()]) : null;
        return ['id'=>(int)$row['id'],'slug'=>$row['slug'],'title'=>$row['title'],'document'=>$revision ? json_decode((string)$revision['document'], true) : null,'revision_id'=>$revision ? (int)$revision['id'] : null,'published'=>!empty($row['published_revision_id']),'history'=>self::history((int)$row['id'])];
    }

    public static function saveDraft(int $id, array $document, ?int $authorId = null): array
    {
        self::ensureSchema(); $issues = self::validate($document);
        $revisions = new RevisionStore();
        $revisionId = $revisions->snapshot(self::OWNER, $id, json_encode($document, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE), RevisionStore::STATUS_WORKING, $authorId, 'Visual editor autosave', 1);
        Database::update(self::TABLE, ['draft_revision_id'=>$revisionId,'updated_at'=>date('Y-m-d H:i:s')], 'id = ? AND tenant_id = ?', [$id,current_tenant_id()]);
        return ['ok'=>true,'revision_id'=>$revisionId,'issues'=>$issues,'can_publish'=>count($issues)===0];
    }

    public static function publish(int $id, ?int $authorId = null): array
    {
        $row = Database::row('SELECT draft_revision_id FROM ' . self::TABLE . ' WHERE id = ? AND tenant_id = ?', [$id,current_tenant_id()]);
        if (!$row || empty($row['draft_revision_id'])) return ['ok'=>false,'error'=>'Save a draft before publishing.','issues'=>['draft'=>'No draft revision exists.']];
        $draft = Database::row('SELECT document FROM content_revisions WHERE id = ? AND tenant_id = ?', [(int)$row['draft_revision_id'],current_tenant_id()]);
        $doc = json_decode((string)($draft['document'] ?? ''), true); $issues = self::validate(is_array($doc)?$doc:[]);
        if ($issues) return ['ok'=>false,'error'=>'Fix SEO and accessibility issues before publishing.','issues'=>$issues];
        $id2 = (new RevisionStore())->publish(self::OWNER, $id, $authorId);
        Database::update(self::TABLE, ['published_revision_id'=>$id2,'updated_at'=>date('Y-m-d H:i:s')], 'id = ? AND tenant_id = ?', [$id,current_tenant_id()]);
        return ['ok'=>true,'published_revision_id'=>$id2,'issues'=>[]];
    }

    public static function rollback(int $id, int $revisionId, ?int $authorId = null): array
    {
        $row = Database::row('SELECT document FROM content_revisions WHERE id = ? AND tenant_id = ? AND owner_type = ?', [$revisionId,current_tenant_id(),self::OWNER]);
        if (!$row) return ['ok'=>false,'error'=>'Revision not found.'];
        return self::saveDraft($id, json_decode((string)$row['document'], true) ?: [], $authorId);
    }

    public static function history(int $id, int $limit = 25): array
    {
        return (new RevisionStore())->listForOwner(self::OWNER, $id, $limit);
    }

    public static function validate(array $document): array
    {
        $issues=[]; $counts=['h1'=>0]; $walk=function($n) use (&$walk,&$issues,&$counts){ if(!is_array($n))return; $tag=strtolower((string)($n['tag']??'')); if(!in_array($tag,self::TAGS,true))$issues[]='Unsupported HTML tag: '.$tag; if($tag==='h1')$counts['h1']++; if($tag==='img' && trim((string)($n['attrs']['alt']??''))==='')$issues[]='Every image needs descriptive alt text.'; if($tag==='a' && trim((string)($n['attrs']['href']??''))==='')$issues[]='Every link needs a destination.'; if($tag==='button' && trim((string)($n['text']??''))==='')$issues[]='Every button needs accessible text.'; foreach((array)($n['children']??[]) as $c)$walk($c); }; foreach((array)($document['children']??[]) as $n)$walk($n); if($counts['h1']!==1)$issues[]='The page must contain exactly one H1 heading.'; return array_values(array_unique($issues));
    }
}

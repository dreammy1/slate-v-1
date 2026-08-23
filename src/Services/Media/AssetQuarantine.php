<?php
declare(strict_types=1);

namespace Slate\Services\Media;

final class AssetQuarantine
{
    /** @return array{ok:bool,id?:int,path?:string,error?:string} */
    public static function quarantineUpload(string $field, ?int $userId = null): array
    {
        $result = Uploads::handle($field, 'quarantine', [
            'max_bytes' => 20 * 1024 * 1024,
            'allowed_mimes' => Media::allowedMimes(),
            'allowed_exts' => Media::allowedExts(),
        ]);
        if (empty($result['ok'])) return ['ok'=>false, 'error'=>(string)($result['error'] ?? 'Upload rejected.')];
        $path = (string)$result['path'];
        $absolute = SLATE_ROOT . '/' . ltrim($path, '/');
        $checksum = is_file($absolute) ? hash_file('sha256', $absolute) : '';
        if ($checksum === '') return ['ok'=>false, 'error'=>'Could not checksum quarantined asset.'];
        try {
            $existing = \Database::row('SELECT id, stored_path, status FROM asset_quarantine WHERE tenant_id = ? AND checksum = ?', [current_tenant_id(), $checksum]);
            if ($existing) {
                @unlink($absolute);
                return ['ok'=>true, 'id'=>(int)$existing['id'], 'path'=>(string)$existing['stored_path']];
            }
            $id = (int)\Database::insert('asset_quarantine', [
                'tenant_id'=>current_tenant_id(), 'original_name'=>mb_substr((string)($result['original'] ?? basename($path)), 0, 255),
                'stored_path'=>$path, 'mime'=>(string)($result['mime'] ?? ''), 'size_bytes'=>(int)($result['size'] ?? 0),
                'checksum'=>$checksum, 'status'=>'quarantined', 'uploaded_by'=>$userId,
            ]);
            return $id > 0 ? ['ok'=>true,'id'=>$id,'path'=>$path] : ['ok'=>false,'error'=>'Could not record quarantined asset.'];
        } catch (\Throwable $e) {
            @unlink($absolute);
            return ['ok'=>false,'error'=>'Quarantine storage is not available yet.'];
        }
    }

    public static function approve(int $id, ?int $reviewerId = null): int
    {
        $row = \Database::row('SELECT * FROM asset_quarantine WHERE tenant_id = ? AND id = ? AND status = ?', [current_tenant_id(), $id, 'quarantined']);
        if (!$row) return 0;
        $path = (string)$row['stored_path']; $full = SLATE_ROOT . '/' . ltrim($path, '/');
        if (!is_file($full)) { self::reject($id, $reviewerId, 'Quarantined file is missing.'); return 0; }
        $size = (int)filesize($full); $width = null; $height = null;
        if (str_starts_with((string)$row['mime'], 'image/') && function_exists('getimagesize')) {
            $dim = @getimagesize($full); if (is_array($dim)) { $width=(int)($dim[0]??0) ?: null; $height=(int)($dim[1]??0) ?: null; }
        }
        $mediaId = Media::register($path, ['mime'=>(string)$row['mime'],'size_bytes'=>$size,'width'=>$width,'height'=>$height,'original_name'=>(string)$row['original_name']]);
        if ($mediaId <= 0) return 0;
        \Database::update('asset_quarantine', ['status'=>'approved','media_id'=>$mediaId,'reviewed_at'=>date('Y-m-d H:i:s')], 'tenant_id = ? AND id = ?', [current_tenant_id(), $id]);
        return $mediaId;
    }

    public static function reject(int $id, ?int $reviewerId = null, string $reason = 'Rejected during review.'): bool
    {
        $row = \Database::row('SELECT stored_path FROM asset_quarantine WHERE tenant_id = ? AND id = ? AND status = ?', [current_tenant_id(), $id, 'quarantined']);
        if (!$row) return false;
        $ok = \Database::update('asset_quarantine', ['status'=>'rejected','reason'=>mb_substr($reason, 0, 500),'reviewed_at'=>date('Y-m-d H:i:s')], 'tenant_id = ? AND id = ?', [current_tenant_id(), $id]);
        if ($ok) Uploads::remove((string)$row['stored_path']);
        return (bool)$ok;
    }

    /** @return list<array<string,mixed>> */
    public static function pending(int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        return \Database::rows('SELECT * FROM asset_quarantine WHERE tenant_id = ? AND status = ? ORDER BY created_at ASC LIMIT ' . $limit, [current_tenant_id(), 'quarantined']);
    }
}

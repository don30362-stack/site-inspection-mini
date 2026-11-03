<?php

namespace App\Support;

use App\Models\ReportImage;
use Closure;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ReportPhotoQuota
{
    public const LOCK_KEY = 'report-photo-capacity';

    public function synchronized(Closure $operation): mixed
    {
        // Do not expire a lease while a slow write/rollback is still using the space.
        // HTTP uses the existing file cache; tests use an isolated cache store.
        $lock = Cache::lock(self::LOCK_KEY, 0);
        if (! $lock->get()) {
            throw new HttpResponseException(response()->json([
                'data' => null, 'message' => '照片儲存忙碌中，請稍後再試。', 'errors' => (object) [],
            ], 409));
        }
        try {
            return $operation();
        } finally {
            $lock->release();
        }
    }

    public function ensureCapacity(FilesystemAdapter $disk, array $photos): void
    {
        $used = 0;
        // Only committed report paths, not unrelated files or private deletion backups.
        foreach (ReportImage::query()->select('image_path')->distinct()->cursor() as $image) {
            if ($disk->exists($image->image_path)) {
                $used += $disk->size($image->image_path);
            }
        }
        $incoming = 0;
        foreach ($photos as $photo) {
            $size = $photo->getSize();
            if ($size === false) {
                throw new RuntimeException('Unable to determine uploaded photo size.');
            }
            $incoming += $size;
        }
        if ($used + $incoming > config('inspection.photo_storage_limit_bytes')) {
            throw ValidationException::withMessages([
                'photos' => ['巡檢照片總容量不足，請聯絡管理者刪除舊紀錄後再試。'],
            ]);
        }
    }
}

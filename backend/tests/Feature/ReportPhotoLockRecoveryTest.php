<?php

namespace Tests\Feature;

use App\Support\ReportPhotoQuota;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportPhotoLockRecoveryTest extends TestCase
{
    public function test_prefixed_file_cache_key_is_required_to_forget_a_stale_capacity_lock(): void
    {
        $base = storage_path('framework/testing');
        File::ensureDirectoryExists($base);
        $base = realpath($base);
        $directory = $base.DIRECTORY_SEPARATOR.'lock-recovery-'.Str::uuid();
        File::ensureDirectoryExists($directory);
        config([
            'cache.default' => 'file',
            'cache.stores.file.path' => $directory,
            'cache.stores.file.lock_path' => $directory,
        ]);
        Cache::purge('file');
        $cache = Cache::store('file');
        $lock = $cache->lock(ReportPhotoQuota::LOCK_KEY, 0);
        $actualKey = 'file-store-lock:'.ReportPhotoQuota::LOCK_KEY;
        try {
            $cache->forever('unrelated-test-key', 'preserved');
            $this->assertTrue($lock->get());
            $this->assertFileExists($cache->getStore()->path($actualKey));
            $this->assertSame($lock->owner(), $cache->get($actualKey));

            // Original README command reports success but leaves the actual lock intact.
            $this->assertSame(0, Artisan::call('cache:forget', ['key' => ReportPhotoQuota::LOCK_KEY]));
            $competitor = $cache->lock(ReportPhotoQuota::LOCK_KEY, 0);
            $this->assertFalse($competitor->get());

            // Corrected command explicitly targets the file store's prefixed key.
            $this->assertSame(0, Artisan::call('cache:forget', ['key' => $actualKey, 'store' => 'file']));
            $this->assertNull($cache->get($actualKey));
            $this->assertTrue($competitor->get());
            $this->assertTrue($competitor->release());
            $this->assertSame('preserved', $cache->get('unrelated-test-key'));
        } finally {
            $lock->forceRelease();
            Cache::purge('file');
            if (str_starts_with(realpath($directory).DIRECTORY_SEPARATOR, $base.DIRECTORY_SEPARATOR.'lock-recovery-')) {
                File::deleteDirectory($directory);
            }
        }
    }
}

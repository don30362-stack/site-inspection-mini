<?php

namespace App\Actions;

use App\Models\Report;
use App\Models\ReportImage;
use App\Support\ReportPhotoQuota;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class DeleteReport
{
    public function __construct(private ReportPhotoQuota $quota) {}

    // False means the record/public photos are gone, but private backup cleanup failed.
    public function handle(int $id): bool
    {
        // Keep capacity checks out until deletion/rollback has finished restoring files.
        return $this->quota->synchronized(fn () => $this->deleteUnderLock($id));
    }

    private function deleteUnderLock(int $id): bool
    {
        $lock = Cache::lock('report-delete:'.$id, 120);
        abort_unless($lock->get(), 409);
        try {
            $public = Storage::disk('public');
            $private = Storage::disk('local');
            $directory = 'report-deletions/'.Str::uuid();
            $backups = [];
            try {
                DB::transaction(function () use ($id, $public, $private, $directory, &$backups) {
                    // Re-read under the deletion lock to handle concurrent/repeated requests.
                    $report = Report::with('images')->findOrFail($id);
                    $paths = $report->images->pluck('image_path')->unique();
                    $shared = ReportImage::where('report_id', '!=', $id)->whereIn('image_path', $paths)->pluck('image_path');
                    foreach ($paths->diff($shared) as $index => $path) {
                        // Missing files are already removed; storage read errors still fail closed.
                        if (! $public->exists($path)) {
                            continue;
                        }
                        $backup = $directory.'/'.$index;
                        $this->copy($public, $path, $private, $backup);
                        $backups[$path] = $backup;
                    }
                    foreach ($backups as $path => $backup) {
                        if (! $public->delete($path) || $public->exists($path)) {
                            throw new RuntimeException('Unable to delete report photo.');
                        }
                    }
                    if (! $report->delete()) {
                        throw new RuntimeException('Unable to delete report.');
                    }
                });
            } catch (Throwable $exception) {
                $restored = true;
                foreach ($backups as $path => $backup) {
                    try {
                        $this->copy($private, $backup, $public, $path);
                    } catch (Throwable $restoreException) {
                        report($restoreException);
                        $restored = false;
                    }
                }
                if ($restored) {
                    $this->cleanBackups($private, $directory);
                } else {
                    report(new RuntimeException('Report photo recovery required: '.$directory));
                }
                throw $exception;
            }

            return $this->cleanBackups($private, $directory);
        } finally {
            $lock->release();
        }
    }

    private function copy(FilesystemAdapter $from, string $source, FilesystemAdapter $to, string $destination): void
    {
        $stream = $from->readStream($source);
        if (! is_resource($stream)) {
            throw new RuntimeException('Unable to read deletion backup source.');
        }
        try {
            if (! $to->put($destination, $stream)) {
                throw new RuntimeException('Unable to write deletion backup or restore.');
            }
        } finally {
            fclose($stream);
        }
    }

    private function cleanBackups(FilesystemAdapter $disk, string $directory): bool
    {
        try {
            if (! $disk->deleteDirectory($directory) || $disk->directoryExists($directory)) {
                throw new RuntimeException('Unable to clean deletion backup: '.$directory);
            }

            return true;
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }
}

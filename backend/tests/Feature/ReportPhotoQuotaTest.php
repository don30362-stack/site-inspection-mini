<?php

namespace Tests\Feature;

use App\Models\Report;
use App\Support\ReportPhotoQuota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use PDO;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ReportPhotoQuotaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('local');
    }

    private function upload(int $count = 1): array
    {
        return [
            'site_name' => '容量隔離測試', 'issue_type' => '水電', 'severity' => 'low', 'description' => '容量測試',
            'photos' => array_map(fn () => UploadedFile::fake()->createWithContent('photo.png', file_get_contents(base_path('tests/Fixtures/photo.png'))), range(1, $count)),
        ];
    }

    public function test_default_limit_is_two_gib_and_exact_capacity_is_allowed(): void
    {
        $this->assertSame(2147483648, config('inspection.photo_storage_limit_bytes'));
        $bytes = filesize(base_path('tests/Fixtures/photo.png'));
        config(['inspection.photo_storage_limit_bytes' => 2 * $bytes]);
        $this->post('/api/reports', $this->upload())->assertCreated();
        $this->post('/api/reports', $this->upload())->assertCreated();
        $before = Storage::disk('public')->allFiles('reports');
        $this->post('/api/reports', $this->upload())->assertUnprocessable()->assertJsonValidationErrors('photos');
        $this->assertDatabaseCount('reports', 2);
        $this->assertDatabaseCount('report_images', 2);
        $this->assertSame($before, Storage::disk('public')->allFiles('reports'));
    }

    public function test_all_incoming_photos_count_and_over_limit_rejects_the_whole_report(): void
    {
        $bytes = filesize(base_path('tests/Fixtures/photo.png'));
        config(['inspection.photo_storage_limit_bytes' => 5 * $bytes - 1]);
        $this->post('/api/reports', $this->upload(5))->assertUnprocessable()
            ->assertJsonPath('errors.photos.0', '巡檢照片總容量不足，請聯絡管理者刪除舊紀錄後再試。');
        $this->assertDatabaseCount('reports', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
        config(['inspection.photo_storage_limit_bytes' => 0]);
        $this->post('/api/reports', $this->upload())->assertUnprocessable();
    }

    public function test_only_existing_committed_project_photo_paths_count_once(): void
    {
        $bytes = filesize(base_path('tests/Fixtures/photo.png'));
        config(['inspection.photo_storage_limit_bytes' => 2 * $bytes]);
        $created = $this->post('/api/reports', $this->upload())->assertCreated();
        $report = Report::findOrFail($created->json('data.id'));
        $path = $report->images->first()->image_path;
        $report->images()->create(['image_path' => $path, 'sort_order' => 1]);
        $report->images()->create(['image_path' => 'reports/missing.png', 'sort_order' => 2]);
        Storage::disk('public')->put('another-project/large.bin', str_repeat('x', 4096));
        Storage::disk('public')->put('reports/unreferenced.bin', str_repeat('x', 4096));
        Storage::disk('local')->put('report-deletions/backup.bin', str_repeat('x', 4096));
        $this->post('/api/reports', $this->upload())->assertCreated();
        $this->post('/api/reports', $this->upload())->assertUnprocessable();
        Storage::disk('public')->assertExists('another-project/large.bin');
        Storage::disk('local')->assertExists('report-deletions/backup.bin');
    }

    public function test_deletion_releases_capacity_for_a_new_report(): void
    {
        config([
            'inspection.photo_storage_limit_bytes' => filesize(base_path('tests/Fixtures/photo.png')),
            'inspection.admin_password_hash' => Hash::make('quota-test-password'),
        ]);
        $created = $this->post('/api/reports', $this->upload())->assertCreated();
        $this->post('/api/reports', $this->upload())->assertUnprocessable();
        $this->deleteJson('/api/reports/'.$created->json('data.id'), ['admin_password' => 'quota-test-password'])->assertOk();
        $this->post('/api/reports', $this->upload())->assertCreated();
        $this->assertDatabaseCount('reports', 1);
        $this->assertCount(1, Storage::disk('public')->allFiles('reports'));
    }

    public function test_shared_lock_rejects_upload_and_deletion_without_mutating_data(): void
    {
        config(['inspection.admin_password_hash' => Hash::make('quota-test-password')]);
        $created = $this->post('/api/reports', $this->upload())->assertCreated();
        $lock = Cache::lock(ReportPhotoQuota::LOCK_KEY, 0);
        $this->assertTrue($lock->get());
        try {
            $this->post('/api/reports', $this->upload())->assertStatus(409)->assertJsonPath('message', '照片儲存忙碌中，請稍後再試。');
            $this->deleteJson('/api/reports/'.$created->json('data.id'), ['admin_password' => 'quota-test-password'])->assertStatus(409);
            $this->assertDatabaseCount('reports', 1);
            $this->assertCount(1, Storage::disk('public')->allFiles('reports'));
        } finally {
            $lock->release();
        }
        $this->post('/api/reports', $this->upload())->assertCreated();
    }

    public function test_failed_write_releases_lock_and_does_not_consume_capacity(): void
    {
        config(['inspection.photo_storage_limit_bytes' => filesize(base_path('tests/Fixtures/photo.png'))]);
        $disk = Storage::disk('public');
        $failing = Mockery::mock($disk);
        $failing->shouldReceive('putFileAs')->andReturnUsing(function ($directory, $file, $name) use ($disk) {
            $disk->putFileAs($directory, $file, $name);
            throw new RuntimeException('Simulated partial upload.');
        });
        Storage::set('public', $failing);
        $this->post('/api/reports', $this->upload())->assertStatus(500);
        Storage::set('public', $disk);
        $this->assertSame([], $disk->allFiles('reports'));
        $this->post('/api/reports', $this->upload())->assertCreated();
    }

    public function test_overlapping_php_processes_cannot_both_consume_the_remaining_capacity(): void
    {
        $base = storage_path('framework/testing');
        File::ensureDirectoryExists($base);
        $base = realpath($base);
        $root = $base.DIRECTORY_SEPARATOR.'quota-'.Str::uuid();
        File::ensureDirectoryExists($root);
        touch($root.'/database.sqlite');
        $worker = base_path('tests/Fixtures/quota-worker.php');
        $process = fn (string $mode) => new Process([PHP_BINARY, $worker, $root, $mode], base_path(), null, null, 20);
        $first = null;
        $second = null;
        try {
            $process('init')->mustRun();
            $first = $process('first');
            $first->start();
            $deadline = microtime(true) + 10;
            while (! file_exists($root.'/holding') && $first->isRunning() && microtime(true) < $deadline) {
                usleep(10000);
                clearstatcache(true, $root.'/holding');
            }
            $this->assertFileExists($root.'/holding', $first->getErrorOutput());
            $second = $process('second');
            $second->mustRun();
            $this->assertSame(409, json_decode(file_get_contents($root.'/second.json'), true)['status']);
            touch($root.'/release');
            $first->wait();
            $this->assertTrue($first->isSuccessful(), $first->getErrorOutput());
            $this->assertSame(201, json_decode(file_get_contents($root.'/first.json'), true)['status']);
            $process('retry')->mustRun();
            $this->assertSame(422, json_decode(file_get_contents($root.'/retry.json'), true)['status']);
            $db = new PDO('sqlite:'.$root.'/database.sqlite');
            $this->assertSame(1, (int) $db->query('select count(*) from reports')->fetchColumn());
            $this->assertSame(1, (int) $db->query('select count(*) from report_images')->fetchColumn());
            $files = glob($root.'/storage/app/public/reports/*');
            $this->assertCount(1, $files);
            $this->assertSame(filesize(base_path('tests/Fixtures/photo.png')), filesize($files[0]));
            $db = null;
        } finally {
            if ($first?->isRunning()) {
                $first->stop();
            }
            if ($second?->isRunning()) {
                $second->stop();
            }
            if (str_starts_with(realpath($root).DIRECTORY_SEPARATOR, $base.DIRECTORY_SEPARATOR.'quota-')) {
                File::deleteDirectory($root);
            }
        }
    }
}

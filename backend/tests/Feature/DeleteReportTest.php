<?php

namespace Tests\Feature;

use App\Models\Report;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class DeleteReportTest extends TestCase
{
    use RefreshDatabase;

    private string $password = 'isolated-test-password';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('local');
        config(['inspection.admin_password_hash' => Hash::make($this->password)]);
    }

    private function report(int $count = 5): Report
    {
        $report = Report::create(['site_name' => '隔離測試', 'issue_type' => '水電', 'severity' => 'low', 'description' => '刪除測試']);
        foreach (range(1, $count) as $n) {
            $path = 'reports/'.$report->id.'-'.$n.'.png';
            Storage::disk('public')->put($path, file_get_contents(base_path('tests/Fixtures/photo.png')));
            $report->images()->create(['image_path' => $path, 'sort_order' => $n - 1]);
        }

        return $report;
    }

    public function test_deletes_rows_and_all_photos_but_preserves_other_reports(): void
    {
        $report = $this->report();
        $other = $this->report(1);
        $this->deleteJson('/api/reports/'.$report->id, ['admin_password' => $this->password])->assertOk()->assertJsonPath('message', '巡檢紀錄與照片已刪除。');
        $this->assertDatabaseMissing('reports', ['id' => $report->id]);
        $this->assertDatabaseMissing('report_images', ['report_id' => $report->id]);
        $this->assertSame(['reports/'.$other->id.'-1.png'], Storage::disk('public')->allFiles('reports'));
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->getJson('/api/reports/'.$report->id)->assertNotFound();
        $this->deleteJson('/api/reports/'.$report->id, ['admin_password' => $this->password])->assertNotFound();
    }

    public function test_password_is_required_validated_in_body_and_checked_on_server(): void
    {
        $report = $this->report(1);
        $url = '/api/reports/'.$report->id;
        $this->deleteJson($url)->assertUnprocessable()->assertJsonValidationErrors('admin_password');
        $this->deleteJson($url.'?admin_password='.$this->password)->assertUnprocessable();
        $this->deleteJson($url, ['admin_password' => ['invalid']])->assertUnprocessable();
        $this->deleteJson($url, ['admin_password' => str_repeat('a', 1025)])->assertUnprocessable();
        $this->deleteJson($url, ['admin_password' => 'wrong'])->assertForbidden()->assertJsonPath('message', '管理密碼錯誤。');
        $this->assertDatabaseHas('reports', ['id' => $report->id]);
        Storage::disk('public')->assertExists('reports/'.$report->id.'-1.png');
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_unconfigured_or_invalid_hash_disables_deletion(): void
    {
        $report = $this->report(1);
        foreach ([null, '', 'plain-text-is-not-a-hash'] as $hash) {
            config(['inspection.admin_password_hash' => $hash]);
            $this->deleteJson('/api/reports/'.$report->id, ['admin_password' => $this->password])->assertStatus(503);
        }
        $this->assertDatabaseCount('reports', 1);
        Storage::disk('public')->assertExists('reports/'.$report->id.'-1.png');
    }

    public function test_password_preserves_whitespace_and_hash_is_not_exposed_in_api(): void
    {
        $report = $this->report(1);
        config(['inspection.admin_password_hash' => Hash::make(' '.$this->password.' ')]);
        $this->getJson('/api/reports/'.$report->id)->assertDontSee('admin_password');
        $this->deleteJson('/api/reports/'.$report->id, ['admin_password' => $this->password])->assertForbidden();
        $this->deleteJson('/api/reports/'.$report->id, ['admin_password' => ' '.$this->password.' '])->assertOk();
    }

    #[DataProvider('failures')]
    public function test_storage_failure_after_an_earlier_photo_delete_restores_every_file(bool $throw): void
    {
        $report = $this->report();
        $disk = Storage::disk('public');
        $failing = Mockery::mock($disk);
        $deleted = 0;
        $failing->shouldReceive('delete')->andReturnUsing(function ($path) use ($disk, &$deleted, $throw) {
            if (++$deleted === 3) {
                if ($throw) {
                    throw new RuntimeException('Simulated storage failure.');
                }

                return false;
            }

            return $disk->delete($path);
        });
        Storage::set('public', $failing);
        $this->deleteJson('/api/reports/'.$report->id, ['admin_password' => $this->password])->assertStatus(500)->assertJsonPath('data', null);
        $this->assertDatabaseCount('reports', 1);
        $this->assertDatabaseCount('report_images', 5);
        foreach ($report->images as $image) {
            $this->assertSame(file_get_contents(base_path('tests/Fixtures/photo.png')), $disk->get($image->image_path));
        }
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public static function failures(): array
    {
        return [[true], [false]];
    }

    public function test_database_failure_after_photo_removal_restores_files_and_rows(): void
    {
        $report = $this->report(2);
        Report::deleted(fn () => throw new RuntimeException('Simulated database failure.'));
        try {
            $this->deleteJson('/api/reports/'.$report->id, ['admin_password' => $this->password])->assertStatus(500);
        } finally {
            Report::flushEventListeners();
        }
        $this->assertDatabaseCount('reports', 1);
        $this->assertDatabaseCount('report_images', 2);
        foreach ($report->images as $image) {
            Storage::disk('public')->assertExists($image->image_path);
        }
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_backup_creation_failure_does_not_remove_anything(): void
    {
        $report = $this->report(1);
        $private = Mockery::mock(Storage::disk('local'));
        $private->shouldReceive('put')->andReturn(false);
        Storage::set('local', $private);
        $this->deleteJson('/api/reports/'.$report->id, ['admin_password' => $this->password])->assertStatus(500);
        $this->assertDatabaseCount('reports', 1);
        Storage::disk('public')->assertExists('reports/'.$report->id.'-1.png');
    }

    public function test_cleanup_failure_after_commit_is_reported_as_failure_and_keeps_private_backup(): void
    {
        $report = $this->report(1);
        $private = Mockery::mock(Storage::disk('local'));
        $private->shouldReceive('deleteDirectory')->andReturn(false);
        Storage::set('local', $private);
        $this->deleteJson('/api/reports/'.$report->id, ['admin_password' => $this->password])->assertStatus(500)->assertJsonPath('message', '紀錄已移除，但照片備份清理未完成。請勿重複刪除，請聯絡管理者。');
        $this->assertDatabaseCount('reports', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertCount(1, $private->allFiles('report-deletions'));
    }

    public function test_restore_failure_retains_private_backups_for_recovery_and_never_reports_success(): void
    {
        $report = $this->report(2);
        $disk = Storage::disk('public');
        $failing = Mockery::mock($disk);
        $calls = 0;
        $failing->shouldReceive('delete')->andReturnUsing(function ($path) use ($disk, &$calls) {
            return ++$calls === 2 ? false : $disk->delete($path);
        });
        $failing->shouldReceive('put')->andReturn(false);
        Storage::set('public', $failing);
        $this->deleteJson('/api/reports/'.$report->id, ['admin_password' => $this->password])->assertStatus(500);
        $this->assertDatabaseCount('reports', 1);
        $this->assertDatabaseCount('report_images', 2);
        $this->assertCount(2, Storage::disk('local')->allFiles('report-deletions'));
    }

    public function test_hash_command_requires_long_matching_passwords(): void
    {
        $this->artisan('reports:admin-password-hash')
            ->expectsQuestion('管理密碼（至少 12 字元）', 'isolated-command-password')
            ->expectsQuestion('再次輸入管理密碼', 'different-password')
            ->expectsOutput('兩次輸入不一致。')->assertExitCode(1);
        $this->artisan('reports:admin-password-hash')
            ->expectsQuestion('管理密碼（至少 12 字元）', 'short')
            ->expectsOutput('請使用至少 12 字元且不超過 72 位元組的非空白密碼。')->assertExitCode(1);
        $this->artisan('reports:admin-password-hash')
            ->expectsQuestion('管理密碼（至少 12 字元）', 'isolated-command-password')
            ->expectsQuestion('再次輸入管理密碼', 'isolated-command-password')
            ->expectsOutputToContain('$2y$')->assertExitCode(0);
    }

    public function test_password_beyond_bcrypt_byte_limit_is_rejected_instead_of_truncated(): void
    {
        $report = $this->report(1);
        config(['inspection.admin_password_hash' => Hash::make(str_repeat('x', 72))]);
        $this->deleteJson('/api/reports/'.$report->id, ['admin_password' => str_repeat('x', 72).'suffix'])->assertUnprocessable();
        $this->deleteJson('/api/reports/'.$report->id, ['admin_password' => str_repeat('字', 25)])->assertUnprocessable();
        $this->assertDatabaseCount('reports', 1);
        $this->deleteJson('/api/reports/'.$report->id, ['admin_password' => str_repeat('x', 72)])->assertOk();
    }

    public function test_another_report_referencing_the_same_path_keeps_its_file(): void
    {
        $first = $this->report(1);
        $other = $this->report(1);
        $other->images()->create(['image_path' => $first->images->first()->image_path, 'sort_order' => 1]);
        $this->deleteJson('/api/reports/'.$first->id, ['admin_password' => $this->password])->assertOk();
        Storage::disk('public')->assertExists($first->images->first()->image_path);
        $this->assertDatabaseCount('reports', 1);
    }

    public function test_missing_photo_is_already_cleaned_and_same_record_lock_blocks_parallel_deletion(): void
    {
        $report = $this->report(1);
        $lock = Cache::lock('report-delete:'.$report->id, 120);
        $lock->get();
        $this->deleteJson('/api/reports/'.$report->id, ['admin_password' => $this->password])->assertStatus(409);
        $lock->release();
        Storage::disk('public')->delete($report->images->first()->image_path);
        $this->deleteJson('/api/reports/'.$report->id, ['admin_password' => $this->password])->assertOk();
    }

    public function test_delete_attempt_limit_is_shared_across_record_ids_and_expires(): void
    {
        $first = $this->report(1);
        $other = $this->report(1);
        for ($n = 0; $n < 5; $n++) {
            $this->deleteJson('/api/reports/'.$first->id, ['admin_password' => 'wrong'])->assertForbidden();
        }
        $response = $this->deleteJson('/api/reports/'.$other->id, ['admin_password' => $this->password])->assertStatus(429)->assertHeader('Retry-After');
        $this->assertStringContainsString('頻繁', $response->json('message'));
        $this->assertDatabaseCount('reports', 2);
        $this->travel(61)->seconds();
        $this->deleteJson('/api/reports/'.$other->id, ['admin_password' => $this->password])->assertOk();
    }
}

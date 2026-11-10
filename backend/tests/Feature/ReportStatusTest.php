<?php

namespace Tests\Feature;

use App\Models\Report;
use App\Support\ReportPhotoQuota;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReportStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_new_reports_default_to_pending_in_all_resource_responses(): void
    {
        $created = $this->post('/api/reports', [...$this->attributes(), 'photos' => [$this->photo()]])
            ->assertCreated()->assertJsonPath('data.status', 'pending');
        $id = $created->json('data.id');
        $this->assertDatabaseHas('reports', ['id' => $id, 'status' => 'pending']);
        $this->getJson('/api/reports/'.$id)->assertOk()->assertJsonPath('data.status', 'pending');
        $this->getJson('/api/reports')->assertOk()->assertJsonPath('data.0.status', 'pending');
    }

    #[DataProvider('initialStatuses')]
    public function test_post_rejects_any_custom_initial_status(mixed $status): void
    {
        $this->post('/api/reports', [...$this->attributes(), 'status' => $status, 'photos' => [$this->photo()]])
            ->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->assertDatabaseCount('reports', 0);
        $this->assertSame([], Storage::disk('public')->allFiles('reports'));
    }

    public static function initialStatuses(): array
    {
        return [['pending'], ['in_progress'], ['completed'], ['reviewed'], [null], ['']];
    }

    public function test_migration_preserves_legacy_reports_images_and_files(): void
    {
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $migration = require database_path('migrations/2026_11_09_000000_add_status_to_reports_table.php');
        $migration->down();
        $ids = [];
        foreach (range(1, 2) as $index) {
            $ids[] = DB::table('reports')->insertGetId([
                ...$this->attributes(), 'created_at' => '2026-11-01 12:00:00', 'updated_at' => '2026-11-02 12:00:00',
            ]);
        }
        DB::table('report_images')->insert(['report_id' => $ids[0], 'image_path' => 'reports/existing.jpg', 'sort_order' => 0]);
        Storage::disk('public')->put('reports/existing.jpg', 'existing image bytes');
        $before = DB::table('reports')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        $images = DB::table('report_images')->get()->all();

        $migration->up();

        $this->assertSame(array_map(fn ($row) => [...$row, 'status' => 'pending'], $before),
            DB::table('reports')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all());
        $this->assertEquals($images, DB::table('report_images')->get()->all());
        $this->assertSame('existing image bytes', Storage::disk('public')->get('reports/existing.jpg'));
        $column = collect(DB::select('PRAGMA table_info(reports)'))->firstWhere('name', 'status');
        $this->assertSame(1, $column->notnull);
        $this->assertSame("'pending'", $column->dflt_value);
        $id = DB::table('reports')->insertGetId($this->attributes());
        $this->assertDatabaseHas('reports', ['id' => $id, 'status' => 'pending']);
    }

    public function test_status_column_rejects_null(): void
    {
        $this->expectException(QueryException::class);
        DB::table('reports')->insert([...$this->attributes(), 'status' => null]);
    }

    #[DataProvider('statuses')]
    public function test_each_status_can_transition_directly_to_every_status(string $initial): void
    {
        $report = $this->createReport(['status' => $initial]);
        foreach (Report::STATUSES as $status) {
            $report->status = $initial;
            $report->save();
            $this->patchJson('/api/reports/'.$report->id.'/status', ['status' => $status])
                ->assertOk()->assertJsonPath('data.status', $status)->assertJsonPath('data.id', $report->id)
                ->assertJsonPath('message', '處理狀態已更新。');
            $this->assertDatabaseHas('reports', ['id' => $report->id, 'status' => $status]);
        }
    }

    public static function statuses(): array
    {
        return [['pending'], ['in_progress'], ['completed'], ['reviewed']];
    }

    #[DataProvider('invalidStatuses')]
    public function test_invalid_status_is_rejected_without_changing_the_record(mixed $status): void
    {
        $report = $this->createReport();
        $this->patchJson('/api/reports/'.$report->id.'/status', ['status' => $status])
            ->assertUnprocessable()->assertJsonValidationErrors('status')->assertJsonPath('data', null);
        $this->assertSame('pending', $report->fresh()->status);
    }

    public static function invalidStatuses(): array
    {
        return [[null], [''], ['unknown'], ['PENDING'], [0], [true], [['pending']]];
    }

    public function test_missing_status_and_nonexistent_reports_return_json_errors(): void
    {
        $report = $this->createReport();
        $this->patchJson('/api/reports/'.$report->id.'/status', [])
            ->assertUnprocessable()->assertJsonValidationErrors('status');
        foreach (['999999', 'not-an-id'] as $id) {
            $this->patchJson('/api/reports/'.$id.'/status', ['status' => 'reviewed'])
                ->assertNotFound()->assertJsonPath('data', null)->assertJsonStructure(['data', 'message', 'errors']);
        }
    }

    public function test_patch_only_changes_status_and_update_time_without_using_photo_lock(): void
    {
        $report = $this->createReport();
        $report->images()->create(['image_path' => 'reports/existing.jpg', 'sort_order' => 0]);
        Storage::disk('public')->put('reports/existing.jpg', 'unchanged bytes');
        $before = $report->fresh()->toArray();
        $lock = Cache::lock(ReportPhotoQuota::LOCK_KEY, 0);
        $this->assertTrue($lock->get());
        try {
            $this->travel(1)->seconds();
            $response = $this->patchJson('/api/reports/'.$report->id.'/status', [
                'status' => 'completed', 'site_name' => 'changed', 'issue_type' => '水電', 'severity' => 'high',
                'description' => 'changed', 'id' => 999, 'created_at' => '2000-01-01',
                'images' => [], 'image_path' => 'changed',
            ])->assertOk()->assertJsonPath('data.images.0.image_url', Storage::disk('public')->url('reports/existing.jpg'));
            $after = $report->fresh()->toArray();
            foreach ($before as $field => $value) {
                if (! in_array($field, ['status', 'updated_at'])) {
                    $this->assertSame($value, $after[$field]);
                }
            }
            $this->assertNotSame($before['updated_at'], $after['updated_at']);
            $updatedAt = $report->fresh()->updated_at->toISOString();
            $response->assertJsonPath('data.updated_at', $updatedAt);
            $this->getJson('/api/reports/'.$report->id)->assertOk()
                ->assertJsonPath('data.updated_at', $updatedAt);
            $this->assertSame('completed', $response->json('data.status'));
            $this->assertDatabaseCount('report_images', 1);
            $this->assertSame('unchanged bytes', Storage::disk('public')->get('reports/existing.jpg'));
        } finally {
            $lock->release();
        }
    }

    public function test_status_filter_combines_with_existing_filters_and_pagination(): void
    {
        $ids = [];
        foreach (range(1, 21) as $index) {
            $report = $this->createReport(['status' => 'reviewed']);
            $report->forceFill(['created_at' => '2026-11-01 12:00:00'])->save();
            $ids[] = $report->id;
        }
        foreach (['status' => 'pending', 'site_name' => '其他工地', 'issue_type' => '水電', 'severity' => 'high'] as $field => $value) {
            $this->createReport(['status' => 'reviewed', $field => $value]);
        }
        $filters = ['status' => 'reviewed', ...$this->attributes()];
        unset($filters['description']);
        $this->getJson('/api/reports?'.http_build_query([...$filters, 'page' => 2]))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $ids[0])
            ->assertJsonPath('data.0.status', 'reviewed')->assertJsonPath('meta', [
                'current_page' => 2, 'per_page' => 20, 'last_page' => 2, 'total' => 21, 'from' => 21, 'to' => 21,
            ]);
        $first = $this->getJson('/api/reports?'.http_build_query($filters))->assertOk()->assertJsonCount(20, 'data');
        $this->assertSame(array_reverse(array_slice($ids, 1)), array_column($first->json('data'), 'id'));
        $this->getJson('/api/reports?status=invalid')->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->getJson('/api/reports?status[]=pending')->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->getJson('/api/reports?status=')->assertOk()->assertJsonPath('meta.total', 25);
    }

    public function test_patch_limit_is_per_ip_across_reports_and_expires(): void
    {
        $first = $this->createReport();
        $second = $this->createReport();
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10']);
        for ($index = 0; $index < 10; $index++) {
            $id = $index % 2 ? $first->id : $second->id;
            $this->patchJson('/api/reports/'.$id.'/status', ['status' => 'reviewed'])->assertOk();
        }
        $this->patchJson('/api/reports/'.$first->id.'/status', ['status' => 'pending'])
            ->assertStatus(429)->assertHeader('Retry-After')->assertJsonPath('data', null);
        $this->getJson('/api/reports')->assertOk();
        $this->assertSame('reviewed', $first->fresh()->status);
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.11']);
        $this->patchJson('/api/reports/'.$first->id.'/status', ['status' => 'completed'])->assertOk();
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10']);
        $this->travel(61)->seconds();
        $this->patchJson('/api/reports/'.$first->id.'/status', ['status' => 'pending'])->assertOk();
    }

    public function test_invalid_patch_attempts_also_count_towards_rate_limit(): void
    {
        $report = $this->createReport();
        for ($index = 0; $index < 10; $index++) {
            $this->patchJson('/api/reports/'.$report->id.'/status', ['status' => 'invalid'])->assertUnprocessable();
        }
        $this->patchJson('/api/reports/'.$report->id.'/status', ['status' => 'reviewed'])->assertStatus(429);
        $this->assertSame('pending', $report->fresh()->status);
    }

    private function createReport(array $overrides = []): Report
    {
        $report = new Report($this->attributes());
        $report->forceFill($overrides)->save();

        return $report;
    }

    private function attributes(): array
    {
        return ['site_name' => '測試工地', 'issue_type' => '安全防護', 'severity' => 'medium', 'description' => '測試描述'];
    }

    private function photo(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('photo.png', file_get_contents(base_path('tests/Fixtures/photo.png')));
    }
}

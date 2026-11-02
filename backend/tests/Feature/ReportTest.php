<?php

namespace Tests\Feature;

use App\Models\Report;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_final_schema_has_no_legacy_column_and_images_have_a_foreign_key(): void
    {
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(['id', 'site_name', 'issue_type', 'severity', 'description', 'created_at', 'updated_at'], Schema::getColumnListing('reports'));
        $this->assertSame(['id', 'report_id', 'image_path', 'sort_order'], Schema::getColumnListing('report_images'));
        $columns = collect(DB::select('PRAGMA table_info(report_images)'))->keyBy('name');
        $this->assertSame(1, $columns['id']->pk);
        foreach (['report_id', 'image_path', 'sort_order'] as $column) {
            $this->assertSame(1, $columns[$column]->notnull);
        }
        $foreignKey = DB::select('PRAGMA foreign_key_list(report_images)')[0];
        $this->assertSame('reports', $foreignKey->table);
        $this->assertSame('report_id', $foreignKey->from);
        $this->assertSame('CASCADE', $foreignKey->on_delete);
    }

    #[DataProvider('validSeverities')]
    public function test_model_can_create_and_read_reports_with_ordered_images(string $severity): void
    {
        $report = Report::create($this->attributes($severity));
        $report->images()->create(['image_path' => 'reports/second.jpg', 'sort_order' => 1]);
        $report->images()->create(['image_path' => 'reports/first.jpg', 'sort_order' => 0]);
        $stored = Report::findOrFail($report->id);
        $this->assertSame($severity, $stored->severity);
        $this->assertInstanceOf(Carbon::class, $stored->created_at);
        $this->assertInstanceOf(Carbon::class, $stored->updated_at);
        $this->assertSame(['reports/first.jpg', 'reports/second.jpg'], $stored->images->pluck('image_path')->all());
        $this->assertTrue($stored->images->first()->report->is($report));
    }

    public static function validSeverities(): array
    {
        return [['low'], ['medium'], ['high']];
    }

    public function test_foreign_key_rejects_an_orphan_image(): void
    {
        $this->expectException(QueryException::class);
        DB::table('report_images')->insert(['report_id' => 99999, 'image_path' => 'reports/a.jpg', 'sort_order' => 0]);
    }

    public function test_sort_order_is_unique_within_a_report(): void
    {
        $report = Report::create($this->attributes());
        $report->images()->create(['image_path' => 'reports/a.jpg', 'sort_order' => 0]);
        $this->expectException(QueryException::class);
        $report->images()->create(['image_path' => 'reports/b.jpg', 'sort_order' => 0]);
    }

    public function test_report_deletion_cascades_to_image_rows(): void
    {
        $report = Report::create($this->attributes());
        $report->images()->create(['image_path' => 'reports/a.jpg', 'sort_order' => 0]);
        $report->delete();
        $this->assertDatabaseCount('report_images', 0);
    }

    public function test_upgrade_migration_preserves_existing_report_and_photo_path(): void
    {
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $migration = require database_path('migrations/2026_11_02_000001_create_report_images_and_remove_report_image_path.php');
        $migration->down();
        $id = DB::table('reports')->insertGetId([...$this->attributes(), 'image_path' => 'reports/existing.jpg']);
        $migration->up();
        $this->assertFalse(Schema::hasColumn('reports', 'image_path'));
        $this->assertDatabaseHas('reports', ['id' => $id, 'site_name' => '測試工地']);
        $this->assertDatabaseHas('report_images', ['report_id' => $id, 'image_path' => 'reports/existing.jpg', 'sort_order' => 0]);
    }

    public function test_rollback_refuses_to_discard_multiple_photo_paths(): void
    {
        $report = Report::create($this->attributes());
        foreach (range(0, 1) as $order) {
            $report->images()->create(['image_path' => 'reports/'.$order.'.jpg', 'sort_order' => $order]);
        }
        $migration = require database_path('migrations/2026_11_02_000001_create_report_images_and_remove_report_image_path.php');
        try {
            $migration->down();
            $this->fail('Rollback must reject a lossy conversion.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('multiple photos', $exception->getMessage());
        }
        $this->assertDatabaseCount('report_images', 2);
        $this->assertFalse(Schema::hasColumn('reports', 'image_path'));
    }

    #[DataProvider('requiredColumns')]
    public function test_database_rejects_null_required_fields(string $column): void
    {
        $attributes = $this->attributes();
        $attributes[$column] = null;
        $this->expectException(QueryException::class);
        DB::table('reports')->insert($attributes);
    }

    public static function requiredColumns(): array
    {
        return [['site_name'], ['issue_type'], ['severity'], ['description']];
    }

    #[DataProvider('invalidSeverities')]
    public function test_database_rejects_invalid_severity(string $severity): void
    {
        $this->expectException(QueryException::class);
        DB::table('reports')->insert($this->attributes($severity));
    }

    public static function invalidSeverities(): array
    {
        return [['critical'], ['LOW'], ['']];
    }

    private function attributes(string $severity = 'medium'): array
    {
        return ['site_name' => '測試工地', 'issue_type' => '安全防護', 'severity' => $severity, 'description' => '施工區域護欄需要修復。'];
    }
}

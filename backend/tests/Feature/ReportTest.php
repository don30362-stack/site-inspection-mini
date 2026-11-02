<?php

namespace Tests\Feature;

use App\Models\Report;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_table_has_the_expected_sqlite_structure(): void
    {
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $columns = collect(DB::select('PRAGMA table_info(reports)'))->keyBy('name');

        $this->assertSame([
            'id', 'site_name', 'issue_type', 'severity', 'description',
            'image_path', 'created_at', 'updated_at',
        ], $columns->keys()->all());
        $this->assertSame('INTEGER', strtoupper($columns['id']->type));
        $this->assertSame(1, $columns['id']->pk);

        foreach (['site_name', 'issue_type', 'severity'] as $name) {
            $this->assertSame('VARCHAR', strtoupper($columns[$name]->type));
            $this->assertSame(1, $columns[$name]->notnull);
        }

        $this->assertSame('TEXT', strtoupper($columns['description']->type));
        $this->assertSame(1, $columns['description']->notnull);
        $this->assertSame('VARCHAR', strtoupper($columns['image_path']->type));
        $this->assertSame(0, $columns['image_path']->notnull);

        foreach (['created_at', 'updated_at'] as $name) {
            $this->assertSame('DATETIME', strtoupper($columns[$name]->type));
        }
    }

    #[DataProvider('validReports')]
    public function test_model_can_create_and_read_reports(string $severity, ?string $imagePath): void
    {
        $attributes = $this->attributes($severity, $imagePath);
        $report = Report::create($attributes);
        $stored = Report::findOrFail($report->id);

        $this->assertGreaterThan(0, $stored->id);
        foreach ($attributes as $name => $value) {
            $this->assertSame($value, $stored->getAttribute($name));
        }
        $this->assertInstanceOf(Carbon::class, $stored->created_at);
        $this->assertInstanceOf(Carbon::class, $stored->updated_at);
        $this->assertDatabaseHas('reports', ['id' => $stored->id, ...$attributes]);
    }

    public static function validReports(): array
    {
        return [
            'low without photo' => ['low', null],
            'medium with storage path' => ['medium', 'reports/example.jpg'],
            'high with storage path' => ['high', 'reports/example.png'],
        ];
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
        return [
            ['site_name'], ['issue_type'], ['severity'], ['description'],
        ];
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

    private function attributes(string $severity = 'medium', ?string $imagePath = null): array
    {
        return [
            'site_name' => '測試工地',
            'issue_type' => '安全防護',
            'severity' => $severity,
            'description' => '施工區域護欄需要修復。',
            'image_path' => $imagePath,
        ];
    }
}

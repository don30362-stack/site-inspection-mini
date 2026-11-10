<?php

namespace Tests\Feature;

use App\Models\Report;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReportPaginationTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('boundaries')]
    public function test_pages_have_fixed_size_and_paginator_metadata(int $total): void
    {
        Storage::fake('public');
        $ids = $this->createReports($total);
        $last = max(1, (int) ceil($total / 20));
        $seen = [];
        for ($page = 1; $page <= $last; $page++) {
            $expected = array_slice(array_reverse($ids), ($page - 1) * 20, 20);
            // per_page is deliberately ignored: callers cannot override the size.
            $response = $this->getJson('/api/reports?page='.$page.'&per_page=1')->assertOk();
            $this->assertSame($expected, array_column($response->json('data'), 'id'));
            $response->assertJsonPath('meta', [
                'current_page' => $page, 'per_page' => 20, 'last_page' => $last,
                'total' => $total, 'from' => $total ? ($page - 1) * 20 + 1 : null,
                'to' => $total ? min($page * 20, $total) : null,
            ])->assertJsonPath('message', '查詢成功。');
            $this->assertEquals((object) [], json_decode($response->getContent())->errors);
            $seen = [...$seen, ...array_column($response->json('data'), 'id')];
            if ($total) {
                $response->assertJsonStructure(['data' => [['id', 'site_name', 'issue_type', 'severity', 'description', 'images', 'created_at', 'updated_at']]]);
            }
        }
        $this->assertSame(array_reverse($ids), $seen);
        $this->assertCount($total, array_unique($seen));
    }

    public static function boundaries(): array
    {
        return [[0], [1], [20], [21], [41]];
    }

    public function test_all_filters_are_applied_before_counting_and_pagination(): void
    {
        $ids = $this->createReports(21);
        foreach (['site_name' => '其他工地', 'issue_type' => '水電', 'severity' => 'high'] as $field => $value) {
            $this->createReports(1, [$field => $value]);
        }
        $this->getJson('/api/reports?'.http_build_query([
            'page' => 2, 'site_name' => '測試工地', 'issue_type' => '安全防護', 'severity' => 'medium',
        ]))->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ids[0])->assertJsonPath('meta.total', 21)->assertJsonPath('meta.last_page', 2);
    }

    #[DataProvider('invalidPages')]
    public function test_invalid_pages_return_validation_errors(mixed $page): void
    {
        $this->getJson('/api/reports?'.http_build_query(['page' => $page]))
            ->assertUnprocessable()->assertJsonValidationErrors('page')->assertJsonPath('data', null);
    }

    public static function invalidPages(): array
    {
        return [[0], [-1], ['1.5'], ['abc'], [''], [['2']], ['999999999999999999999999999999']];
    }

    public function test_out_of_range_pages_return_empty_data_with_real_metadata(): void
    {
        $this->createReports(21);
        $this->getJson('/api/reports?page=3')->assertOk()->assertJsonPath('data', [])
            ->assertJsonPath('meta', [
                'current_page' => 3, 'per_page' => 20, 'last_page' => 2,
                'total' => 21, 'from' => null, 'to' => null,
            ]);
        Report::query()->delete();
        $this->getJson('/api/reports?page=3')->assertOk()->assertJsonPath('data', [])
            ->assertJsonPath('meta.last_page', 1)->assertJsonPath('meta.total', 0);
    }

    public function test_created_at_precedes_id_and_photos_remain_ordered_on_later_pages(): void
    {
        Storage::fake('public');
        $ids = $this->createReports(21);
        $old = Report::findOrFail($ids[20]);
        $old->forceFill(['created_at' => now()->subDay()])->save();
        $old->images()->create(['image_path' => 'reports/second.jpg', 'sort_order' => 1]);
        $first = $old->images()->create(['image_path' => 'reports/first.jpg', 'sort_order' => 0]);
        $this->getJson('/api/reports?page=2')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $old->id)->assertJsonPath('data.0.images.0.id', $first->id)
            ->assertJsonPath('data.0.images.0.image_url', Storage::disk('public')->url('reports/first.jpg'));
    }

    private function createReports(int $count, array $overrides = []): array
    {
        $ids = [];
        $time = now()->startOfSecond();
        for ($index = 0; $index < $count; $index++) {
            $report = Report::create([
                'site_name' => '測試工地', 'issue_type' => '安全防護', 'severity' => 'medium',
                'description' => '測試描述', ...$overrides,
            ]);
            $report->forceFill(['created_at' => $time])->save();
            $ids[] = $report->id;
        }

        return $ids;
    }
}

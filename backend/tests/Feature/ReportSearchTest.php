<?php

namespace Tests\Feature;

use App\Models\Report;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReportSearchTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('blankKeywords')]
    public function test_blank_keywords_do_not_filter(string $keyword): void
    {
        $report = $this->createReport();

        $this->search($keyword)->assertOk()->assertJsonPath('data.0.id', $report->id)
            ->assertJsonPath('meta.total', 1);
    }

    public static function blankKeywords(): array
    {
        return [[''], [' '], ["\t\r\n"], ['　'], ["\u{200B}\u{FEFF}\u{200D}"]];
    }

    public function test_trims_unicode_and_invisible_edges_but_preserves_internal_spaces(): void
    {
        $match = $this->createReport(['description' => '前段護欄  鬆動後段']);
        $this->createReport(['description' => '護欄 鬆動']);
        $this->createReport(['description' => '護欄鬆動']);
        $this->createReport(['site_name' => '護欄  鬆動', 'description' => '其他描述']);

        $this->search(" \t　\u{200B}護欄  鬆動\u{200D}\u{FEFF}　\n")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $match->id);
    }

    #[DataProvider('literalKeywords')]
    public function test_search_treats_special_characters_as_literal_text(string $keyword): void
    {
        $match = $this->createReport(['description' => '前段'.$keyword.'後段']);
        $this->createReport(['description' => '任意描述 aXb abc']);

        $this->search($keyword)->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->id)->assertJsonPath('meta.total', 1);
    }

    public static function literalKeywords(): array
    {
        return [['%'], ['_'], ['!'], ['\\'], ["'"], ["%' OR 1=1 --"], ['!%_\\'], ['0'], ['&+#=?']];
    }

    #[DataProvider('keywordsWithNullBytes')]
    public function test_internal_null_bytes_preserve_literal_matching_and_sqlite_case_behavior(string $keyword, string $matching, string $nonMatching): void
    {
        $match = $this->createReport(['description' => $matching]);
        $this->createReport(['description' => $nonMatching]);

        $this->search($keyword)->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->id)->assertJsonPath('meta.total', 1);
    }

    public static function keywordsWithNullBytes(): array
    {
        return [
            'full keyword required' => ["abc\0xyz", "prefix\0abc\0xyz suffix", 'abc'],
            'ascii case after null' => ["abc\0xyz", "ABC\0XYZ", "abc\0xy"],
            'literal special characters' => ["a\0%_!\\'", "prefix\0A\0%_!\\'suffix", "a\0xxx"],
            'unicode case stays distinct' => ["abc\0Ä", "ABC\0Ä", "abc\0ä"],
        ];
    }

    public function test_plain_keyword_matches_after_null_without_bypassing_any_filter(): void
    {
        $match = $this->createReport(['description' => "prefix\0XYZ"]);
        $match->forceFill(['status' => 'reviewed'])->save();
        foreach (['site_name' => '其他工地', 'issue_type' => '水電', 'severity' => 'high', 'status' => 'pending'] as $field => $value) {
            $report = $this->createReport(['description' => "prefix\0XYZ", $field => $value]);
            $report->forceFill(['status' => $field === 'status' ? $value : 'reviewed'])->save();
        }

        $this->getJson('/api/reports?'.http_build_query([
            'site_name' => '測試工地', 'issue_type' => '安全防護', 'severity' => 'medium',
            'status' => 'reviewed', 'keyword' => 'xyz',
        ]))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $match->id)
            ->assertJsonPath('meta.total', 1);
    }

    #[DataProvider('lengthCharacters')]
    public function test_keyword_length_counts_code_points_after_trimming(string $character): void
    {
        $keyword = str_repeat($character, 100);
        $match = $this->createReport(['description' => $keyword]);

        $this->search('　'.$keyword.'　')->assertOk()->assertJsonPath('data.0.id', $match->id);
        $this->search($keyword.$character)->assertUnprocessable()
            ->assertJsonValidationErrors('keyword')->assertJsonPath('data', null);
    }

    public static function lengthCharacters(): array
    {
        return [['字'], ['😀']];
    }

    public function test_array_keyword_is_rejected(): void
    {
        $this->getJson('/api/reports?'.http_build_query(['keyword' => ['護欄']]))
            ->assertUnprocessable()->assertJsonValidationErrors('keyword')->assertJsonPath('data', null);
    }

    public function test_ascii_is_case_insensitive_and_non_ascii_retains_sqlite_case_behavior(): void
    {
        $ascii = $this->createReport(['description' => '前段ABC後段']);
        $unicode = $this->createReport(['description' => '前段Ä後段']);

        $this->search('abc')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $ascii->id);
        $this->search('Ä')->assertOk()->assertJsonPath('data.0.id', $unicode->id);
        $this->search('ä')->assertOk()->assertJsonPath('data', [])->assertJsonPath('meta.total', 0);
    }

    public function test_keyword_combines_with_all_four_filters_before_sorting_and_pagination(): void
    {
        $time = now()->startOfSecond();
        $ids = [];
        for ($index = 0; $index < 21; $index++) {
            $report = $this->createReport();
            $report->forceFill(['created_at' => $time, 'status' => 'reviewed'])->save();
            $ids[] = $report->id;
        }
        foreach (['site_name' => '其他工地', 'issue_type' => '水電', 'severity' => 'high', 'status' => 'pending', 'description' => '不符合'] as $field => $value) {
            $report = $this->createReport([$field => $value]);
            $report->forceFill(['status' => $field === 'status' ? $value : 'reviewed'])->save();
        }
        $filters = ['site_name' => '測試工地', 'issue_type' => '安全防護', 'severity' => 'medium', 'status' => 'reviewed', 'keyword' => '護欄'];

        $first = $this->getJson('/api/reports?'.http_build_query($filters))->assertOk()->assertJsonCount(20, 'data');
        $this->assertSame(array_slice(array_reverse($ids), 0, 20), array_column($first->json('data'), 'id'));
        $this->getJson('/api/reports?'.http_build_query([...$filters, 'page' => 2, 'per_page' => 1]))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $ids[0])
            ->assertJsonPath('meta', ['current_page' => 2, 'per_page' => 20, 'last_page' => 2, 'total' => 21, 'from' => 21, 'to' => 21]);
        $this->getJson('/api/reports?'.http_build_query([...$filters, 'page' => 3]))
            ->assertOk()->assertJsonPath('data', [])->assertJsonPath('meta.total', 21)->assertJsonPath('meta.last_page', 2);
        $this->search('沒有符合')->assertOk()->assertJsonPath('data', [])
            ->assertJsonPath('meta', ['current_page' => 1, 'per_page' => 20, 'last_page' => 1, 'total' => 0, 'from' => null, 'to' => null]);
    }

    private function search(string $keyword): TestResponse
    {
        return $this->getJson('/api/reports?'.http_build_query(['keyword' => $keyword]));
    }

    private function createReport(array $overrides = []): Report
    {
        return Report::create([
            'site_name' => '測試工地', 'issue_type' => '安全防護', 'severity' => 'medium',
            'description' => '施工區域護欄需要修復。', ...$overrides,
        ]);
    }
}

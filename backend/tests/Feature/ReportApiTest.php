<?php

namespace Tests\Feature;

use App\Models\Report;
use App\Models\ReportImage;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ReportApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    #[DataProvider('photoFormats')]
    public function test_creates_a_report_and_stores_the_actual_image(string $extension): void
    {
        $bytes = file_get_contents(base_path('tests/Fixtures/photo.'.$extension));
        $response = $this->post('/api/reports', [
            ...$this->attributes(),
            'photos' => [UploadedFile::fake()->createWithContent('user-photo.'.$extension, $bytes)],
            'image_path' => 'untrusted/path.jpg',
        ])->assertCreated()->assertJsonPath('message', '巡檢紀錄已建立。');

        $report = Report::findOrFail($response->json('data.id'));
        $this->assertDatabaseCount('reports', 1);
        $this->assertDatabaseHas('reports', ['id' => $report->id, ...$this->attributes()]);
        $this->assertMatchesRegularExpression('/^reports\/[0-9a-f-]{36}\.(jpg|png|gif|webp)$/', $report->images->first()->image_path);
        $this->assertSame($bytes, Storage::disk('public')->get($report->images->first()->image_path));
        $response->assertJsonPath('data.images.0.image_url', Storage::disk('public')->url($report->images->first()->image_path));
        $response->assertJsonMissingPath('data.image_path')->assertJsonMissingPath('data.image_url');
        $response->assertJsonStructure(['data' => ['id', 'site_name', 'issue_type', 'severity', 'description', 'images' => [['id', 'image_url', 'sort_order']], 'created_at', 'updated_at'], 'message', 'errors']);
    }

    public static function photoFormats(): array
    {
        return [['jpg'], ['png'], ['gif'], ['webp']];
    }

    public function test_lists_newest_first_with_an_id_tie_breaker(): void
    {
        $old = Report::create($this->attributes());
        $old->forceFill(['created_at' => now()->subDay()])->save();
        $new = Report::create($this->attributes());
        $newer = Report::create($this->attributes());

        $response = $this->get('/api/reports')->assertOk();
        $this->assertSame([$newer->id, $new->id, $old->id], array_column($response->json('data'), 'id'));
        $response->assertJsonPath('data.0.images', []);
    }

    #[DataProvider('filters')]
    public function test_filters_use_exact_matches_and_can_be_combined(array $filters): void
    {
        $match = Report::create($this->attributes());
        Report::create([...$this->attributes(), 'site_name' => '其他工地', 'issue_type' => '水電', 'severity' => 'low']);
        Report::create([...$this->attributes(), 'site_name' => '測試工地分區', 'issue_type' => '裝修', 'severity' => 'high']);

        $this->get('/api/reports?'.http_build_query($filters))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $match->id);
    }

    public static function filters(): array
    {
        return [
            'site' => [['site_name' => '測試工地']],
            'issue' => [['issue_type' => '安全防護']],
            'severity' => [['severity' => 'medium']],
            'combined' => [['site_name' => '測試工地', 'issue_type' => '安全防護', 'severity' => 'medium']],
        ];
    }

    public function test_combined_filters_require_all_conditions_to_match(): void
    {
        Report::create($this->attributes());
        $this->get('/api/reports?'.http_build_query(['site_name' => '測試工地', 'severity' => 'high']))
            ->assertOk()->assertJsonPath('data', []);
    }

    public function test_shows_one_report_and_returns_json_404_without_accept_header(): void
    {
        $report = Report::create($this->attributes());
        $report->images()->create(['image_path' => 'reports/example.jpg', 'sort_order' => 0]);
        $this->get('/api/reports/'.$report->id)->assertOk()
            ->assertJsonPath('data.id', $report->id)
            ->assertJsonPath('data.images.0.image_url', Storage::disk('public')->url($report->images->first()->image_path));
        $this->get('/api/reports/999999')->assertNotFound()->assertJsonPath('data', null)
            ->assertJsonStructure(['data', 'message', 'errors']);
        $this->get('/api/reports/not-an-id')->assertNotFound()->assertJsonPath('data', null);
    }

    public function test_rejects_missing_required_fields_and_photo(): void
    {
        $this->post('/api/reports', [])->assertUnprocessable()
            ->assertJsonValidationErrors(['site_name', 'issue_type', 'severity', 'description', 'photos'])
            ->assertJsonPath('data', null);
        $this->assertNothingPersisted();
    }

    public function test_rejects_a_missing_photo_with_other_fields_present(): void
    {
        $this->post('/api/reports', $this->attributes())->assertUnprocessable()->assertJsonValidationErrors('photos');
        $this->assertNothingPersisted();
    }

    #[DataProvider('invalidFields')]
    public function test_rejects_invalid_fields(string $field, mixed $value): void
    {
        $this->post('/api/reports', [
            ...$this->attributes(), 'photos' => [$this->photo()], $field => $value,
        ])->assertUnprocessable()->assertJsonValidationErrors($field)->assertJsonPath('data', null);
        $this->assertNothingPersisted();
    }

    public static function invalidFields(): array
    {
        return [
            ['site_name', null], ['issue_type', null], ['severity', null], ['description', null],
            ['site_name', '   '], ['description', "\t\r\n"], ['issue_type', '　'], ['severity', ' '],
            ['description', '　　'], ['site_name', str_repeat('工', 101)],
            ['description', str_repeat('字', 1001)], ['severity', 'critical'], ['severity', 'LOW'],
            ['issue_type', 'unknown'], ['site_name', ['array']], ['description', 123],
        ];
    }

    public function test_accepts_text_limits_and_trims_whitespace(): void
    {
        $this->post('/api/reports', [
            ...$this->attributes(), 'site_name' => '　'.str_repeat('工', 100).' ',
            'description' => str_repeat('字', 1000), 'photos' => [$this->photo()],
        ])->assertCreated()->assertJsonPath('data.site_name', str_repeat('工', 100));
    }

    public function test_rejects_invalid_filter_values(): void
    {
        $this->get('/api/reports?severity=critical&issue_type=unknown')
            ->assertUnprocessable()->assertJsonValidationErrors(['severity', 'issue_type']);
    }

    #[DataProvider('invalidPhotos')]
    public function test_rejects_non_images_disguised_images_and_svg(string $name, string $content): void
    {
        $this->post('/api/reports', [
            ...$this->attributes(), 'photos' => [UploadedFile::fake()->createWithContent($name, $content)],
        ])->assertUnprocessable()->assertJsonValidationErrors('photos.0');
        $this->assertNothingPersisted();
    }

    public static function invalidPhotos(): array
    {
        return [
            ['notes.txt', 'plain text'],
            ['fake.jpg', '<?php echo "not a photo";'],
            ['fake.heic', 'not a HEIC image'],
            ['broken.png', "\x89PNG\r\n\x1a\n"],
            ['unsafe.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'],
        ];
    }

    public function test_rejects_an_actual_file_larger_than_twenty_mib(): void
    {
        $this->post('/api/reports', [
            ...$this->attributes(), 'photos' => [$this->paddedPhoto(20 * 1024 * 1024 + 1)],
        ])->assertUnprocessable()->assertJsonValidationErrors('photos.0');
        $this->assertNothingPersisted();
    }

    public function test_accepts_an_actual_file_of_exactly_twenty_mib(): void
    {
        $response = $this->post('/api/reports', [
            ...$this->attributes(), 'photos' => [$this->paddedPhoto(20 * 1024 * 1024)],
        ])->assertCreated();
        $this->assertSame(20 * 1024 * 1024, Storage::disk('public')->size(Report::findOrFail($response->json('data.id'))->images->first()->image_path));
    }

    public function test_php_upload_failure_is_a_json_validation_error(): void
    {
        $photo = new UploadedFile(base_path('tests/Fixtures/photo.png'), 'photo.png', null, UPLOAD_ERR_INI_SIZE, true);
        $this->post('/api/reports', [...$this->attributes(), 'photos' => [$photo]])
            ->assertUnprocessable()->assertJsonValidationErrors('photos.0');
        $this->assertNothingPersisted();
    }

    public function test_post_size_limit_is_a_json_validation_error(): void
    {
        $this->withServerVariables(['CONTENT_LENGTH' => 1024 * 1024 * 1024])
            ->post('/api/reports', $this->attributes())->assertUnprocessable()
            ->assertJsonValidationErrors('photos')->assertJsonPath('data', null);
        $this->assertNothingPersisted();
    }

    public function test_later_database_failure_rolls_back_all_rows_and_photos(): void
    {
        $created = 0;
        ReportImage::created(function () use (&$created) {
            if (++$created === 3) {
                throw new RuntimeException('Simulated failure after third image insert.');
            }
        });
        try {
            $this->post('/api/reports', [...$this->attributes(), 'photos' => array_map(fn () => $this->photo(), range(1, 5))])
                ->assertStatus(500)->assertJsonPath('data', null)->assertJsonMissing(['exception' => RuntimeException::class]);
        } finally {
            ReportImage::flushEventListeners();
        }
        $this->assertNothingPersisted();
    }

    #[DataProvider('writeFailures')]
    public function test_partial_storage_failure_cleans_up_and_creates_no_record(bool $throw): void
    {
        $disk = Storage::disk('public');
        $failingDisk = Mockery::mock(FilesystemAdapter::class);
        $failingDisk->shouldReceive('putFileAs')->once()->andReturnUsing(function ($directory, $file, $name) use ($disk, $throw) {
            $disk->putFileAs($directory, $file, $name);
            if ($throw) {
                throw new RuntimeException('Simulated partial write.');
            }

            return false;
        });
        $failingDisk->shouldReceive('delete')->once()->andReturnUsing(fn ($path) => $disk->delete($path));
        Storage::set('public', $failingDisk);
        try {
            $this->post('/api/reports', [...$this->attributes(), 'photos' => [$this->photo()]])
                ->assertStatus(500)->assertJsonPath('data', null);
        } finally {
            Storage::set('public', $disk);
        }
        $this->assertNothingPersisted();
    }

    public static function writeFailures(): array
    {
        return ['exception' => [true], 'false return' => [false]];
    }

    public function test_five_mixed_photos_are_ordered_and_persist_across_reads(): void
    {
        $formats = ['jpg', 'png', 'webp', 'gif', 'jpg'];
        $photos = array_map(fn ($extension) => UploadedFile::fake()->createWithContent('photo.'.$extension, file_get_contents(base_path('tests/Fixtures/photo.'.$extension))), $formats);
        $response = $this->post('/api/reports', [...$this->attributes(), 'photos' => $photos])
            ->assertCreated()->assertJsonCount(5, 'data.images');
        $report = Report::findOrFail($response->json('data.id'));
        $this->assertDatabaseCount('report_images', 5);
        $this->assertSame([0, 1, 2, 3, 4], $report->images->pluck('sort_order')->all());
        foreach ($report->images as $index => $image) {
            $this->assertSame(file_get_contents(base_path('tests/Fixtures/photo.'.$formats[$index])), Storage::disk('public')->get($image->image_path));
            $response->assertJsonPath('data.images.'.$index.'.id', $image->id)
                ->assertJsonPath('data.images.'.$index.'.sort_order', $index)
                ->assertJsonPath('data.images.'.$index.'.image_url', Storage::disk('public')->url($image->image_path));
        }
        $this->get('/api/reports/'.$report->id)->assertOk()->assertJsonPath('data.images', $response->json('data.images'));
        $this->get('/api/reports')->assertOk()->assertJsonPath('data.0.images', $response->json('data.images'));
    }

    public function test_zero_six_and_old_photo_field_are_rejected(): void
    {
        foreach ([[], array_map(fn () => $this->photo(), range(1, 6))] as $photos) {
            $this->post('/api/reports', [...$this->attributes(), 'photos' => $photos])
                ->assertUnprocessable()->assertJsonValidationErrors('photos');
        }
        $this->post('/api/reports', [...$this->attributes(), 'photo' => $this->photo()])
            ->assertUnprocessable()->assertJsonValidationErrors(['photo', 'photos']);
        $this->assertNothingPersisted();
    }

    public function test_rejects_sparse_photo_indices_without_persisting_anything(): void
    {
        // Pass files separately to preserve the original multipart field indices.
        $response = $this->call('POST', '/api/reports', $this->attributes(), [], [
            'photos' => [0 => $this->photo(), 2 => $this->photo()],
        ]);

        $this->assertSame([0, 2], array_keys($response->baseRequest->file('photos')));
        $response->assertUnprocessable()->assertJsonValidationErrors('photos')->assertJsonPath('data', null);
        $this->assertNothingPersisted();
    }

    public function test_images_are_eager_loaded_without_n_plus_one_queries(): void
    {
        foreach (range(1, 4) as $number) {
            Report::create($this->attributes())->images()->create(['image_path' => 'reports/'.$number.'.jpg', 'sort_order' => 0]);
        }
        DB::enableQueryLog();
        $this->get('/api/reports')->assertOk()->assertJsonCount(4, 'data');
        $queries = collect(DB::getQueryLog())->filter(fn ($query) => str_contains($query['query'], 'report_images'));
        DB::disableQueryLog();
        $this->assertCount(1, $queries);
    }

    #[DataProvider('laterWriteFailures')]
    public function test_later_photo_failure_removes_all_files_and_database_rows(int $failureAt, bool $throw): void
    {
        $disk = Storage::disk('public');
        $failingDisk = Mockery::mock(FilesystemAdapter::class);
        $writes = 0;
        $failingDisk->shouldReceive('putFileAs')->times($failureAt)->andReturnUsing(function ($directory, $file, $name) use ($disk, $failureAt, $throw, &$writes) {
            $path = $disk->putFileAs($directory, $file, $name);
            if (++$writes === $failureAt) {
                if ($throw) {
                    throw new RuntimeException('Simulated later partial write.');
                }

                return false;
            }

            return $path;
        });
        $failingDisk->shouldReceive('delete')->times($failureAt)->andReturnUsing(fn ($path) => $disk->delete($path));
        Storage::set('public', $failingDisk);
        try {
            $this->post('/api/reports', [...$this->attributes(), 'photos' => array_map(fn () => $this->photo(), range(1, 5))])
                ->assertStatus(500)->assertJsonPath('data', null);
        } finally {
            Storage::set('public', $disk);
        }
        $this->assertNothingPersisted();
    }

    public static function laterWriteFailures(): array
    {
        $cases = [];
        foreach (range(2, 5) as $position) {
            $cases[] = [$position, true];
            $cases[] = [$position, false];
        }

        return $cases;
    }

    private function photo(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('photo.png', file_get_contents(base_path('tests/Fixtures/photo.png')));
    }

    private function paddedPhoto(int $bytes): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('large.png', str_pad(file_get_contents(base_path('tests/Fixtures/photo.png')), $bytes, "\0"));
    }

    private function attributes(): array
    {
        return ['site_name' => '測試工地', 'issue_type' => '安全防護', 'severity' => 'medium', 'description' => '施工區域護欄需要修復。'];
    }

    private function assertNothingPersisted(): void
    {
        $this->assertDatabaseCount('reports', 0);
        $this->assertDatabaseCount('report_images', 0);
        $this->assertSame([], Storage::disk('public')->allFiles('reports'));
    }
}

<?php

namespace Tests\Feature;

use App\Models\Report;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
            'photo' => UploadedFile::fake()->createWithContent('user-photo.'.$extension, $bytes),
            'image_path' => 'untrusted/path.jpg',
        ])->assertCreated()->assertJsonPath('message', '巡檢紀錄已建立。');

        $report = Report::findOrFail($response->json('data.id'));
        $this->assertDatabaseCount('reports', 1);
        $this->assertDatabaseHas('reports', ['id' => $report->id, ...$this->attributes()]);
        $this->assertMatchesRegularExpression('/^reports\/[0-9a-f-]{36}\.(jpg|png|gif|webp)$/', $report->image_path);
        $this->assertSame($bytes, Storage::disk('public')->get($report->image_path));
        $response->assertJsonPath('data.image_url', Storage::disk('public')->url($report->image_path));
        $response->assertJsonStructure(['data' => ['id', 'site_name', 'issue_type', 'severity', 'description', 'image_path', 'image_url', 'created_at', 'updated_at'], 'message', 'errors']);
    }

    public static function photoFormats(): array
    {
        return [['jpg'], ['png'], ['gif'], ['webp']];
    }

    public function test_lists_newest_first_with_an_id_tie_breaker(): void
    {
        $old = Report::create([...$this->attributes(), 'image_path' => null]);
        $old->forceFill(['created_at' => now()->subDay()])->save();
        $new = Report::create($this->attributes());
        $newer = Report::create($this->attributes());

        $response = $this->get('/api/reports')->assertOk();
        $this->assertSame([$newer->id, $new->id, $old->id], array_column($response->json('data'), 'id'));
        $response->assertJsonPath('data.0.image_url', null);
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
        $report = Report::create([...$this->attributes(), 'image_path' => 'reports/example.jpg']);
        $this->get('/api/reports/'.$report->id)->assertOk()
            ->assertJsonPath('data.id', $report->id)
            ->assertJsonPath('data.image_url', Storage::disk('public')->url($report->image_path));
        $this->get('/api/reports/999999')->assertNotFound()->assertJsonPath('data', null)
            ->assertJsonStructure(['data', 'message', 'errors']);
        $this->get('/api/reports/not-an-id')->assertNotFound()->assertJsonPath('data', null);
    }

    public function test_rejects_missing_required_fields_and_photo(): void
    {
        $this->post('/api/reports', [])->assertUnprocessable()
            ->assertJsonValidationErrors(['site_name', 'issue_type', 'severity', 'description', 'photo'])
            ->assertJsonPath('data', null);
        $this->assertNothingPersisted();
    }

    public function test_rejects_a_missing_photo_with_other_fields_present(): void
    {
        $this->post('/api/reports', $this->attributes())->assertUnprocessable()->assertJsonValidationErrors('photo');
        $this->assertNothingPersisted();
    }

    #[DataProvider('invalidFields')]
    public function test_rejects_invalid_fields(string $field, mixed $value): void
    {
        $this->post('/api/reports', [
            ...$this->attributes(), 'photo' => $this->photo(), $field => $value,
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
            'description' => str_repeat('字', 1000), 'photo' => $this->photo(),
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
            ...$this->attributes(), 'photo' => UploadedFile::fake()->createWithContent($name, $content),
        ])->assertUnprocessable()->assertJsonValidationErrors('photo');
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
            ...$this->attributes(), 'photo' => $this->paddedPhoto(20 * 1024 * 1024 + 1),
        ])->assertUnprocessable()->assertJsonValidationErrors('photo');
        $this->assertNothingPersisted();
    }

    public function test_accepts_an_actual_file_of_exactly_twenty_mib(): void
    {
        $response = $this->post('/api/reports', [
            ...$this->attributes(), 'photo' => $this->paddedPhoto(20 * 1024 * 1024),
        ])->assertCreated();
        $this->assertSame(20 * 1024 * 1024, Storage::disk('public')->size($response->json('data.image_path')));
    }

    public function test_php_upload_failure_is_a_json_validation_error(): void
    {
        $photo = new UploadedFile(base_path('tests/Fixtures/photo.png'), 'photo.png', null, UPLOAD_ERR_INI_SIZE, true);
        $this->post('/api/reports', [...$this->attributes(), 'photo' => $photo])
            ->assertUnprocessable()->assertJsonValidationErrors('photo');
        $this->assertNothingPersisted();
    }

    public function test_post_size_limit_is_a_json_validation_error(): void
    {
        $this->withServerVariables(['CONTENT_LENGTH' => 1024 * 1024 * 1024])
            ->post('/api/reports', $this->attributes())->assertUnprocessable()
            ->assertJsonValidationErrors('photo')->assertJsonPath('data', null);
        $this->assertNothingPersisted();
    }

    public function test_database_failure_rolls_back_the_insert_and_deletes_the_photo(): void
    {
        Report::created(function () {
            throw new RuntimeException('Simulated failure after database insert.');
        });
        try {
            $this->post('/api/reports', [...$this->attributes(), 'photo' => $this->photo()])
                ->assertStatus(500)->assertJsonPath('data', null)->assertJsonMissing(['exception' => RuntimeException::class]);
        } finally {
            Report::flushEventListeners();
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
            $this->post('/api/reports', [...$this->attributes(), 'photo' => $this->photo()])
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
        $this->assertSame([], Storage::disk('public')->allFiles('reports'));
    }
}

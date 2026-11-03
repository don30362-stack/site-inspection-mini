<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReportRateLimitTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('validProxySettings')]
    public function test_proxy_configuration_preserves_valid_ips_and_cidrs(string $value, array $expected): void
    {
        $configuration = $this->loadProxyConfiguration($value);
        $this->assertSame($expected, $configuration['trusted_proxies']);
    }

    #[DataProvider('invalidProxySettings')]
    public function test_proxy_configuration_rejects_invalid_cidrs(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->loadProxyConfiguration($value);
    }

    public static function validProxySettings(): array
    {
        return [
            'empty' => ['', []],
            'ipv4 address' => ['192.0.2.1', ['192.0.2.1']],
            'ipv6 address' => ['2001:db8::1', ['2001:db8::1']],
            'ipv4 lower boundary' => ['128.0.0.0/1', ['128.0.0.0/1']],
            'ipv4 upper boundary' => ['192.0.2.1/32', ['192.0.2.1/32']],
            'ipv6 lower boundary' => ['8000::/1', ['8000::/1']],
            'ipv6 upper boundary' => ['2001:db8::1/128', ['2001:db8::1/128']],
            'mixed list with whitespace' => [' 127.0.0.1, ::1, 192.0.2.0/24, 2001:db8::/64 ', ['127.0.0.1', '::1', '192.0.2.0/24', '2001:db8::/64']],
        ];
    }

    public static function invalidProxySettings(): array
    {
        $cases = [];
        foreach (['192.0.2.1', '2001:db8::1'] as $address) {
            foreach (['', '0', '00', '-1', '+1', 'abc', '24abc', '1.5', '2e1', ' 24', '２４', '999999999999999999999999'] as $prefix) {
                $cases[$address.'/'.$prefix] = [$address.'/'.$prefix];
            }
            $cases[$address.'/24/1'] = [$address.'/24/1'];
            $cases[$address.'/24/'] = [$address.'/24/'];
            $cases[$address.'//24'] = [$address.'//24'];
        }
        $cases['ipv4 exceeds 32'] = ['192.0.2.1/33'];
        $cases['ipv4 with ipv6-sized mask'] = ['192.0.2.1/128'];
        $cases['ipv6 exceeds 128'] = ['2001:db8::1/129'];
        $cases['invalid address'] = ['not-an-ip/24'];
        $cases['wildcard'] = ['*'];
        $cases['invalid entry in mixed list'] = ['127.0.0.1,2001:db8::1/129'];

        return $cases;
    }

    private function loadProxyConfiguration(string $value): array
    {
        $key = 'TRUSTED_PROXIES';
        $previousProcess = getenv($key);
        $hadEnv = array_key_exists($key, $_ENV);
        $previousEnv = $_ENV[$key] ?? null;
        $hadServer = array_key_exists($key, $_SERVER);
        $previousServer = $_SERVER[$key] ?? null;
        putenv($key.'='.$value);
        $_ENV[$key] = $_SERVER[$key] = $value;
        try {
            return require config_path('inspection.php');
        } finally {
            putenv($previousProcess === false ? $key : $key.'='.$previousProcess);
            if ($hadEnv) {
                $_ENV[$key] = $previousEnv;
            } else {
                unset($_ENV[$key]);
            }
            if ($hadServer) {
                $_SERVER[$key] = $previousServer;
            } else {
                unset($_SERVER[$key]);
            }
        }
    }

    public function test_ten_successful_uploads_share_the_same_attempt_limit(): void
    {
        Storage::fake('public');
        for ($n = 0; $n < 10; $n++) {
            $this->post('/api/reports', [
                'site_name' => '限流測試', 'issue_type' => '水電', 'severity' => 'low', 'description' => '隔離照片',
                'photos' => [UploadedFile::fake()->createWithContent('photo.png', file_get_contents(base_path('tests/Fixtures/photo.png')))],
            ])->assertCreated();
        }
        $this->postJson('/api/reports', [])->assertStatus(429);
        $this->assertDatabaseCount('reports', 10);
        $this->assertCount(10, Storage::disk('public')->allFiles('reports'));
    }

    public function test_old_hourly_counter_does_not_extend_the_new_minute_window(): void
    {
        RateLimiter::for('report-create', fn ($request) => Limit::perHour(5)->by('report-create:'.$request->ip()));
        for ($n = 0; $n < 5; $n++) {
            $this->postJson('/api/reports', [])->assertUnprocessable();
        }
        $this->postJson('/api/reports', [])->assertStatus(429);
        (new AppServiceProvider($this->app))->boot();
        for ($n = 0; $n < 10; $n++) {
            $this->postJson('/api/reports', [])->assertUnprocessable();
        }
        $this->postJson('/api/reports', [])->assertStatus(429);
        $this->travel(61)->seconds();
        $this->postJson('/api/reports', [])->assertUnprocessable();
    }

    public function test_upload_attempts_count_validation_failures_and_cannot_spoof_forwarded_ip(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10']);
        for ($n = 0; $n < 10; $n++) {
            $this->withHeaders(['X-Forwarded-For' => '198.51.100.'.$n, 'Forwarded' => 'for=198.51.100.'.$n])->postJson('/api/reports', [])->assertUnprocessable();
        }
        $this->withHeaders(['X-Forwarded-For' => '198.51.100.99'])->postJson('/api/reports', [])->assertStatus(429)->assertHeader('Retry-After')->assertJsonPath('message', '每個來源 IP 每分鐘最多新增嘗試 10 次，請稍後再試。');
        $this->getJson('/api/reports')->assertOk();
        $this->travel(61)->seconds();
        $this->postJson('/api/reports', [])->assertUnprocessable();
    }

    public function test_only_explicit_trusted_proxy_can_supply_forwarded_ip(): void
    {
        config(['inspection.trusted_proxies' => ['10.0.0.2']]);
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])->withHeaders(['X-Forwarded-For' => '198.51.100.20']);
        for ($n = 0; $n < 10; $n++) {
            $this->postJson('/api/reports', [])->assertUnprocessable();
        }
        $this->postJson('/api/reports', [])->assertStatus(429);
        $this->withHeaders(['X-Forwarded-For' => '198.51.100.21'])->postJson('/api/reports', [])->assertUnprocessable();
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.12']);
        for ($n = 0; $n < 10; $n++) {
            $this->withHeaders(['X-Forwarded-For' => '198.51.100.'.$n])->postJson('/api/reports', [])->assertUnprocessable();
        }
        $this->postJson('/api/reports', [])->assertStatus(429);
    }
}

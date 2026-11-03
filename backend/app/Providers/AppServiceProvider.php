<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('report-create', fn (Request $request) => Limit::perMinute(10)
            ->by('report-create-minute:'.$request->ip())
            ->response(fn (Request $request, array $headers) => response()->json([
                'data' => null, 'message' => '每個來源 IP 每分鐘最多新增嘗試 10 次，請稍後再試。', 'errors' => (object) [],
            ], 429, $headers)));
        RateLimiter::for('report-delete', fn (Request $request) => Limit::perMinute(5)
            ->by('report-delete:'.$request->ip())
            ->response(fn (Request $request, array $headers) => response()->json([
                'data' => null, 'message' => '刪除嘗試過於頻繁，請一分鐘後再試。', 'errors' => (object) [],
            ], 429, $headers)));
    }
}

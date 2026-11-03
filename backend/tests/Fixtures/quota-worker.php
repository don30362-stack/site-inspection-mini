<?php

// Subprocess fixture: own SQLite, Storage and file cache; never the developer's data.
use App\Models\Report;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\Console\Input\ArrayInput;

require dirname(__DIR__, 2).'/vendor/autoload.php';
[$script, $root, $mode] = $argv;
foreach (['storage/app/public', 'storage/app/private', 'storage/framework/cache/data', 'storage/logs'] as $directory) {
    if (! is_dir($root.'/'.$directory)) {
        mkdir($root.'/'.$directory, 0777, true);
    }
}
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->useStoragePath($root.'/storage');
$app->afterBootstrapping(LoadConfiguration::class, function ($app) use ($root) {
    $app['config']->set([
        'database.default' => 'sqlite',
        'database.connections.sqlite.database' => $root.'/database.sqlite',
        'database.connections.sqlite.url' => null,
        'cache.default' => 'file',
        'cache.limiter' => 'file',
        'cache.stores.file.path' => $root.'/storage/framework/cache/data',
        'cache.stores.file.lock_path' => $root.'/storage/framework/cache/data',
        'inspection.photo_storage_limit_bytes' => filesize(__DIR__.'/photo.png'),
    ]);
});
if ($mode === 'init') {
    exit($app->handleCommand(new ArrayInput(['command' => 'migrate', '--force' => true, '--no-interaction' => true])));
}
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
if ($mode === 'first') {
    Report::creating(function () use ($root) {
        touch($root.'/holding');
        $deadline = microtime(true) + 10;
        while (! file_exists($root.'/release')) {
            if (microtime(true) > $deadline) {
                throw new RuntimeException('Test barrier timed out.');
            }
            usleep(10000);
            clearstatcache(true, $root.'/release');
        }
    });
}
$request = Request::create('/api/reports', 'POST', [
    'site_name' => '並發隔離測試', 'issue_type' => '水電', 'severity' => 'low', 'description' => '只剩一張空間',
], [], ['photos' => [UploadedFile::fake()->createWithContent('photo.png', file_get_contents(__DIR__.'/photo.png'))]], [
    'HTTP_ACCEPT' => 'application/json', 'REMOTE_ADDR' => $mode === 'first' ? '203.0.113.1' : '203.0.113.2',
]);
$response = $kernel->handle($request);
file_put_contents($root.'/'.$mode.'.json', json_encode(['status' => $response->getStatusCode(), 'body' => json_decode($response->getContent(), true)]));
$kernel->terminate($request, $response);

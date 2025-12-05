<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

echo "=== CACHE INVESTIGATION ===\n\n";

// Check cache table
echo "1. Database cache table:\n";
$cacheEntries = DB::table('cache')->get(['key', 'expiration']);
echo "   Total entries: " . $cacheEntries->count() . "\n";
foreach ($cacheEntries->take(5) as $entry) {
    $expiresAt = date('Y-m-d H:i:s', $entry->expiration);
    $ttl = $entry->expiration - time();
    echo "   - Key: " . substr($entry->key, 0, 50) . "\n";
    echo "     Expires: $expiresAt (TTL: {$ttl}s)\n";
}

echo "\n2. Testing cache write:\n";
Cache::put('investigation_test', 'test_value', 300);
echo "   ✓ Wrote 'investigation_test' with 300s TTL\n";

// Check where it went
$dbCheck = DB::table('cache')->where('key', 'like', '%investigation_test%')->first();
if ($dbCheck) {
    $ttl = $dbCheck->expiration - time();
    echo "   ✓ Found in DATABASE with TTL: {$ttl}s\n";
} else {
    echo "   ✗ NOT in database\n";
}

// Check Redis
$redis = new Predis\Client([
    'scheme' => 'tcp',
    'host' => '127.0.0.1',
    'port' => 6379,
    'database' => 1
]);

$redisKeys = $redis->keys('*investigation_test*');
if (count($redisKeys) > 0) {
    echo "   ✓ Found in REDIS: " . implode(', ', $redisKeys) . "\n";
    foreach ($redisKeys as $key) {
        $ttl = $redis->ttl($key);
        echo "     TTL: {$ttl}s\n";
    }
} else {
    echo "   ✗ NOT in Redis\n";
}

echo "\n3. Cache driver info:\n";
echo "   Driver: " . config('cache.default') . "\n";
echo "   Store class: " . get_class(Cache::getStore()) . "\n";

// Check Redis connection from Laravel
try {
    $laravelRedis = Illuminate\Support\Facades\Redis::connection('cache');
    $laravelRedis->set('direct_test_' . time(), 'direct');
    $allKeys = $laravelRedis->keys('*');
    echo "   Redis keys via Laravel: " . count($allKeys) . "\n";
    if (count($allKeys) > 0) {
        echo "   Sample keys:\n";
        foreach (array_slice($allKeys, 0, 3) as $key) {
            $ttl = $laravelRedis->ttl($key);
            $type = $laravelRedis->type($key);
            echo "     - $key (type: $type, ttl: $ttl)\n";
        }
    }
} catch (Exception $e) {
    echo "   Redis error: " . $e->getMessage() . "\n";
}

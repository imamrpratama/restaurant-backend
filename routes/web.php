<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

Route::get('/', function () {
    return view('welcome');
});

// Test cache routes
Route::get('/test-cache', function () {
    $results = [];

    // Test 1: Check config
    $results['cache_driver'] = config('cache.default');
    $results['cache_connection'] = config('cache.stores.redis.connection');

    // Test 2: Store in cache
    Cache::put('test_key_' . time(), 'Hello Redis!', 60);

    // Test 3: Check Redis directly
    try {
        $redis = Redis::connection('cache');
        $redis->set('direct_test', 'Direct Redis works!');
        $results['direct_redis'] = $redis->get('direct_test');

        // Get all keys in cache database
        $results['redis_keys'] = $redis->keys('*');
    } catch (\Exception $e) {
        $results['redis_error'] = $e->getMessage();
    }

    // Test 4: Check categories cache
    $results['categories_cached'] = Cache::has('categories');

    return response()->json($results);
});

Route::get('/populate-cache', function () {
    // Populate some cache
    Cache::put('test_1', 'value_1', 3600);
    Cache::put('test_2', 'value_2', 3600);
    Cache::put('categories:test', ['id' => 1, 'name' => 'Test'], 3600);

    try {
        $redis = Redis::connection('cache');
        $allKeys = $redis->keys('*');

        return response()->json([
            'message' => 'Cache populated',
            'total_keys' => count($allKeys),
            'keys' => $allKeys
        ]);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()]);
    }
});

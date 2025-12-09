<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\TableController;
use App\Http\Controllers\Api\OrderController;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

// Public routes
Route::middleware(['throttle:10,1'])->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/google-login', [AuthController::class, 'googleLogin']);
    Route::post('/check-email', [AuthController::class, 'checkEmailExists']);
});

// 2FA verification routes (requires temp token) - FIXED
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/verify-2fa', [AuthController::class, 'verify2FA']);
    Route::post('/verify-recovery-code', [AuthController::class, 'verifyRecoveryCode']);
});

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // 2FA Management
    Route::post('/2fa/enable', [AuthController::class, 'enable2FA']);
    Route::post('/2fa/confirm', [AuthController::class, 'confirm2FA']);
    Route::post('/2fa/disable', [AuthController::class, 'disable2FA']);

    // Categories
    Route::apiResource('categories', CategoryController::class);

    // Menus
    Route::apiResource('menus', MenuController::class);

    // Tables
    Route::apiResource('tables', TableController::class);

    // Orders
    Route::get('/kitchen-display', [OrderController::class, 'kitchenDisplay']);
    Route::patch('/orders/{order}/status', [OrderController::class, 'updateOrderStatus']);
    Route::apiResource('orders', OrderController::class)->except(['update']);
});

Route::get('/test-redis', function () {
    $results = [];

    // Test 1: Basic cache
    Cache::put('test_key', 'Hello Redis!', 60);
    $results['test_1'] = Cache::get('test_key');

    // Test 2: Check categories cache
    $results['categories_cached'] = Cache::has('categories');

    // Test 3: Get all cache keys with proper TTL
    $predis = new \Predis\Client([
        'scheme' => 'tcp',
        'host' => config('database.redis.cache.host'),
        'port' => config('database.redis.cache.port'),
        'database' => config('database.redis.cache.database'),
    ]);

    $allKeys = $predis->keys('*');
    $results['total_keys'] = count($allKeys);
    $results['cache_keys'] = array_map(function($key) use ($predis) {
        $cleanKey = preg_replace('/^restaurantapp-database-restaurant_/', '', $key);
        $ttl = $predis->ttl($key);
        return [
            'key' => $cleanKey,
            'ttl_seconds' => $ttl > 0 ? $ttl : ($ttl === -1 ? 'permanent' : 'expired'),
        ];
    }, $allKeys);

    // Test 4: Redis info
    try {
        $predis->ping();
        $results['redis_connected'] = true;
    } catch (\Exception $e) {
        $results['redis_connected'] = false;
    }

    return response()->json([
        'message' => 'Redis is working!',
        'results' => $results
    ]);
});

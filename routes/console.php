<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Cache;
use App\Models\Order;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule cache population every 30 seconds
Schedule::call(function () {
    $order = Order::all();
    Cache::put('order:all', $order, 360);

    $kitchenOrders = Order::with(['items.menu', 'table', 'user'])
        ->whereIn('status', ['pending', 'processing'])
        ->latest()
        ->get();
    Cache::put('kitchen_display:all', $kitchenOrders, 360);
})->everyThirtySeconds();

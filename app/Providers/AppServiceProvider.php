<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Cache;
use App\Models\Order;

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
        // Populate order caches on app boot
        $this->populateOrderCaches();
    }

    /**
     * Populate order caches
     */
    private function populateOrderCaches(): void
    {
        try {
            // Cache all orders
            if (!Cache::has('order:all')) {
                $orders = Order::all();
                Cache::put('order:all', $orders, 360);
            }

            // Cache kitchen display orders
            if (!Cache::has('kitchen_display:all')) {
                $kitchenOrders = Order::with(['items.menu', 'table', 'user'])
                    ->whereIn('status', ['pending', 'processing'])
                    ->latest()
                    ->get();
                Cache::put('kitchen_display:all', $kitchenOrders, 360);
            }
        } catch (\Exception $e) {
            // Silently fail if there's an issue (e.g., database not ready)
            \Log::debug('Failed to populate order caches on boot', ['error' => $e->getMessage()]);
        }
    }
}

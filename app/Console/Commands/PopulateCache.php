<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use App\Models\Category;
use App\Models\Menu;
use App\Models\Table;
use App\Models\Order;

class PopulateCache extends Command
{
    protected $signature = 'cache:populate';
    protected $description = 'Populate cache with actual application data';

    public function handle()
    {
        $this->info('🔄 Populating cache with application data...');
        $this->newLine();

        // Categories
        $categories = Category::all();
        Cache::put('categories', $categories, 360);
        $this->line('✅ Categories cached: ' . $categories->count() . ' items (1 hour)');

        // Menus
        $menus = Menu::with('category')->get();
        Cache::put('menus_all', $menus, 360);
        $this->line('✅ Menus cached: ' . $menus->count() . ' items (1 hour)');

        // Tables
        $tables = Table::all();
        Cache::put('tables:all', $tables, 360);
        $this->line('✅ Tables cached: ' . $tables->count() . ' items (1 hour)');

        $order = Order::all();
        Cache::put('order:all', $order, 360);
        $this->line('✅ order cached: ' . $order->count() . ' items (1 hour)');

        // Kitchen Display (pending/processing orders)
        $kitchenOrders = Order::with(['items.menu', 'table', 'user'])
            ->whereIn('status', ['pending', 'processing'])
            ->latest()
            ->get();
        Cache::put('kitchen_display:all', $kitchenOrders, 360);
        $this->line('✅ Kitchen Display cached: ' . $kitchenOrders->count() . ' orders (30 seconds)');

        $this->newLine();
        $this->info('✨ Cache population complete!');
        $this->comment('💡 Run `php artisan redis:monitor` to view cache status');

        return Command::SUCCESS;
    }
}

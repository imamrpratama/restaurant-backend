<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class ClearAppCache extends Command
{
    protected $signature = 'app:clear-cache
                            {--all : Clear all cache}
                            {--categories : Clear categories cache}
                            {--menus : Clear menus cache}
                            {--pattern= : Clear cache by pattern}';

    protected $description = 'Clear application cache with specific patterns';

    public function handle()
    {
        if ($this->option('all')) {
            Cache::flush();
            $this->info('✅ All cache cleared!');
            return Command::SUCCESS;
        }

        $cleared = false;

        if ($this->option('categories')) {
            $this->clearPattern('*categories*');
            $this->info('✅ Categories cache cleared! ');
            $cleared = true;
        }

        if ($this->option('menus')) {
            $this->clearPattern('*menus*');
            $this->info('✅ Menus cache cleared!');
            $cleared = true;
        }

        if ($pattern = $this->option('pattern')) {
            $this->clearPattern($pattern);
            $this->info("✅ Cache pattern '{$pattern}' cleared!");
            $cleared = true;
        }

        if (! $cleared) {
            $this->warn('No cache cleared.  Use --all, --categories, --menus, or --pattern');
            $this->line('Examples:');
            $this->line('  php artisan app:clear-cache --all');
            $this->line('  php artisan app:clear-cache --categories');
            $this->line('  php artisan app:clear-cache --pattern="categories:*"');
        }

        return Command::SUCCESS;
    }

    private function clearPattern($pattern)
    {
        $prefix = config('cache.prefix');
        $keys = Redis::keys($prefix .  $pattern);

        foreach ($keys as $key) {
            $cleanKey = str_replace($prefix, '', $key);
            Cache::forget($cleanKey);
        }
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;

class RedisMonitor extends Command
{
    protected $signature = 'redis:monitor {--watch : Watch mode (refresh every 2 seconds)}';
    protected $description = 'Monitor Redis cache in real-time';

    public function handle()
    {
        if ($this->option('watch')) {
            $this->watchMode();
        } else {
            $this->displayOnce();
        }

        return Command::SUCCESS;
    }

    private function watchMode()
    {
        while (true) {
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                system('cls');
            } else {
                system('clear');
            }

            $this->displayOnce();
            $this->newLine();
            $this->info('Press Ctrl+C to stop watching.. .');
            sleep(2);
        }
    }

    private function displayOnce()
    {
        $this->info('╔════════════════════════════════════════╗');
        $this->info('║       Redis Cache Monitor              ║');
        $this->info('╚════════════════════════════════════════╝');
        $this->newLine();

        try {
            // Use direct Predis client to avoid prefix issues
            $predis = new \Predis\Client([
                'scheme' => 'tcp',
                'host' => config('database.redis.cache.host'),
                'port' => config('database.redis.cache.port'),
                'database' => config('database.redis.cache.database'),
            ]);

            $predis->ping();
            $this->info('📡 Connection: ✅ Connected');
            $this->newLine();

            // Get all cache keys
            $allKeys = $predis->keys('*');
            $cacheKeys = $allKeys; // All keys in cache connection are cache keys

            $this->info("📊 Statistics:");
            $this->line("  Total Redis Keys: " . count($allKeys));
            $this->line("  App Cache Keys: " . count($cacheKeys));
            $this->newLine();

            // Display cache keys
            if (! empty($cacheKeys)) {
                $this->info('💾 Cached Items:');
                $this->newLine();

                $table = [];
                foreach ($cacheKeys as $key) {
                    // Remove the full Redis prefix to show clean key
                    $cleanKey = preg_replace('/^restaurantapp-database-restaurant_/', '', $key);
                    $ttl = $predis->ttl($key);
                    $type = $predis->type($key);

                    $ttlText = $ttl > 0 ? "{$ttl}s" : ($ttl === -1 ? 'permanent' : 'expired');

                    // Get value size
                    $size = 'N/A';
                    if ($type === 'string') {
                        $value = $predis->get($key);
                        $size = strlen($value) .  ' bytes';
                    }

                    $table[] = [
                        'key' => substr($cleanKey, 0, 40),
                        'ttl' => $ttlText,
                        'type' => $type,
                        'size' => $size,
                    ];
                }

                $this->table(
                    ['Cache Key', 'TTL', 'Type', 'Size'],
                    array_map(fn($row) => array_values($row), $table)
                );
            } else {
                $this->warn('  No cached items found');
            }

            $this->newLine();

            // Application-specific checks with detailed info
            $this->info('🔍 Application Cache Summary:');

            // Categories
            $categoryStatus = Cache::has('categories') ? '✅ Cached' : '❌ Not cached';
            if (Cache::has('categories')) {
                $categoryKey = $predis->keys('*categories*')[0] ?? null;
                if ($categoryKey) {
                    $ttl = $predis->ttl($categoryKey);
                    $categoryStatus .= " ({$ttl}s)";
                }
            }
            $this->line('  Categories: ' . $categoryStatus);

            // Menus
            $menuKeys = $predis->keys('*menus*');
            $menuStatus = count($menuKeys) > 0 ? '✅ ' . count($menuKeys) . ' cached' : '❌ Not cached';
            if (count($menuKeys) > 0) {
                $ttl = $predis->ttl($menuKeys[0]);
                $menuStatus .= " ({$ttl}s)";
            }
            $this->line('  Menus: ' .  $menuStatus);

            // Tables
            $tableKeys = $predis->keys('*table*');
            $tableStatus = count($tableKeys) > 0 ? '✅ ' . count($tableKeys) . ' cached' : '❌ Not cached';
            if (count($tableKeys) > 0) {
                $ttl = $predis->ttl($tableKeys[0]);
                $tableStatus .= " ({$ttl}s)";
            }
            $this->line('  Tables: ' .  $tableStatus);

            // Orders
            $orderKeys = $predis->keys('*order*');
            $orderStatus = count($orderKeys) > 0 ? '✅ ' . count($orderKeys) . ' cached' : '❌ Not cached';
            if (count($orderKeys) > 0) {
                $ttl = $predis->ttl($orderKeys[0]);
                $orderStatus .= " ({$ttl}s)";
            }
            $this->line('  Orders: ' .  $orderStatus);

            // Kitchen Display
            $kitchenKeys = $predis->keys('*kitchen*');
            $kitchenStatus = count($kitchenKeys) > 0 ? '✅ ' . count($kitchenKeys) . ' cached' : '❌ Not cached';
            if (count($kitchenKeys) > 0) {
                $ttl = $predis->ttl($kitchenKeys[0]);
                $kitchenStatus .= " ({$ttl}s)";
            }
            $this->line('  Kitchen Display: ' .  $kitchenStatus);

        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
        }
    }
}

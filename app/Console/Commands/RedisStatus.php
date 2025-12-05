<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;

class RedisStatus extends Command
{
    protected $signature = 'redis:status';
    protected $description = 'Check Redis connection and display statistics';

    public function handle()
    {
        $this->info('=== Redis Status ===');
        $this->newLine();

        try {
            // Test connection
            $pong = Redis::ping();
            $this->info("✅ Redis Connection: {$pong}");

            // Get Redis info
            $info = Redis::info();

            $this->newLine();
            $this->info('📊 Redis Statistics:');
            $this->line("  - Version: " . ($info['redis_version'] ?? 'N/A'));
            $this->line("  - Uptime: " . ($info['uptime_in_seconds'] ?? 0) . " seconds");
            $this->line("  - Connected Clients: " . ($info['connected_clients'] ?? 0));
            $this->line("  - Used Memory: " . ($info['used_memory_human'] ?? 'N/A'));
            $this->line("  - Total Keys: " . $this->getTotalKeys());

            $this->newLine();
            $this->info('💾 Cache Statistics:');
            $this->displayCacheKeys();

            $this->newLine();
            $this->info('✅ Redis is working properly!');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Redis Connection Failed! ');
            $this->error('Error: ' . $e->getMessage());
            $this->newLine();
            $this->warn('Make sure Redis Docker container is running:');
            $this->line('  docker-compose up -d');

            return Command::FAILURE;
        }
    }

    private function getTotalKeys()
    {
        try {
            $keys = Redis::keys('*');
            return count($keys);
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function displayCacheKeys()
    {
        try {
            $prefix = config('cache.prefix');
            $keys = Redis::keys($prefix .  '*');

            if (empty($keys)) {
                $this->line('  No cached keys found');
                return;
            }

            $this->line('  Cached Keys:');
            foreach (array_slice($keys, 0, 10) as $key) {
                $cleanKey = str_replace($prefix, '', $key);
                $ttl = Redis::ttl($key);
                $ttlText = $ttl > 0 ? "{$ttl}s" : 'permanent';
                $this->line("    - {$cleanKey} (TTL: {$ttlText})");
            }

            if (count($keys) > 10) {
                $this->line("    ... and " . (count($keys) - 10) . " more");
            }
        } catch (\Exception $e) {
            $this->line('  Unable to fetch cache keys');
        }
    }
}

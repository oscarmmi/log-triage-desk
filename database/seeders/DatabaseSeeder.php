<?php

namespace Database\Seeders;

use App\Models\LogEvent;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $fixtures = [
            ['checkout-api', 'ERROR POST /api/checkout failed with HTTP 500 for order_id=ord_a81d2c'],
            ['checkout-api', 'ERROR POST /api/checkout failed with HTTP 500 for order_id=ord_b72e3f'],
            ['checkout-api', 'ERROR POST /api/checkout failed with HTTP 500 for order_id=ord_c63f4a'],
            ['checkout-api', 'ERROR POST /api/checkout failed with HTTP 500 for order_id=ord_d54a5b'],
            ['webhook-worker', 'WARN webhook delivery timed out after 10s for endpoint evt_91ab23'],
            ['webhook-worker', 'WARN webhook delivery timed out after 10s for endpoint evt_82bc34'],
            ['webhook-worker', 'WARN webhook delivery timed out after 10s for endpoint evt_73cd45'],
            ['webhook-worker', 'WARN webhook delivery timed out after 10s for endpoint evt_64de56'],
            ['platform-health', 'INFO platform health check healthy: 8 workers available'],
            ['platform-health', 'INFO platform health check healthy: 12 workers available'],
            ['platform-health', 'INFO platform health check healthy: 240 cache keys available'],
            ['report-exporter', 'ERROR invoice export checksum mismatch in archive shard north-4'],
        ];

        foreach ($fixtures as $index => [$source, $message]) {
            LogEvent::query()->firstOrCreate(
                ['source' => $source, 'message' => $message],
                ['occurred_at' => now()->subMinutes(count($fixtures) - $index)],
            );
        }
    }
}

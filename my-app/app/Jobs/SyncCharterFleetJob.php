<?php

namespace App\Jobs;

use App\Services\CharterFleetSyncer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SyncCharterFleetJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // A full sync pages through thousands of records with a throttling
    // delay between requests, so it can legitimately run for several minutes.
    public $timeout = 0;

    public const CACHE_RUNNING_KEY = 'charter_fleet_sync_running';

    public const CACHE_SUMMARY_KEY = 'charter_fleet_sync_summary';

    public function handle(CharterFleetSyncer $syncer): void
    {
        try {
            $result = $syncer->sync();

            Cache::put(self::CACHE_SUMMARY_KEY, [
                'imported' => $result['imported'],
                'updated' => $result['updated'],
                'skipped' => $result['skipped'],
                'finished_at' => now()->toIso8601String(),
                'failed' => false,
            ], now()->addDay());
        } catch (\RuntimeException $e) {
            Log::error('fleet:sync failed', ['message' => $e->getMessage()]);

            Cache::put(self::CACHE_SUMMARY_KEY, [
                'error' => $e->getMessage(),
                'finished_at' => now()->toIso8601String(),
                'failed' => true,
            ], now()->addDay());
        } finally {
            Cache::forget(self::CACHE_RUNNING_KEY);
        }
    }
}

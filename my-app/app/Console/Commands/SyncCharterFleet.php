<?php

namespace App\Console\Commands;

use App\Services\CharterFleetSyncer;
use Illuminate\Console\Command;

class SyncCharterFleet extends Command
{
    protected $signature = 'fleet:sync';

    protected $description = 'Sync the Charter Fleet Directory from the Aviapages charter_aircraft API';

    public function handle(CharterFleetSyncer $syncer): int
    {
        $this->info('Syncing charter fleet from Aviapages...');

        try {
            $result = $syncer->sync(fn (string $line) => $this->line($line));
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            [
                ['Imported (new)', $result['imported']],
                ['Updated (existing)', $result['updated']],
                ['Skipped', $result['skipped']],
            ]
        );

        if ($result['skipped_reasons'] !== []) {
            $this->warn(sprintf('%d record(s) skipped — see storage/logs for the full list. First few:', $result['skipped']));

            foreach (array_slice($result['skipped_reasons'], 0, 15) as $reason) {
                $this->line("  - {$reason}");
            }
        }

        $this->info('Done.');

        return self::SUCCESS;
    }
}

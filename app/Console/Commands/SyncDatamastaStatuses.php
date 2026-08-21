<?php

namespace App\Console\Commands;

use App\Services\DataMastaOrderStatusSyncService;
use Illuminate\Console\Command;

class SyncDatamastaStatuses extends Command
{
    protected $signature = 'orders:sync-datamasta';
    protected $description = 'Sync order statuses from DataMasta';

    public function handle(DataMastaOrderStatusSyncService $syncService)
    {
        $this->info('Syncing DataMasta order statuses...');
        $syncService->syncOrderStatuses();
        $this->info('Done.');
    }
}

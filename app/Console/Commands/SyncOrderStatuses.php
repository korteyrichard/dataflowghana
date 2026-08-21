<?php

namespace App\Console\Commands;

use App\Services\OrderStatusSyncService;
use Illuminate\Console\Command;

class SyncOrderStatuses extends Command
{
    protected $signature = 'orders:sync-statuses';
    protected $description = 'Sync order statuses from all order pushers';

    public function handle(OrderStatusSyncService $syncService)
    {
        if (!(bool) \App\Models\Setting::get('order_sync_enabled', 1)) {
            $this->info('Order syncing is disabled. Skipping.');
            return;
        }

        $this->info('Syncing order statuses...');
        $syncService->syncOrderStatuses();
        $this->info('Order statuses synced successfully!');
    }
}

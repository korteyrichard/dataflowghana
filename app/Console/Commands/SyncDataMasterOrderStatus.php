<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\DataMasterOrderStatusSyncService;
use App\Services\MoolreSmsService;

class SyncDataMasterOrderStatus extends Command
{
    protected $signature = 'orders:sync-datamaster';
    protected $description = 'Sync DataMaster order statuses';

    public function handle()
    {
        $this->info('Starting DataMaster order status sync...');
        
        $smsService = new MoolreSmsService();
        $syncService = new DataMasterOrderStatusSyncService($smsService);
        $syncService->syncOrderStatuses();
        
        $this->info('DataMaster order status sync completed.');
    }
}

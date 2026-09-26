<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class BundlePortalMtnOrderStatusSyncService
{
    public function syncOrderStatuses(): void
    {
        Log::info('BundlePortal MTN order statuses are delivered by webhook; polling is disabled for API v2.');
    }
}

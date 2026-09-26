<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class BundlePortalOrderStatusSyncService
{
    public function syncOrderStatuses(): void
    {
        Log::info('BundlePortal order statuses are delivered by webhook; polling is disabled for API v2.');
    }
}

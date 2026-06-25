<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\OrderWebhookService;

class OrderObserver
{
    public function updated(Order $order): void
    {
        if ($order->isDirty('status')) {
            OrderWebhookService::send($order, $order->getOriginal('status'));
        }
    }
}

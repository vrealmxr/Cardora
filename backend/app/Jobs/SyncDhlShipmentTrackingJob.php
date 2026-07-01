<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\DhlShipmentWorkflowService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncDhlShipmentTrackingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $orderId)
    {
    }

    public function handle(DhlShipmentWorkflowService $workflow): void
    {
        $order = Order::query()->find($this->orderId);

        if (! $order) {
            return;
        }

        $workflow->syncTrackingForOrder($order);
    }
}

<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\BoxNowShipmentWorkflowService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class CreateBoxNowShipmentForOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $orderId)
    {
    }

    public function handle(BoxNowShipmentWorkflowService $workflow): void
    {
        $order = Order::query()->find($this->orderId);

        if (! $order) {
            return;
        }

        $shipmentOrder = $workflow->createShipmentForOrder($order);

        if (blank($shipmentOrder->shipment_tracking_number ?: $shipmentOrder->tracking_number)) {
            return;
        }

        SyncBoxNowShipmentTrackingJob::dispatch($shipmentOrder->getKey());
        SyncBoxNowShipmentTrackingJob::dispatch($shipmentOrder->getKey())->delay(Carbon::now()->addMinutes(5));
        SyncBoxNowShipmentTrackingJob::dispatch($shipmentOrder->getKey())->delay(Carbon::now()->addMinutes(15));
    }
}

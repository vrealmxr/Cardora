<?php

namespace App\Console\Commands;

use App\Jobs\SyncBoxNowShipmentTrackingJob;
use App\Models\Order;
use Illuminate\Console\Command;

class SyncBoxNowShipmentTracking extends Command
{
    protected $signature = 'marketplace:sync-boxnow-shipments';

    protected $description = 'Poll BoxNow tracking updates for marketplace orders that are still in transit.';

    public function handle(): int
    {
        if (! config('services.boxnow.enabled')) {
            $this->info('BoxNow shipment sync is disabled.');

            return self::SUCCESS;
        }

        $orders = Order::query()
            ->where('shipping_carrier', 'boxnow')
            ->whereNotNull('shipment_tracking_number')
            ->whereIn('status', ['paid_pending_release'])
            ->whereNotIn('shipment_status', ['delivered'])
            ->orderBy('shipment_synced_at')
            ->get(['id']);

        foreach ($orders as $order) {
            SyncBoxNowShipmentTrackingJob::dispatch($order->getKey());
        }

        $this->info(sprintf('Queued %d BoxNow shipment(s) for tracking sync.', $orders->count()));

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Jobs\SyncDhlShipmentTrackingJob;
use App\Models\Order;
use Illuminate\Console\Command;

class SyncDhlShipmentTracking extends Command
{
    protected $signature = 'marketplace:sync-dhl-shipments';

    protected $description = 'Poll DHL tracking updates for marketplace orders that are still in transit.';

    public function handle(): int
    {
        if (! config('services.dhl.enabled')) {
            $this->info('DHL shipment sync is disabled.');

            return self::SUCCESS;
        }

        $orders = Order::query()
            ->where('shipping_carrier', 'dhl_express')
            ->whereNotNull('shipment_tracking_number')
            ->whereIn('status', ['paid_pending_release'])
            ->whereNotIn('shipment_status', ['delivered'])
            ->orderBy('shipment_synced_at')
            ->get(['id']);

        foreach ($orders as $order) {
            SyncDhlShipmentTrackingJob::dispatch($order->getKey());
        }

        $this->info(sprintf('Queued %d DHL shipment(s) for tracking sync.', $orders->count()));

        return self::SUCCESS;
    }
}

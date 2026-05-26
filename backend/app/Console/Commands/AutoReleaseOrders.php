<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Jobs\ReleaseOrderFundsJob;
use App\Models\Order;
use Illuminate\Console\Command;

class AutoReleaseOrders extends Command
{
    protected $signature = 'marketplace:auto-release-orders';

    protected $description = 'Automatically release seller funds for paid orders whose confirmation window has expired.';

    public function handle(): int
    {
        $orders = Order::query()
            ->where('status', OrderStatus::PaidPendingRelease->value)
            ->whereNotNull('auto_release_at')
            ->where('auto_release_at', '<=', now())
            ->orderBy('auto_release_at')
            ->get(['id']);

        foreach ($orders as $order) {
            ReleaseOrderFundsJob::dispatch($order->getKey());
        }

        $this->info(sprintf('Queued %d orders for automatic release.', $orders->count()));

        return self::SUCCESS;
    }
}

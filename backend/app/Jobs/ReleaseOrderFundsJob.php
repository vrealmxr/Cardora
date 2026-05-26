<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\StripeMarketplaceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ReleaseOrderFundsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $orderId)
    {
    }

    public function handle(StripeMarketplaceService $marketplaceService): void
    {
        $order = Order::query()->find($this->orderId);

        if (! $order) {
            return;
        }

        try {
            $marketplaceService->releaseFundsToSeller($order, [
                'buyer_confirmed' => false,
                'released_by' => 'auto_release_job',
                'auto_release' => true,
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Automatic seller funds release failed.', [
                'order_id' => $this->orderId,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}

<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\BoxNowShipmentWorkflowService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

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

        $workflow->createShipmentForOrder($order);
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status,
            'status_key' => $this->status,
            'escrow_status' => $this->escrow_status,
            'escrow_status_key' => $this->escrow_status,
            'subtotal' => $this->subtotal,
            'shipping_total' => $this->shipping_total,
            'service_fee' => $this->service_fee,
            'total' => $this->total,
            'total_amount' => $this->total_amount,
            'commission_amount' => $this->commission_amount,
            'seller_amount' => $this->seller_amount,
            'currency' => $this->currency,
            'payment_method' => $this->payment_method,
            'product_id' => $this->product_id,
            'stripe_checkout_session_id' => $this->stripe_checkout_session_id,
            'stripe_payment_intent_id' => $this->stripe_payment_intent_id,
            'stripe_charge_id' => $this->stripe_charge_id,
            'stripe_transfer_id' => $this->stripe_transfer_id,
            'tracking_number' => $this->tracking_number,
            'shipping_address' => $this->shipping_address,
            'billing_address' => $this->billing_address,
            'notes' => $this->notes,
            'metadata' => $this->metadata,
            'placed_at' => $this->placed_at,
            'buyer_confirmed_at' => $this->buyer_confirmed_at,
            'auto_release_at' => $this->auto_release_at,
            'released_at' => $this->released_at,
            'cancelled_at' => $this->cancelled_at,
            'refunded_at' => $this->refunded_at,
            'completed_at' => $this->completed_at,
            'disputed_at' => $this->disputed_at,
            'buyer' => new UserProfileResource($this->whenLoaded('buyer')),
            'seller' => new UserProfileResource($this->whenLoaded('seller')),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'escrow_transaction' => new EscrowTransactionResource($this->whenLoaded('escrowTransaction')),
        ];
    }
}

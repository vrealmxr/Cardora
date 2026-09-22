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
        $canViewAddresses = (bool) $request->user()
            && (
                (int) $request->user()->getKey() === (int) $this->buyer_id
                || (bool) $request->user()->is_admin
            );
        $trackingNumber = $this->shipment_tracking_number ?: $this->tracking_number;

        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'checkout_batch_id' => $this->checkout_batch_id,
            'checkoutBatchId' => $this->checkout_batch_id,
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
            'shipping_carrier' => $this->shipping_carrier,
            'shippingCarrier' => $this->shipping_carrier,
            'shipping_service' => $this->shipping_service,
            'shippingService' => $this->shipping_service,
            'shipment_status' => $this->shipment_status,
            'shipmentStatus' => $this->shipment_status,
            'product_id' => $this->product_id,
            'stripe_checkout_session_id' => $this->stripe_checkout_session_id,
            'stripe_payment_intent_id' => $this->stripe_payment_intent_id,
            'stripe_charge_id' => $this->stripe_charge_id,
            'stripe_transfer_id' => $this->stripe_transfer_id,
            'tracking_number' => $trackingNumber,
            'trackingNumber' => $trackingNumber,
            'shipment_tracking_number' => $this->shipment_tracking_number,
            'shipmentTrackingNumber' => $this->shipment_tracking_number,
            'shipment_reference' => $this->shipment_reference,
            'shipmentReference' => $this->shipment_reference,
            'shipping_address' => $this->when($canViewAddresses, $this->shipping_address),
            'billing_address' => $this->when($canViewAddresses, $this->billing_address),
            'notes' => $this->notes,
            'metadata' => $this->metadata,
            'shipment_metadata' => $this->shipment_metadata,
            'shipmentMetadata' => $this->shipment_metadata,
            'placed_at' => $this->placed_at,
            'shipped_at' => $this->shipped_at,
            'shippedAt' => $this->shipped_at,
            'delivered_at' => $this->delivered_at,
            'deliveredAt' => $this->delivered_at,
            'buyer_confirmed_at' => $this->buyer_confirmed_at,
            'auto_release_at' => $this->auto_release_at,
            'released_at' => $this->released_at,
            'cancelled_at' => $this->cancelled_at,
            'refunded_at' => $this->refunded_at,
            'completed_at' => $this->completed_at,
            'disputed_at' => $this->disputed_at,
            'shipment_last_event_code' => $this->shipment_last_event_code,
            'shipment_last_event_description' => $this->shipment_last_event_description,
            'shipment_last_event_at' => $this->shipment_last_event_at,
            'shipment_synced_at' => $this->shipment_synced_at,
            'buyer' => new UserProfileResource($this->whenLoaded('buyer')),
            'seller' => new UserProfileResource($this->whenLoaded('seller')),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'escrow_transaction' => new EscrowTransactionResource($this->whenLoaded('escrowTransaction')),
        ];
    }
}

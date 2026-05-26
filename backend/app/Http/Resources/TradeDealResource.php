<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TradeDealResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'trade_request_id' => $this->trade_request_id,
            'listing_id' => $this->listing_id,
            'owner_user_id' => $this->owner_user_id,
            'proposer_user_id' => $this->proposer_user_id,
            'dispute_opened_by_user_id' => $this->dispute_opened_by_user_id,
            'winner_user_id' => $this->winner_user_id,
            'status' => $this->status,
            'owner_declared_value' => $this->owner_declared_value,
            'proposer_declared_value' => $this->proposer_declared_value,
            'deposit_amount' => $this->deposit_amount,
            'fee_rate' => $this->fee_rate,
            'owner_gross_amount' => $this->owner_gross_amount,
            'proposer_gross_amount' => $this->proposer_gross_amount,
            'owner_fee_amount' => $this->owner_fee_amount,
            'proposer_fee_amount' => $this->proposer_fee_amount,
            'owner_net_amount' => $this->owner_net_amount,
            'proposer_net_amount' => $this->proposer_net_amount,
            'platform_fee_amount' => $this->platform_fee_amount,
            'currency' => $this->currency,
            'owner_stripe_checkout_session_id' => $this->owner_stripe_checkout_session_id,
            'proposer_stripe_checkout_session_id' => $this->proposer_stripe_checkout_session_id,
            'owner_stripe_payment_intent_id' => $this->owner_stripe_payment_intent_id,
            'proposer_stripe_payment_intent_id' => $this->proposer_stripe_payment_intent_id,
            'owner_stripe_charge_id' => $this->owner_stripe_charge_id,
            'proposer_stripe_charge_id' => $this->proposer_stripe_charge_id,
            'owner_paid_at' => $this->owner_paid_at,
            'proposer_paid_at' => $this->proposer_paid_at,
            'funded_at' => $this->funded_at,
            'owner_released_at' => $this->owner_released_at,
            'proposer_released_at' => $this->proposer_released_at,
            'settled_at' => $this->settled_at,
            'disputed_at' => $this->disputed_at,
            'resolved_at' => $this->resolved_at,
            'cancelled_at' => $this->cancelled_at,
            'resolution' => $this->resolution,
            'resolution_notes' => $this->resolution_notes,
            'payout_metadata' => $this->payout_metadata,
            'metadata' => $this->metadata,
            'listing_bundle' => data_get($this->metadata, 'listing_bundle', []),
            'owner_listing_ids' => data_get($this->metadata, 'listing_bundle.owner_listing_ids', []),
            'proposer_listing_ids' => data_get($this->metadata, 'listing_bundle.proposer_listing_ids', []),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'is_owner_paid' => $this->owner_paid_at !== null,
            'is_proposer_paid' => $this->proposer_paid_at !== null,
            'is_funded' => $this->bothPaid(),
            'is_owner_released' => $this->owner_released_at !== null,
            'is_proposer_released' => $this->proposer_released_at !== null,
            'is_fully_released' => $this->bothReleased(),
            'trade_request' => new TradeRequestResource($this->whenLoaded('tradeRequest')),
            'listing' => new ListingResource($this->whenLoaded('listing')),
            'owner' => new UserProfileResource($this->whenLoaded('owner')),
            'proposer' => new UserProfileResource($this->whenLoaded('proposer')),
            'winner' => new UserProfileResource($this->whenLoaded('winner')),
        ];
    }
}

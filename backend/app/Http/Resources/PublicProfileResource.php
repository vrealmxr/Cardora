<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicProfileResource extends JsonResource
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
            'name' => $this->name,
            'display_name' => $this->display_name,
            'handle' => $this->handle,
            'city' => $this->city,
            'bio' => $this->bio,
            'collector_tagline' => $this->collector_tagline,
            'avatar_url' => $this->avatar_url,
            'profile_cover' => $this->profile_cover,
            'profile_visibility' => $this->profile_visibility,
            'trust_status' => $this->trust_status,
            'rating' => $this->rating,
            'sales_count' => $this->sales_count,
            'purchase_count' => $this->purchase_count,
            'is_verified_seller' => $this->is_verified_seller,
            'profile_likes_count' => $this->profile_likes_count ?? $this->whenCounted('profileLikesReceived'),
            'followers_count' => $this->followers_count ?? $this->whenCounted('profileFollowersReceived'),
            'following_count' => $this->following_count ?? $this->whenCounted('profileFollowsGiven'),
            'collection_entries_count' => $this->collection_entries_count ?? null,
            'active_listings_count' => $this->active_listings_count ?? null,
            'liked_by_auth_user' => (bool) ($this->liked_by_auth_user ?? false),
            'followed_by_auth_user' => (bool) ($this->followed_by_auth_user ?? false),
            'collection_entries' => CollectionEntryResource::collection($this->whenLoaded('collectionEntries')),
            'active_listings' => ListingResource::collection($this->whenLoaded('listings')),
            'recent_reviews' => ReviewResource::collection($this->whenLoaded('receivedReviews')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

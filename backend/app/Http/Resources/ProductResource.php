<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
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
            'category_id' => $this->category_id,
            'title' => $this->title,
            'slug' => $this->slug,
            'sku' => $this->sku,
            'subtitle' => $this->subtitle,
            'franchise' => $this->franchise,
            'series' => $this->series,
            'brand' => $this->brand,
            'year' => $this->year,
            'language' => $this->language,
            'set_name' => $this->set_name,
            'item_number' => $this->item_number,
            'description' => $this->description,
            'product_type' => $this->product_type,
            'specifications' => $this->specifications,
            'tags' => $this->tags,
            'media' => $this->media,
            'authenticity_notes' => $this->authenticity_notes,
            'is_authenticated' => $this->is_authenticated,
            'is_lot' => $this->is_lot,
            'lot_configuration' => $this->lot_configuration,
            'metadata' => $this->metadata,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'created_at' => $this->created_at,
        ];
    }
}

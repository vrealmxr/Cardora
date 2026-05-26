<?php

namespace App\Filament\Resources\ListingResource\Pages;

use App\Filament\Resources\ListingResource;
use Filament\Resources\Pages\CreateRecord;

class CreateListing extends CreateRecord
{
    protected static string $resource = ListingResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (($data['status'] ?? null) === 'published') {
            if (empty($data['published_at'])) {
                $data['published_at'] = now();
            }

            $quantity = (int) ($data['quantity'] ?? 0);
            $availableQuantity = (int) ($data['available_quantity'] ?? 0);

            if ($quantity > 0 && $availableQuantity <= 0) {
                $data['available_quantity'] = $quantity;
            }

            if (empty($data['availability']) || ($data['availability'] ?? null) === 'sold_out') {
                $data['availability'] = 'in_stock';
            }
        }

        return $data;
    }
}

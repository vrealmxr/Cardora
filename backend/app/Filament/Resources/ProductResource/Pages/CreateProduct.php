<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['media'] = ProductResource::dehydrateMediaStateForStorage($data['media'] ?? []);
        $data['lot_configuration'] = ProductResource::dehydrateLotConfigurationForStorage($data['lot_configuration'] ?? []);

        return $data;
    }
}

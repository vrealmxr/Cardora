<?php

namespace App\Filament\Resources\CardoraCampaignResource\Pages;

use App\Filament\Resources\CardoraCampaignResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCardoraCampaign extends CreateRecord
{
    protected static string $resource = CardoraCampaignResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return CardoraCampaignResource::mutateFormDataBeforeSave($data);
    }
}

<?php

namespace App\Filament\Resources\DrawCampaignResource\Pages;

use App\Filament\Resources\DrawCampaignResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateDrawCampaign extends CreateRecord
{
    protected static string $resource = DrawCampaignResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['campaign_type'] = 'community_raffle';

        return $data;
    }
}

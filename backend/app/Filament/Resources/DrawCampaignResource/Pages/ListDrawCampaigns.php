<?php

namespace App\Filament\Resources\DrawCampaignResource\Pages;

use App\Filament\Resources\DrawCampaignResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDrawCampaigns extends ListRecords
{
    protected static string $resource = DrawCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('New community raffle'),
        ];
    }
}

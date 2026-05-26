<?php

namespace App\Filament\Resources\CardoraCampaignResource\Pages;

use App\Filament\Resources\CardoraCampaignResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCardoraCampaigns extends ListRecords
{
    protected static string $resource = CardoraCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('New Cardora campaign'),
        ];
    }
}

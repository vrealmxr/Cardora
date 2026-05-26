<?php

namespace App\Filament\Resources\AuctionBidResource\Pages;

use App\Filament\Resources\AuctionBidResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewAuctionBid extends ViewRecord
{
    protected static string $resource = AuctionBidResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}

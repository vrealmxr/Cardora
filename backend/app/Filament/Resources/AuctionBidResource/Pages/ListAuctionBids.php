<?php

namespace App\Filament\Resources\AuctionBidResource\Pages;

use App\Filament\Resources\AuctionBidResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAuctionBids extends ListRecords
{
    protected static string $resource = AuctionBidResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

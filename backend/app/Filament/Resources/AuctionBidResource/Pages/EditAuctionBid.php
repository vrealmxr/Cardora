<?php

namespace App\Filament\Resources\AuctionBidResource\Pages;

use App\Filament\Resources\AuctionBidResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAuctionBid extends EditRecord
{
    protected static string $resource = AuctionBidResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}

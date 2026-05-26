<?php

namespace App\Filament\Resources\EscrowTransactionResource\Pages;

use App\Filament\Resources\EscrowTransactionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEscrowTransactions extends ListRecords
{
    protected static string $resource = EscrowTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

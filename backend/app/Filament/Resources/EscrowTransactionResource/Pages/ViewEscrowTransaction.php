<?php

namespace App\Filament\Resources\EscrowTransactionResource\Pages;

use App\Filament\Resources\EscrowTransactionResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewEscrowTransaction extends ViewRecord
{
    protected static string $resource = EscrowTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}

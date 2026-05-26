<?php

namespace App\Filament\Resources\EscrowTransactionResource\Pages;

use App\Filament\Resources\EscrowTransactionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEscrowTransaction extends EditRecord
{
    protected static string $resource = EscrowTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}

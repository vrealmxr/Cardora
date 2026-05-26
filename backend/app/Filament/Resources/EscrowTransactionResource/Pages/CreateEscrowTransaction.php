<?php

namespace App\Filament\Resources\EscrowTransactionResource\Pages;

use App\Filament\Resources\EscrowTransactionResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateEscrowTransaction extends CreateRecord
{
    protected static string $resource = EscrowTransactionResource::class;
}

<?php

namespace App\Filament\Resources\VerificationSubmissionResource\Pages;

use App\Filament\Resources\VerificationSubmissionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVerificationSubmissions extends ListRecords
{
    protected static string $resource = VerificationSubmissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

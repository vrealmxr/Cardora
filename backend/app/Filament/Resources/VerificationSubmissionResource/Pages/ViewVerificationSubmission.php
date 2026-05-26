<?php

namespace App\Filament\Resources\VerificationSubmissionResource\Pages;

use App\Filament\Resources\VerificationSubmissionResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewVerificationSubmission extends ViewRecord
{
    protected static string $resource = VerificationSubmissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}

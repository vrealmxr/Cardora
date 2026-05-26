<?php

namespace App\Filament\Resources\VerificationSubmissionResource\Pages;

use App\Filament\Resources\VerificationSubmissionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditVerificationSubmission extends EditRecord
{
    protected static string $resource = VerificationSubmissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

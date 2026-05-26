<?php

namespace App\Filament\Resources\CategoryAttributeDefinitionResource\Pages;

use App\Filament\Resources\CategoryAttributeDefinitionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCategoryAttributeDefinition extends EditRecord
{
    protected static string $resource = CategoryAttributeDefinitionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}

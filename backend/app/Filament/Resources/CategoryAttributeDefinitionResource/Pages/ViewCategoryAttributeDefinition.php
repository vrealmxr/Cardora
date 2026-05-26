<?php

namespace App\Filament\Resources\CategoryAttributeDefinitionResource\Pages;

use App\Filament\Resources\CategoryAttributeDefinitionResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewCategoryAttributeDefinition extends ViewRecord
{
    protected static string $resource = CategoryAttributeDefinitionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}

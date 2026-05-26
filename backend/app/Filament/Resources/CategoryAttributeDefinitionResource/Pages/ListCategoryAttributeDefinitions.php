<?php

namespace App\Filament\Resources\CategoryAttributeDefinitionResource\Pages;

use App\Filament\Resources\CategoryAttributeDefinitionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCategoryAttributeDefinitions extends ListRecords
{
    protected static string $resource = CategoryAttributeDefinitionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

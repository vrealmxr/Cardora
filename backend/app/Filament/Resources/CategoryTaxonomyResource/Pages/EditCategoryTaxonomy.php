<?php

namespace App\Filament\Resources\CategoryTaxonomyResource\Pages;

use App\Filament\Resources\CategoryTaxonomyResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCategoryTaxonomy extends EditRecord
{
    protected static string $resource = CategoryTaxonomyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}

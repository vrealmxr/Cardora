<?php

namespace App\Filament\Resources\CategoryTaxonomyResource\Pages;

use App\Filament\Resources\CategoryTaxonomyResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewCategoryTaxonomy extends ViewRecord
{
    protected static string $resource = CategoryTaxonomyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}

<?php

namespace App\Filament\Resources\CategoryTaxonomyResource\Pages;

use App\Filament\Resources\CategoryTaxonomyResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCategoryTaxonomies extends ListRecords
{
    protected static string $resource = CategoryTaxonomyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

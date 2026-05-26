<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['media'] = ProductResource::normalizeMediaStateForForm($data['media'] ?? []);
        $data['lot_configuration'] = ProductResource::normalizeLotConfigurationForForm($data['lot_configuration'] ?? []);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['media'] = ProductResource::dehydrateMediaStateForStorage($data['media'] ?? []);
        $data['lot_configuration'] = ProductResource::dehydrateLotConfigurationForStorage($data['lot_configuration'] ?? []);
        $data['metadata'] = array_replace_recursive(
            $this->record->metadata ?? [],
            $data['metadata'] ?? [],
        );

        return $data;
    }

    protected function afterSave(): void
    {
        if (! $this->record->is_authenticated) {
            return;
        }

        $this->record
            ->listings()
            ->whereIn('status', ['draft', 'pending_review', 'needs_revision', 'rejected'])
            ->get()
            ->each(function ($listing): void {
                $listing->update([
                    'status' => 'published',
                    'published_at' => $listing->published_at ?: now(),
                    'availability' => $listing->availability ?: 'in_stock',
                ]);
            });
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}

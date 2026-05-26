<?php

namespace App\Filament\Resources\CardoraCampaignResource\Pages;

use App\Filament\Resources\CardoraCampaignResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCardoraCampaign extends EditRecord
{
    protected static string $resource = CardoraCampaignResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return CardoraCampaignResource::mutateFormDataBeforeFill($data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return CardoraCampaignResource::mutateFormDataBeforeSave($data, $this->record);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('activate')
                ->label('Make active')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn (): bool => in_array($this->record->status, ['draft', 'review', 'cancelled'], true))
                ->action(function (): void {
                    $this->record->update([
                        'status' => 'active',
                    ]);

                    $this->fillForm();
                }),
            Actions\Action::make('sendToReview')
                ->label('Send to review')
                ->icon('heroicon-o-eye')
                ->color('warning')
                ->visible(fn (): bool => in_array($this->record->status, ['draft', 'cancelled'], true))
                ->action(function (): void {
                    $this->record->update([
                        'status' => 'review',
                    ]);

                    $this->fillForm();
                }),
            Actions\Action::make('moveToDraft')
                ->label('Move to draft')
                ->icon('heroicon-o-document')
                ->color('gray')
                ->visible(fn (): bool => in_array($this->record->status, ['review', 'active'], true))
                ->action(function (): void {
                    $this->record->update([
                        'status' => 'draft',
                    ]);

                    $this->fillForm();
                }),
            Actions\DeleteAction::make(),
        ];
    }
}

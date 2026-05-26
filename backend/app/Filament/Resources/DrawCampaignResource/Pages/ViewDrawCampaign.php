<?php

namespace App\Filament\Resources\DrawCampaignResource\Pages;

use App\Filament\Resources\DrawCampaignResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewDrawCampaign extends ViewRecord
{
    protected static string $resource = DrawCampaignResource::class;

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

                    $this->record = $this->record->fresh();
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

                    $this->record = $this->record->fresh();
                }),
            Actions\EditAction::make(),
        ];
    }
}

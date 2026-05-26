<?php

namespace App\Filament\Pages;

use App\Services\AdminAttentionCenterService;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;

class Dashboard extends \Filament\Pages\Dashboard
{
    public function mount(): void
    {
        foreach (app(AdminAttentionCenterService::class)->getDashboardNotifications() as $alert) {
            $notification = Notification::make()
                ->title($alert['title'])
                ->body($alert['body'])
                ->persistent()
                ->actions([
                    Action::make('openQueue')
                        ->label($alert['action'])
                        ->button()
                        ->url($alert['url']),
                ]);

            match ($alert['tone']) {
                'danger' => $notification->danger(),
                'warning' => $notification->warning(),
                'success' => $notification->success(),
                default => $notification->info(),
            };

            $notification->send();
        }
    }
}

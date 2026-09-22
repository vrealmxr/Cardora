<?php

namespace App\Filament\Widgets;

use App\Services\AdminAttentionCenterService;
use Filament\Widgets\Widget;

class AdminAttentionCenter extends Widget
{
    public static function canView(): bool
    {
        return ! (auth()->user()?->is_seo_editor ?? false);
    }

    protected static string $view = 'filament.widgets.admin-attention-center';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = -10;

    protected static ?string $pollingInterval = '15s';

    public function getViewData(): array
    {
        return app(AdminAttentionCenterService::class)->getDashboardPayload();
    }
}

<?php

namespace App\Filament\Resources;

use Filament\Resources\Resource;

/**
 * Base class for every Filament resource except the SEO-only ones. Hides the resource
 * (nav + direct URL access) from users flagged is_seo_editor, so that restricted admin
 * login only ever sees PageSeoSettingResource.
 */
abstract class RestrictableResource extends Resource
{
    public static function canAccess(): bool
    {
        return ! (auth()->user()?->is_seo_editor ?? false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supportedLocales = config('app.supported_locales', ['el', 'en']);

        $locale = $request->query('locale')
            ?: $request->header('X-Locale')
            ?: $request->input('locale')
            ?: $request->user()?->locale
            ?: config('app.locale');

        $normalizedLocale = Str::of((string) $locale)
            ->trim()
            ->lower()
            ->replace('_', '-')
            ->before('-')
            ->value();

        if (in_array($normalizedLocale, $supportedLocales, true)) {
            app()->setLocale($normalizedLocale);
        }

        return $next($request);
    }
}

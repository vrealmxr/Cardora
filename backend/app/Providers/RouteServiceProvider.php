<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            // Signed public renderer reads retain the guest limit per visitor, not per shared Worker IP.
            if ($visitor = \App\Services\PublicRenderSignature::verifiedVisitor($request)) {
                return Limit::perMinute(90)->by('public-render:' . $visitor);
            }
            if ($request->user()) {
                return Limit::perMinute(180)->by('user:' . $request->user()->getAuthIdentifier());
            }

            return Limit::perMinute(90)->by('ip:' . $request->ip());
        });

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }
}

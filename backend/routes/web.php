<?php

use App\Http\Controllers\Web\EmailVerificationRedirectController;
use App\Http\Controllers\Web\StripeConnectRedirectController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/stripe/connect/refresh/{account}', [StripeConnectRedirectController::class, 'refresh'])
    ->name('stripe.connect.refresh');

Route::get('/stripe/connect/success/{account}', [StripeConnectRedirectController::class, 'success'])
    ->name('stripe.connect.success');

Route::get('/email/verify/{id}/{hash}', EmailVerificationRedirectController::class)
    ->name('verification.verify');

Route::fallback(function () {
    if (
        request()->is('api/*') ||
        request()->is('sanctum/*') ||
        request()->is('css/filament/*') ||
        request()->is('js/filament/*') ||
        request()->is('livewire/*')
    ) {
        abort(404);
    }

    $spaEntry = public_path('index.html');

    if (!is_file($spaEntry)) {
        abort(500, 'Missing SPA index file.');
    }

    return response()->file($spaEntry);
});

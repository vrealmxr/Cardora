<?php

use App\Http\Controllers\Api\PublicPageDataController;
use App\Http\Controllers\Api\PublicListingController;
use Illuminate\Support\Facades\Route;

// The API group's stricter 90/minute guest limit applies, including signed renderer attribution.
Route::get('/public-page-data', PublicPageDataController::class);
Route::get('/public-listing-paths', [PublicListingController::class, 'paths']);
Route::get('/public-listings/{slug}', [PublicListingController::class, 'show']);
Route::get('/public-html/{digest}', \App\Http\Controllers\Api\PublicHtmlController::class)->where('digest', '[a-f0-9]{64}');

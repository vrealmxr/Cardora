<?php
use App\Http\Controllers\Api\CardScanController;
use Illuminate\Support\Facades\Route;

Route::get('card-scans/config',[CardScanController::class,'config']);
Route::middleware('auth:sanctum')->prefix('card-scans')->group(function () {
    Route::get('/mine',[CardScanController::class,'mine']);
    Route::post('/',[CardScanController::class,'store'])->middleware('throttle:2,1');
    Route::get('/{id}',[CardScanController::class,'show'])->whereUuid('id');
    Route::delete('/{id}',[CardScanController::class,'destroy'])->whereUuid('id');
});

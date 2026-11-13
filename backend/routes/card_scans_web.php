<?php
use App\Http\Controllers\DatasetReviewController;
use App\Http\Middleware\DatasetAdmin;
use Illuminate\Support\Facades\Route;

Route::redirect('/scanner', '/el/cardora-scanner', 302);

Route::prefix('admin/dataset-review')->middleware(DatasetAdmin::class)->group(function () {
    Route::get('/',[DatasetReviewController::class,'home']);
    Route::get('/session',[DatasetReviewController::class,'session']);
    Route::get('/{file}',[DatasetReviewController::class,'asset'])->where('file','review\.(css|js)');
    Route::get('/api/scans',[DatasetReviewController::class,'index']);
    Route::get('/api/scans/{id}',[DatasetReviewController::class,'show']);
    Route::get('/api/scans/{id}/images/{index}',[DatasetReviewController::class,'image'])->whereNumber('index');
    Route::post('/api/scans/{id}/review',[DatasetReviewController::class,'review']);
    Route::post('/api/scans/{id}/clear-usage',[DatasetReviewController::class,'clearance']);
    Route::post('/api/export',[DatasetReviewController::class,'export'])->middleware('throttle:2,1');
});

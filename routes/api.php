<?php

use App\Http\Controllers\Api\V1\StatsController;
use App\Http\Controllers\Api\V1\StrategyController;
use App\Http\Controllers\Api\V1\TradeAttachmentController;
use App\Http\Controllers\Api\V1\TradeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    Route::apiResource('trades', TradeController::class);
    Route::post('trades/{trade}/close', [TradeController::class, 'close']);
    Route::post('trades/{trade}/reopen', [TradeController::class, 'reopen']);

    Route::post('trades/{trade}/attachments', [TradeAttachmentController::class, 'store']);
    Route::delete('attachments/{attachment}', [TradeAttachmentController::class, 'destroy']);

    Route::get('stats', [StatsController::class, 'index']);

    Route::get('strategies', [StrategyController::class, 'index']);
    Route::post('strategies', [StrategyController::class, 'store']);
    Route::delete('strategies/{strategy}', [StrategyController::class, 'destroy']);
});

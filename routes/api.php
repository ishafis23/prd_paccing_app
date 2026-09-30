<?php

use App\Http\Controllers\Api\OrderItemController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::put('/order-items/{orderItem}', [OrderItemController::class, 'update']);
    Route::delete('/order-items/{orderItem}', [OrderItemController::class, 'destroy']);
});

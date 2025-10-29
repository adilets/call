<?php

use App\Http\Controllers\CardWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ZelleWebhookController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Zelle webhook to mark order as paid
Route::post('/webhooks/zelle', ZelleWebhookController::class);

Route::post('/webhooks/card', CardWebhookController::class);

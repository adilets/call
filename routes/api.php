<?php

use App\Http\Controllers\CardWebhookController;
use App\Http\Controllers\CardToUsdtWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ZelleWebhookController;
use App\Http\Controllers\AirwallexWebhookController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Zelle webhook to mark order as paid
Route::post('/webhooks/zelle', ZelleWebhookController::class);

Route::post('/webhooks/card', CardWebhookController::class);
Route::post('/webhooks/airwallex', AirwallexWebhookController::class);
Route::post('/webhooks/cardtousdt', CardToUsdtWebhookController::class)->name('webhooks.cardtousdt');

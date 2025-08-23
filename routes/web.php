<?php

use App\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');
});

Route::domain(env('DOMAIN_PAYMENT', 'getsecurepay.net'))->group(function () {
    Route::get('/pay/{token}', [PaymentController::class, 'show'])
        ->name('payment.page');

    Route::get('/pay/{token}/thank-you', [PaymentController::class, 'thanks'])
        ->name('payment.thanks');

    Route::post('/pay/{token}/process', [PaymentController::class, 'process'])
        ->name('payment.process');
});

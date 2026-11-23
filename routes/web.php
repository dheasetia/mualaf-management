<?php

declare(strict_types=1);

use App\Http\Controllers\SwitchLocaleController;
use App\Http\Controllers\VerifyCertificateController;
use App\Support\Localization\Locales;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// 09 §9.6: public certificate verification from the QR code, 60 requests a minute per IP.
Route::get('/verify/{uuid}', VerifyCertificateController::class)
    ->whereUuid('uuid')
    ->middleware('throttle:certificate-verification')
    ->name('certificates.verify');

// Interface language (Arabic / English); saved on the account when signed in.
Route::post('/locale/{locale}', SwitchLocaleController::class)
    ->whereIn('locale', Locales::SUPPORTED)
    ->name('locale.switch');

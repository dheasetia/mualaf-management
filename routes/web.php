<?php

declare(strict_types=1);

use App\Http\Controllers\VerifyCertificateController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// 09 §9.6: public certificate verification from the QR code, 60 requests a minute per IP.
Route::get('/verify/{uuid}', VerifyCertificateController::class)
    ->whereUuid('uuid')
    ->middleware('throttle:certificate-verification')
    ->name('certificates.verify');

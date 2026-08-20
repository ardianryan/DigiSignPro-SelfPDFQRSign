<?php

use App\Http\Controllers\Api\ApiDocsController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\SignatureController;
use App\Http\Controllers\Api\V1\SignController;
use App\Http\Controllers\Api\V1\VerifyController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| DigiSign REST API v1
|--------------------------------------------------------------------------
| Auth: Authorization: Bearer <api_key>  OR  X-API-Key: <api_key>
*/

Route::prefix('v1')->group(function () {
    // Public
    Route::get('/health', [HealthController::class, 'show'])->name('api.v1.health');
    Route::get('/verify/{code}', [VerifyController::class, 'show'])->name('api.v1.verify');
    Route::get('/docs/quickapi.md', [ApiDocsController::class, 'quickapiPublic'])
        ->middleware('throttle:30,1')
        ->name('api.v1.docs.quickapi');

    // Authenticated (per-user API key)
    Route::middleware(['api.key', 'throttle:api'])->group(function () {
        Route::get('/me', [MeController::class, 'show'])->name('api.v1.me');

        Route::get('/signatures', [SignatureController::class, 'index'])->name('api.v1.signatures.index');
        Route::get('/signatures/{id}', [SignatureController::class, 'show'])->whereNumber('id')->name('api.v1.signatures.show');
        Route::delete('/signatures/{id}', [SignatureController::class, 'destroy'])->whereNumber('id')->name('api.v1.signatures.destroy');

        Route::post('/sign/single', [SignController::class, 'single'])->name('api.v1.sign.single');
        Route::post('/sign/qr-manual', [SignController::class, 'qrManual'])->name('api.v1.sign.qr');
    });
});

<?php

use App\Http\Controllers\MediaController;
use App\Http\Controllers\Tv\AdultZoneController;
use App\Http\Controllers\Tv\CatalogController;
use App\Http\Controllers\Tv\PairingController;
use Illuminate\Support\Facades\Route;

// API del televisor. Sin cookies ni sesión: el televisor se identifica con su token de dispositivo.
Route::prefix('tv')->group(function () {
    Route::post('/pairings', [PairingController::class, 'start'])->middleware('throttle:tv-pairing');
    Route::get('/pairings/{pairing}', [PairingController::class, 'poll'])->middleware('throttle:tv-poll');
    Route::get('/pairings/{pairing}/qr.svg', [PairingController::class, 'qr'])->middleware('throttle:tv-poll')->name('tv.pairing.qr');

    Route::middleware(['throttle:tv-api', 'device'])->group(function () {
        Route::get('/session', [CatalogController::class, 'session']);
        Route::get('/profiles/{profile}/catalog', [CatalogController::class, 'catalog']);
        Route::get('/contents/{content}/play', [CatalogController::class, 'play']);
        Route::post('/contents/{content}/problem', [CatalogController::class, 'problem']);
        Route::post('/plays', [CatalogController::class, 'watched']);

        Route::post('/adult/unlock', [AdultZoneController::class, 'unlock'])->middleware('throttle:tv-pin');
        Route::post('/adult/lock', [AdultZoneController::class, 'lock']);
        Route::get('/adult/status', [AdultZoneController::class, 'status']);
        Route::get('/adult/catalog', [AdultZoneController::class, 'catalog']);
        Route::delete('/adult/device', [AdultZoneController::class, 'unlink']);
    });
});

// Archivos privados con URL firmada y temporal, emitida solo a televisores autorizados.
Route::get('/media/{content}/{variant}', MediaController::class)
    ->whereIn('variant', ['file', 'cover'])
    ->whereNumber('content')
    ->middleware('signed')
    ->name('media.show');

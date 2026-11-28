<?php

use App\Http\Controllers\Api\V1;
use App\Http\Middleware\EnforceMaintenanceMode;
use App\Http\Middleware\EnsureApiAccess;
use App\Http\Middleware\EnsureApiEnabled;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Support\Facades\Route;

// API publique v1 (prefixe /api/v1). Authentification par jeton Sanctum, droits par "ability".
Route::prefix('v1')->name('api.v1.')->middleware([EnsureApiEnabled::class, 'throttle:api-ip'])->group(function () {
    Route::get('meta', [V1\MetaController::class, 'show'])->name('meta');

    Route::middleware(['auth:sanctum', EnsureUserIsActive::class, EnsureApiAccess::class, EnforceMaintenanceMode::class, 'throttle:api'])->group(function () {
        Route::get('me', [V1\MetaController::class, 'me'])->name('me');

        Route::middleware('abilities:resources:read')->group(function () {
            Route::get('resources', [V1\ResourceController::class, 'index'])->name('resources.index');
            Route::get('resources/{resource}', [V1\ResourceController::class, 'show'])->whereNumber('resource')->name('resources.show');
            Route::get('resources/{resource}/slots', [V1\ResourceController::class, 'slots'])->whereNumber('resource')->name('resources.slots');
            Route::get('availability', V1\AvailabilityController::class)->name('availability');
            Route::get('rules', V1\RulesController::class)->name('rules');
        });

        Route::middleware('abilities:reservations:read')->group(function () {
            Route::get('reservations', [V1\ReservationController::class, 'index'])->name('reservations.index');
            Route::get('reservations/{reservation}', [V1\ReservationController::class, 'show'])->whereNumber('reservation')->name('reservations.show');
        });

        Route::middleware(['abilities:reservations:write', 'throttle:api-write'])->group(function () {
            Route::post('reservations', [V1\ReservationController::class, 'store'])->name('reservations.store');
            Route::delete('reservations/{reservation}', [V1\ReservationController::class, 'destroy'])->whereNumber('reservation')->name('reservations.destroy');
        });
    });
});

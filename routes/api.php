<?php

use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\SpoolController;
use Illuminate\Support\Facades\Route;

// Prefix: /api/v1 (see bootstrap/app.php).
// Order matters: IP check and cheap IP throttles run before token authentication.
Route::middleware(['ip:api', 'auth-failures', 'throttle-ip:api-ip', 'auth:sanctum'])->group(function () {
    Route::get('/me', MeController::class)->middleware('throttle:api-read');

    Route::middleware(['ability:spools:read', 'throttle:api-read'])->group(function () {
        Route::get('/catalog', [CatalogController::class, 'index']);
        Route::get('/catalog/{product}', [CatalogController::class, 'show']);
        Route::get('/spools', [SpoolController::class, 'index']);
        Route::get('/spools/{spool}', [SpoolController::class, 'show']);
        Route::get('/spools/{spool}/events', [SpoolController::class, 'events']);
    });

    Route::middleware(['abilities:spools:write', 'throttle:api-write'])->group(function () {
        Route::post('/spools', [SpoolController::class, 'store']);
        Route::patch('/spools/{spool}', [SpoolController::class, 'update']);
        Route::delete('/spools/{spool}', [SpoolController::class, 'destroy']);
        Route::post('/spools/{spool}/open', [SpoolController::class, 'open']);
        Route::post('/spools/{spool}/used-up', [SpoolController::class, 'usedUp']);
    });
});

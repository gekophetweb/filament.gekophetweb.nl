<?php

use App\Http\Controllers\Api\AccessRequestController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\PairController;
use App\Http\Controllers\Api\PrintRequestController;
use App\Http\Controllers\Api\SpoolController;
use App\Http\Controllers\Api\SuggestionController;
use Illuminate\Support\Facades\Route;

// Prefix: /api/v1 (see bootstrap/app.php).
// Order matters: IP check and cheap IP throttles run before token authentication.
Route::post('/pair', PairController::class)
    ->middleware(['ip:api', 'auth-failures', 'throttle-ip:api-ip', 'throttle:token-exchange'])->name('api.pair');

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

    // What comes in through the share link: print requests, suggestions and access requests.
    Route::middleware(['ability:requests:read', 'throttle:api-read'])->group(function () {
        Route::get('/print-requests', [PrintRequestController::class, 'index']);
        Route::get('/print-requests/{printRequest}', [PrintRequestController::class, 'show']);
        Route::get('/print-requests/{printRequest}/stl', [PrintRequestController::class, 'stl']);
        Route::get('/suggestions', [SuggestionController::class, 'index']);
        Route::get('/suggestions/{uuid}', [SuggestionController::class, 'show']);
        Route::get('/access-requests', [AccessRequestController::class, 'index']);
    });

    Route::middleware(['abilities:requests:write', 'throttle:api-write'])->group(function () {
        Route::patch('/print-requests/{printRequest}', [PrintRequestController::class, 'update']);
        Route::delete('/print-requests/{printRequest}', [PrintRequestController::class, 'destroy']);
        Route::patch('/suggestions/{uuid}', [SuggestionController::class, 'update']);
        Route::post('/suggestions/{uuid}/messages', [SuggestionController::class, 'reply']);
        Route::delete('/suggestions/{uuid}', [SuggestionController::class, 'destroy']);
        Route::post('/access-requests/{id}/approve', [AccessRequestController::class, 'approve'])->whereNumber('id');
        Route::post('/access-requests/{id}/decline', [AccessRequestController::class, 'decline'])->whereNumber('id');
    });
});

<?php

use App\Http\Controllers\ApiDocsController;
use Illuminate\Support\Facades\Route;

// Public API documentation. Loaded outside the "web" group (see bootstrap/app.php),
// so it skips the IP allow-list, sessions and the setup gate. Only a per-IP throttle applies.
Route::middleware('throttle:docs')->group(function () {
    Route::get('/docs/api', [ApiDocsController::class, 'page'])->name('docs.api');
    Route::get('/docs/api/openapi.json', [ApiDocsController::class, 'openApi'])->name('docs.api.openapi');
});

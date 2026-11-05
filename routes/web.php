<?php

use App\Http\Controllers\Auth\SsoController;
use App\Livewire\Catalog;
use App\Livewire\Devices;
use App\Livewire\SpoolDetail;
use App\Livewire\Spools;
use Illuminate\Support\Facades\Route;

// Only the login page and the SSO round trip are reachable without a session.
Route::middleware('guest')->group(function () {
    Route::get('/login', [SsoController::class, 'login'])->middleware('throttle:login')->name('login');
    Route::get('/auth/redirect', [SsoController::class, 'redirect'])->middleware('throttle:login')->name('sso.redirect');
    Route::get('/auth/callback', [SsoController::class, 'callback'])->middleware('throttle:sso-callback')->name('sso.callback');
});

Route::middleware(['auth', 'throttle:web'])->group(function () {
    Route::get('/', Catalog::class)->name('catalog');
    Route::get('/spools', Spools::class)->name('spools');
    Route::get('/spools/{spool}', SpoolDetail::class)->name('spools.show');
    Route::get('/devices', Devices::class)->name('devices');
    Route::post('/logout', [SsoController::class, 'logout'])->name('logout');
});

<?php

use App\Http\Controllers\Auth\SetupSignInTestController;
use App\Http\Controllers\Auth\SsoController;
use App\Livewire\Catalog;
use App\Livewire\Devices;
use App\Livewire\FilamentDetail;
use App\Livewire\Setup;
use App\Livewire\SpoolDetail;
use App\Livewire\Spools;
use App\Livewire\Usage;
use Illuminate\Support\Facades\Route;

// First-run setup. EnsureSetupComplete keeps these local-only and returns 404 once setup is done.
Route::get('/setup', Setup::class)->middleware('throttle:login')->name('setup');
Route::get('/setup/test', [SetupSignInTestController::class, 'redirect'])->middleware('throttle:login')->name('setup.test');
Route::get('/setup/callback', [SetupSignInTestController::class, 'callback'])->middleware('throttle:sso-callback')->name('setup.callback');

// Only the login page and the SSO round trip are reachable without a session.
Route::middleware('guest')->group(function () {
    Route::get('/login', [SsoController::class, 'login'])->middleware('throttle:login')->name('login');
    Route::get('/auth/redirect', [SsoController::class, 'redirect'])->middleware('throttle:login')->name('sso.redirect');
    Route::get('/auth/callback', [SsoController::class, 'callback'])->middleware('throttle:sso-callback')->name('sso.callback');
});

Route::middleware(['auth', 'throttle:web'])->group(function () {
    Route::get('/', Catalog::class)->name('catalog');
    Route::get('/filaments/{product}', FilamentDetail::class)->name('filaments.show');
    Route::get('/usage', Usage::class)->name('usage');
    Route::get('/spools', Spools::class)->name('spools');
    Route::get('/spools/{spool}', SpoolDetail::class)->name('spools.show');
    Route::get('/devices', Devices::class)->name('devices');
    Route::post('/logout', [SsoController::class, 'logout'])->name('logout');
});

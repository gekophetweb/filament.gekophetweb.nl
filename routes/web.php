<?php

use App\Http\Controllers\Auth\PrintRequesterEmailCodeController;
use App\Http\Controllers\Auth\PrintRequesterSignInController;
use App\Http\Controllers\Auth\SetupSignInTestController;
use App\Http\Controllers\Auth\SsoController;
use App\Http\Controllers\PrintRequestController;
use App\Http\Controllers\SpoolExportController;
use App\Livewire\Catalog;
use App\Livewire\Devices;
use App\Livewire\FilamentDetail;
use App\Livewire\PrintRequests;
use App\Livewire\Setup;
use App\Livewire\ShareAndExport;
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

// The share link is the print request page (still behind the web IP allow-list). It takes requests once the
// owner lists who may send them; requesters sign in through SSO first.
Route::get('/share/{token}', [PrintRequestController::class, 'create'])
    ->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:share')->name('share');
Route::get('/share/{token}/print-request', [PrintRequestController::class, 'redirectToShare'])
    ->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:share')->name('share.print-request');
Route::get('/share/{token}/sign-in', [PrintRequesterSignInController::class, 'redirect'])
    ->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:login')->name('share.sign-in');
Route::post('/share/{token}/email-code', [PrintRequesterEmailCodeController::class, 'send'])
    ->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:print-request-code')->name('share.email-code');
Route::post('/share/{token}/email-code/verify', [PrintRequesterEmailCodeController::class, 'verify'])
    ->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:login')->name('share.email-code.verify');
Route::post('/share/{token}/email-code/cancel', [PrintRequesterEmailCodeController::class, 'cancel'])
    ->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:share')->name('share.email-code.cancel');
Route::post('/share/{token}/sign-out', [PrintRequesterSignInController::class, 'signOut'])
    ->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:share')->name('share.sign-out');
Route::post('/share/{token}/print-requests', [PrintRequestController::class, 'store'])
    ->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:print-request')->name('share.print-requests.store');

Route::middleware(['auth', 'throttle:web'])->group(function () {
    Route::get('/', Catalog::class)->name('catalog');
    Route::get('/filaments/{product}', FilamentDetail::class)->name('filaments.show');
    Route::get('/usage', Usage::class)->name('usage');
    Route::get('/spools', Spools::class)->name('spools');
    Route::get('/spools/export', SpoolExportController::class)->name('spools.export');
    Route::get('/spools/{spool}', SpoolDetail::class)->name('spools.show');
    Route::get('/share-export', ShareAndExport::class)->name('share-export');
    Route::get('/print-requests', PrintRequests::class)->name('print-requests');
    Route::get('/print-requests/{printRequest}/stl', [PrintRequestController::class, 'download'])->name('print-requests.stl');
    Route::get('/devices', Devices::class)->name('devices');
    Route::post('/logout', [SsoController::class, 'logout'])->name('logout');
});

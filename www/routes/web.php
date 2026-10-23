<?php

use App\Http\Controllers\Auth\SocialLoginController;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

Route::redirect('/', '/app');

Route::middleware('guest')->group(function () {
    Route::view('/login', 'auth.login')->name('login');
    Route::get('/auth/{provider}/redirect', [SocialLoginController::class, 'redirect'])
        ->whereIn('provider', SocialLoginController::PROVIDERS)
        ->name('social.redirect');
    Route::get('/auth/{provider}/callback', [SocialLoginController::class, 'callback'])
        ->whereIn('provider', SocialLoginController::PROVIDERS)
        ->name('social.callback');
});

// Apple'ın cross-site POST callback'i; oturum ve CSRF middleware'i bilinçli olarak yok
// (bkz. SocialLoginController::appleFormPost).
Route::post('/auth/apple/callback', [SocialLoginController::class, 'appleFormPost'])
    ->withoutMiddleware([StartSession::class, ShareErrorsFromSession::class, PreventRequestForgery::class]);

Route::post('/logout', [SocialLoginController::class, 'logout'])->middleware('auth')->name('logout');

<?php

use App\Http\Controllers\Auth\SocialLoginController;
use App\Http\Controllers\StoreConnectController;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

Route::view('/', 'landing')->name('home');

// Hukuki metinler: Huawei/Google başvurularında ve giriş sayfasında linklenir.
Route::view('/privacy', 'legal.privacy-tr', ['lang' => 'tr'])->name('legal.privacy');
Route::view('/terms', 'legal.terms-tr', ['lang' => 'tr'])->name('legal.terms');
Route::view('/en/privacy', 'legal.privacy-en', ['lang' => 'en'])->name('legal.privacy.en');
Route::view('/en/terms', 'legal.terms-en', ['lang' => 'en'])->name('legal.terms.en');

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

// Geliştirici hesabını OAuth ile bağlama. callback, {workspace} parametreli route'tan önce tanımlanmalı.
Route::middleware('auth')->group(function () {
    Route::get('/connect/{store}/callback', [StoreConnectController::class, 'callback'])
        ->whereIn('store', ['google_play'])
        ->name('store.connect.callback');
    Route::get('/connect/{store}/{workspace:slug}', [StoreConnectController::class, 'redirect'])
        ->whereIn('store', ['google_play'])
        ->name('store.connect');
});

Route::post('/logout', [SocialLoginController::class, 'logout'])->middleware('auth')->name('logout');

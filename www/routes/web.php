<?php

use App\Http\Controllers\Auth\SocialLoginController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/app');

Route::middleware('guest')->group(function () {
    Route::view('/login', 'auth.login')->name('login');
    Route::get('/auth/{provider}/redirect', [SocialLoginController::class, 'redirect'])
        ->whereIn('provider', SocialLoginController::PROVIDERS)
        ->name('social.redirect');
    // Apple, e-posta/isim istendiğinde callback'i form_post (POST) ile yapar.
    Route::match(['get', 'post'], '/auth/{provider}/callback', [SocialLoginController::class, 'callback'])
        ->whereIn('provider', SocialLoginController::PROVIDERS)
        ->name('social.callback');
});

Route::post('/logout', [SocialLoginController::class, 'logout'])->middleware('auth')->name('logout');

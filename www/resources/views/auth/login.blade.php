@extends('layouts.auth')

@section('title', 'Giriş')

@section('content')
    <h1>{{ config('app.name') }}</h1>
    <p class="muted">Devam etmek için giriş yap</p>

    <div class="stack">
        @forelse (\App\Http\Controllers\Auth\SocialLoginController::enabledProviders() as $provider)
            <a class="btn" href="{{ route('social.redirect', $provider) }}">
                {{ \App\Http\Controllers\Auth\SocialLoginController::LABELS[$provider] }} ile giriş yap
            </a>
        @empty
            <p class="muted">Henüz bir giriş sağlayıcısı yapılandırılmadı.</p>
        @endforelse
    </div>

@endsection

@section('footer')
    Giriş yaparak <a href="{{ route('legal.terms') }}">Kullanım Koşulları</a>'nı ve
    <a href="{{ route('legal.privacy') }}">Gizlilik Politikası</a>'nı kabul etmiş olursunuz.
    <br><a href="{{ route('home') }}">Kalehost Metrics hakkında</a>
@endsection

@extends('layouts.auth')

@section('title', 'Giriş')

@section('content')
    <h1>{{ config('app.name') }}</h1>
    <p class="muted">Devam etmek için giriş yap</p>

    <div class="stack">
        <a class="btn" href="{{ route('social.redirect', 'google') }}">Google ile giriş yap</a>
        <a class="btn" href="{{ route('social.redirect', 'apple') }}">Apple ile giriş yap</a>
        <a class="btn" href="{{ route('social.redirect', 'huawei') }}">Huawei ID ile giriş yap</a>
    </div>
@endsection

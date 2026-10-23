@extends('layouts.auth')

@section('title', 'Yetkilendir')

@section('content')
    <h1>{{ $client->name }}</h1>
    <p class="muted">bu hesaptaki mağaza istatistiklerini okumak istiyor.</p>

    <div class="box">
        <div class="muted" style="font-size: 13px">Giriş yapılan hesap</div>
        <div>{{ $user->email }}</div>
    </div>

    <div class="row">
        <form method="POST" action="{{ route('passport.authorizations.deny') }}">
            @csrf
            @method('DELETE')
            <input type="hidden" name="state" value="">
            <input type="hidden" name="client_id" value="{{ $client->id }}">
            <input type="hidden" name="auth_token" value="{{ $authToken }}">
            <button type="submit" class="btn">Reddet</button>
        </form>
        <form method="POST" action="{{ route('passport.authorizations.approve') }}">
            @csrf
            <input type="hidden" name="state" value="">
            <input type="hidden" name="client_id" value="{{ $client->id }}">
            <input type="hidden" name="auth_token" value="{{ $authToken }}">
            <button type="submit" class="btn btn-primary">İzin ver</button>
        </form>
    </div>
@endsection

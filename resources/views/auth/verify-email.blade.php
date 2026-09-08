@extends('layouts.guest')
@section('title', 'Verify email')
@section('content')
<p class="eyebrow">One last detail</p><h1>Verify your email</h1><p class="auth-intro">We sent a verification link to your inbox. Open it to activate your studio.</p>
<form method="POST" action="{{ route('verification.send') }}" class="form-stack">@csrf<button class="button button-primary button-wide" type="submit">Send another link</button></form>
<form method="POST" action="{{ route('logout') }}" class="auth-switch">@csrf<button class="text-button" type="submit">Sign out</button></form>
@endsection

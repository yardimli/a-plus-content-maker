@extends('layouts.guest')
@section('title', 'Reset password')
@section('content')
<p class="eyebrow">Account recovery</p><h1>Find your way back</h1><p class="auth-intro">Enter your email and we’ll send you a secure password reset link.</p>
@if(session('status'))<div class="flash flash-success">{{ session('status') }}</div>@endif
<form method="POST" action="{{ route('password.email') }}" class="form-stack">@csrf
    <label>Email address<input type="email" name="email" value="{{ old('email') }}" required autofocus>@error('email')<span class="field-error">{{ $message }}</span>@enderror</label>
    <button class="button button-primary button-wide" type="submit">Email reset link</button>
</form>
<p class="auth-switch"><a href="{{ route('login') }}">← Return to sign in</a></p>
@endsection

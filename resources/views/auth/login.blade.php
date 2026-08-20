@extends('layouts.guest')
@section('title', 'Sign in')
@section('content')
<p class="eyebrow">Welcome back</p><h1>Return to your studio</h1><p class="auth-intro">Your books, modules, and export-ready assets are waiting.</p>
@if(session('status'))<div class="flash flash-success">{{ session('status') }}</div>@endif
<form method="POST" action="{{ route('login') }}" class="form-stack">@csrf
    <label>Email address<input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">@error('email')<span class="field-error">{{ $message }}</span>@enderror</label>
    <label>Password<input type="password" name="password" required autocomplete="current-password">@error('password')<span class="field-error">{{ $message }}</span>@enderror</label>
    <div class="form-between"><label class="check-row"><input type="checkbox" name="remember"> Keep me signed in</label>@if(Route::has('password.request'))<a href="{{ route('password.request') }}">Forgot password?</a>@endif</div>
    <button class="button button-primary button-wide" type="submit">Sign in to the studio</button>
</form>
<p class="auth-switch">New here? <a href="{{ route('register') }}">Create an author account</a></p>
@endsection

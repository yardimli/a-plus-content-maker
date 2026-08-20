@extends('layouts.guest')
@section('title', 'Choose a new password')
@section('content')
<p class="eyebrow">Account recovery</p><h1>Choose a new password</h1><p class="auth-intro">Use a strong password you don’t use on another site.</p>
<form method="POST" action="{{ route('password.store') }}" class="form-stack">@csrf
    <input type="hidden" name="token" value="{{ $request->route('token') }}">
    <label>Email address<input type="email" name="email" value="{{ old('email', $request->email) }}" required autocomplete="username">@error('email')<span class="field-error">{{ $message }}</span>@enderror</label>
    <label>New password<input type="password" name="password" required autocomplete="new-password">@error('password')<span class="field-error">{{ $message }}</span>@enderror</label>
    <label>Confirm password<input type="password" name="password_confirmation" required autocomplete="new-password"></label>
    <button class="button button-primary button-wide" type="submit">Reset password</button>
</form>
@endsection

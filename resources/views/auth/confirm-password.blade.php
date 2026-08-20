@extends('layouts.guest')
@section('title', 'Confirm password')
@section('content')
<p class="eyebrow">Security check</p><h1>Confirm it’s you</h1><p class="auth-intro">Enter your password before continuing to this protected action.</p>
<form method="POST" action="{{ route('password.confirm') }}" class="form-stack">@csrf
    <label>Password<input type="password" name="password" required autocomplete="current-password">@error('password')<span class="field-error">{{ $message }}</span>@enderror</label>
    <button class="button button-primary button-wide" type="submit">Confirm password</button>
</form>
@endsection

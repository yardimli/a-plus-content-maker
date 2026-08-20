@extends('layouts.guest')
@section('title', 'Create account')
@section('content')
<p class="eyebrow">Begin a new chapter</p><h1>Create your author studio</h1><p class="auth-intro">Start with a template or build an original A+ page from a blank canvas.</p>
<form method="POST" action="{{ route('register') }}" class="form-stack">@csrf
    <label>Your name<input type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name">@error('name')<span class="field-error">{{ $message }}</span>@enderror</label>
    <label>Email address<input type="email" name="email" value="{{ old('email') }}" required autocomplete="username">@error('email')<span class="field-error">{{ $message }}</span>@enderror</label>
    <label>Password<input type="password" name="password" required autocomplete="new-password">@error('password')<span class="field-error">{{ $message }}</span>@enderror</label>
    <label>Confirm password<input type="password" name="password_confirmation" required autocomplete="new-password"></label>
    <button class="button button-primary button-wide" type="submit">Create my account</button>
</form>
<p class="auth-switch">Already have an account? <a href="{{ route('login') }}">Sign in</a></p>
@endsection

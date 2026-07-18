@extends('layouts.app')

@section('title', __('ui.auth.reset_title'))
@section('content')
<main class="auth-page">
    <section class="auth-panel">
        <p class="eyebrow">{{ __('ui.auth.reset_heading') }}</p>
        <h1>{{ __('ui.auth.reset_heading') }}</h1>
        @if ($errors->any())<div class="alert">{{ $errors->first() }}</div>@endif
        <form class="form-stack" method="POST" action="{{ route('password.update') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <div class="form-field"><label for="email">{{ __('ui.auth.email') }}</label><input id="email" name="email" type="email" value="{{ old('email', $email) }}" required autocomplete="email"></div>
            <div class="form-field"><label for="password">{{ __('ui.auth.new_password') }}</label><input id="password" name="password" type="password" required autocomplete="new-password"></div>
            <div class="form-field"><label for="password_confirmation">{{ __('ui.auth.confirm_password') }}</label><input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"></div>
            <button class="button button-primary" type="submit">{{ __('ui.auth.reset_password') }}</button>
        </form>
    </section>
</main>
@endsection

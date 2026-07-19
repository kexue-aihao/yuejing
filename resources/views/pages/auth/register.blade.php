@extends('layouts.app')

@section('title', __('ui.auth.register_title'))

@section('content')
@php($selectedRole = old('role', request()->query('role', 'user')))
<main class="auth-page"><section class="auth-panel">
    <p class="eyebrow">{{ __('ui.auth.register_eyebrow') }}</p><h1>{{ __('ui.auth.register_heading') }}</h1><p>{{ __('ui.auth.register_intro') }}</p>
    @if ($errors->any())<div class="alert">{{ $errors->first() }}</div>@endif
    <form class="form-stack" method="POST" action="{{ route('register') }}">@csrf
        <div class="form-field"><label for="name">{{ __('ui.auth.name') }}</label><input id="name" name="name" type="text" value="{{ old('name') }}" required autocomplete="name"></div>
        <div class="form-field"><label for="email">{{ __('ui.auth.email') }}</label><input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email"></div>
        <div class="form-field"><label for="role">{{ __('ui.auth.role') }}</label><select id="role" name="role" required><option value="user" @selected($selectedRole === 'user')>{{ __('ui.auth.role_user') }}</option><option value="author" @selected($selectedRole === 'author')>{{ __('ui.auth.role_author') }}</option></select></div>
        <div class="form-field"><label for="password">{{ __('ui.auth.set_password') }}</label><input id="password" name="password" type="password" required autocomplete="new-password"></div>
        <div class="form-field"><label for="password_confirmation">{{ __('ui.auth.confirm_password') }}</label><input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"></div>
        <button class="button button-primary form-submit" type="submit">{{ __('ui.auth.create_account') }}</button>
    </form>
    <p class="auth-foot">{{ __('ui.auth.has_account') }} <a href="{{ route('login') }}">{{ __('ui.auth.login_direct') }}</a></p>
</section></main>
@endsection

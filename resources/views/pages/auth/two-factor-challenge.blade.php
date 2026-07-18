@extends('layouts.app')
@section('title', __('ui.two_factor.challenge_title'))
@section('content')
<main class="auth-page">
    <section class="auth-panel">
        <p class="eyebrow">{{ __('ui.two_factor.challenge_eyebrow') }}</p>
        <h1>{{ __('ui.two_factor.challenge_heading') }}</h1>
        <p>{{ __('ui.two_factor.challenge_intro') }}</p>
        @if ($errors->any())<div class="alert">{{ $errors->first() }}</div>@endif
        <form class="form-stack" method="POST" action="{{ route('two-factor.challenge') }}">
            @csrf
            <div class="form-field"><label for="code">{{ __('ui.two_factor.code') }}</label><input id="code" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code"></div>
            <div class="form-field"><label for="recovery_code">{{ __('ui.two_factor.recovery_code') }}</label><input id="recovery_code" name="recovery_code" autocapitalize="characters" autocomplete="off"></div>
            <button class="button button-primary form-submit" type="submit">{{ __('ui.two_factor.complete_login') }} <span aria-hidden="true">→</span></button>
        </form>
        <p class="auth-foot"><a href="{{ route('login') }}">{{ __('ui.two_factor.back_login') }}</a></p>
    </section>
</main>
@endsection

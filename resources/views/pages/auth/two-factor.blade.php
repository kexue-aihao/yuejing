@extends('layouts.app')
@section('title', __('ui.two_factor.title'))
@section('content')
<main class="auth-page">
    <section class="auth-panel">
        <p class="eyebrow">{{ __('ui.two_factor.eyebrow') }}</p>
        <h1>{{ __('ui.two_factor.heading') }}</h1>
        <p>{{ __('ui.two_factor.intro') }}</p>
        @if ($errors->any())<div class="alert">{{ $errors->first() }}</div>@endif
        @if (session('status'))<div class="alert">{{ session('status') }}</div>@endif
        @if ($setting?->enabled)
            <p>{{ __('ui.two_factor.enabled_notice') }}</p>
            <form class="form-stack" method="POST" action="{{ route('two-factor.disable') }}">
                @csrf @method('DELETE')
                <div class="form-field"><label for="current_password">{{ __('ui.two_factor.current_password') }}</label><input id="current_password" name="current_password" type="password" autocomplete="current-password"></div>
                <div class="form-field"><label for="disable_code">{{ __('ui.two_factor.totp_code') }}</label><input id="disable_code" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code"></div>
                <button class="button button-dark" type="submit">{{ __('ui.two_factor.disable') }}</button>
            </form>
        @else
            @php($setup = session('two_factor_setup'))
            @if ($setup)
                <div class="alert"><strong>{{ __('ui.two_factor.save_recovery') }}</strong><br>{{ implode(' · ', $setup['recovery_codes']) }}</div>
                @if (!empty($setup['otpauth_uri']))
                    <div class="two-factor-setup-grid">
                        <div class="two-factor-qr-panel">
                            <p class="panel-kicker">{{ __('ui.two_factor.scan_eyebrow') }}</p>
                            <h2>{{ __('ui.two_factor.scan_heading') }}</h2>
                            <div data-vue-two-factor-qr data-value="{{ $setup['otpauth_uri'] }}" data-label="{{ __('ui.two_factor.qr_label') }}"></div>
                            <p class="form-help">{{ __('ui.two_factor.scan_notice') }}</p>
                        </div>
                        <div class="two-factor-manual-panel">
                            <p>{{ __('ui.two_factor.setup_notice') }}</p>
                            <p><strong>{{ __('ui.two_factor.secret') }}:</strong>{{ $setup['secret'] }}</p>
                        </div>
                    </div>
                @else
                    <p>{{ __('ui.two_factor.setup_notice') }}</p>
                    <p><strong>{{ __('ui.two_factor.secret') }}:</strong>{{ $setup['secret'] }}</p>
                @endif
                <form class="form-stack" method="POST" action="{{ route('two-factor.enable') }}">
                    @csrf
                    <div class="form-field"><label for="enable_code">{{ __('ui.two_factor.totp_code') }}</label><input id="enable_code" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autocomplete="one-time-code"></div>
                    <button class="button button-primary" type="submit">{{ __('ui.two_factor.confirm_enable') }}</button>
                </form>
            @else
                <form class="form-stack" method="POST" action="{{ route('two-factor.enable') }}">
                    @csrf
                    <button class="button button-primary" type="submit">{{ __('ui.two_factor.generate_secret') }}</button>
                </form>
            @endif
        @endif
    </section>
</main>
@endsection

@extends('layouts.app')

@section('title', __('ui.account_pages.settings_title'))
@section('content')
<main class="site-shell dashboard-page">
    <div class="dashboard-head"><div><p class="eyebrow">{{ __('ui.account_pages.settings_eyebrow') }}</p><h1>{{ __('ui.account.settings') }}</h1><p>{{ __('ui.account_pages.settings_intro') }}</p></div></div>
    <div class="dashboard-grid">
        <x-account-nav active="settings" />
        <div class="dashboard-content">
            @if (session('status'))<div class="alert">{{ session('status') }}</div>@endif
            @if ($errors->any())<div class="alert">{{ $errors->first() }}</div>@endif
            <form class="panel" method="POST" action="{{ route('account.settings.update') }}">
                @csrf @method('PUT')
                <div class="panel-heading"><h2>{{ __('ui.account_pages.basic_info') }}</h2><button class="button button-primary button-small" type="submit">{{ __('ui.account_pages.save_changes') }}</button></div>
                <div class="settings-grid"><div class="form-field"><label for="name">{{ __('ui.account_pages.name') }}</label><input id="name" name="name" value="{{ old('name', $user->name) }}" required autocomplete="name"></div><div class="form-field"><label for="email">{{ __('ui.account_pages.email') }}</label><input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required autocomplete="email"></div></div>
                <p class="settings-hint">{{ __('ui.account_pages.email_hint') }}</p>
            </form>
            <section class="panel security-panel"><div class="panel-heading"><h2>{{ __('ui.account_pages.login_security') }}</h2><span class="status {{ $twoFactorEnabled ? '' : 'pending' }}">{{ $twoFactorEnabled ? __('ui.account_pages.protected') : __('ui.account_pages.not_set') }}</span></div><div class="security-row"><div><strong>{{ __('ui.account_pages.two_factor') }}</strong><p>{{ $twoFactorEnabled ? __('ui.account_pages.two_factor_enabled') : __('ui.account_pages.two_factor_disabled') }}</p></div><a class="button {{ $twoFactorEnabled ? 'button-outline' : 'button-dark' }}" href="{{ route('two-factor.show') }}">{{ $twoFactorEnabled ? __('ui.account_pages.manage_two_factor') : __('ui.account_pages.set_now') }} <span>→</span></a></div></section>
        </div>
    </div>
</main>
@endsection

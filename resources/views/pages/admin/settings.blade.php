@extends('layouts.app')

@section('title', __('ui.admin.site_settings').' · '.__('ui.admin.title_suffix'))

@section('content')
<main class="site-shell dashboard-page">
    <div class="dashboard-head"><div><p class="eyebrow">{{ __('ui.admin.settings_eyebrow') }}</p><h1>{{ __('ui.admin.site_settings') }}</h1><p>{{ __('ui.admin.settings_intro') }}</p></div><span class="status">{{ __('ui.admin.admin_permission') }}</span></div>
    <div class="dashboard-grid">
        @include('pages.admin._nav', ['active' => 'settings'])
        <div class="dashboard-content">
            @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
            @if ($errors->any())<div class="alert">{{ $errors->first() }}</div>@endif
            <form class="panel form-stack" method="POST" action="{{ route('admin.settings.update') }}">
                @csrf @method('PUT')
                <div class="panel-heading"><h2>{{ __('ui.admin.basic_info') }}</h2><button class="button button-primary button-small" type="submit">{{ __('ui.admin.save_settings') }}</button></div>
                <div class="settings-grid">
                    <div class="form-field"><label for="site_name">{{ __('ui.admin.site_name') }}</label><input id="site_name" name="site_name" value="{{ old('site_name', $settingValues['site_name']) }}" required></div>
                    <div class="form-field"><label for="site_tagline">{{ __('ui.admin.site_tagline') }}</label><input id="site_tagline" name="site_tagline" value="{{ old('site_tagline', $settingValues['site_tagline']) }}" required></div>
                    <div class="form-field"><label for="contact_email">{{ __('ui.admin.contact_email') }}</label><input id="contact_email" name="contact_email" type="email" value="{{ old('contact_email', $settingValues['contact_email']) }}" required></div>
                    <div class="form-field"><label for="accent_color">{{ __('ui.admin.accent_color') }}</label><select id="accent_color" name="accent_color"><option value="coral" @selected(old('accent_color', $settingValues['accent_color']) === 'coral')>{{ __('ui.admin.coral') }}</option><option value="moss" @selected(old('accent_color', $settingValues['accent_color']) === 'moss')>{{ __('ui.admin.moss') }}</option><option value="ink" @selected(old('accent_color', $settingValues['accent_color']) === 'ink')>{{ __('ui.admin.ink') }}</option></select></div>
                </div>
                <div class="settings-divider"></div>
                <div class="form-field"><label class="check-row"><input type="hidden" name="email_verification_required" value="0"><input type="checkbox" name="email_verification_required" value="1" @checked(old('email_verification_required', $settingValues['email_verification_required'])) @disabled(! $environmentConfig['email_verification_enabled'])> {{ __('ui.admin.email_verification_required') }}</label><p class="field-hint">{{ $environmentConfig['email_verification_enabled'] ? __('ui.admin.email_verification_hint') : __('ui.admin.email_verification_env_disabled') }}</p></div>
                <div class="settings-grid">
                    <label class="check-row"><input type="hidden" name="show_rank" value="0"><input type="checkbox" name="show_rank" value="1" @checked(old('show_rank', $settingValues['show_rank']))> {{ __('ui.admin.show_rank') }}</label>
                    <label class="check-row"><input type="hidden" name="show_new" value="0"><input type="checkbox" name="show_new" value="1" @checked(old('show_new', $settingValues['show_new']))> {{ __('ui.admin.show_new') }}</label>
                    <label class="check-row"><input type="hidden" name="allow_comments" value="0"><input type="checkbox" name="allow_comments" value="1" @checked(old('allow_comments', $settingValues['allow_comments']))> {{ __('ui.admin.allow_comments') }}</label>
                </div>
            </form>
            <section class="panel environment-config-panel">
                <div class="panel-heading"><div><h2>{{ __('ui.admin.environment_config') }}</h2><p class="panel-description">{{ __('ui.admin.environment_config_intro') }}</p></div><span class="status">{{ __('ui.admin.environment_read_only') }}</span></div>
                <div class="environment-config-list">
                    @foreach ($environmentConfig['items'] as $item)
                        <div class="environment-config-row"><div><strong>{{ $item['key'] }}</strong><p>{{ $item['description'] }}</p></div><code>{{ $item['value'] }}</code></div>
                    @endforeach
                </div>
                <p class="settings-hint">{{ __('ui.admin.environment_config_hint') }}</p>
            </section>
            <section class="panel">
                <div class="panel-heading"><div><h2>{{ __('ui.admin.smtp_status') }}</h2><p class="panel-description">{{ __('ui.admin.smtp_intro') }}</p></div><span class="status">{{ __('ui.admin.testable') }}</span></div>
                <form class="inline-form" method="POST" action="{{ route('admin.settings.email-test') }}">@csrf<div class="form-field"><label for="smtp_email">{{ __('ui.admin.test_email') }}</label><input id="smtp_email" name="email" type="email" value="{{ old('email', $settingValues['contact_email']) }}" required></div><button class="button button-dark" type="submit">{{ __('ui.admin.send_test_email') }}</button></form>
                <p class="settings-hint">{{ __('ui.admin.send_test_hint') }} {{ config('mail.default') }} · {{ config('mail.mailers.'.config('mail.default').'.host', __('ui.components.no_content')) }}</p>
            </section>
        </div>
    </div>
</main>
@endsection

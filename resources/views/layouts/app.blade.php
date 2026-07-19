<!doctype html>
@php
    $device = request()->attributes->get('device', []);
    $localeManager = app(\App\Services\LocaleManager::class);
    $displayLocale = $localeManager->current();
    $localeDefinition = $localeManager->definition($displayLocale);
    $translationLocale = $localeManager->translationLocale($displayLocale);
    $vditorLocales = [
        'de' => 'de_DE',
        'en' => 'en_US',
        'es' => 'es_ES',
        'fr' => 'fr_FR',
        'ja' => 'ja_JP',
        'ko' => 'ko_KR',
        'pt' => 'pt_BR',
        'ru' => 'ru_RU',
        'sv' => 'sv_SE',
        'vi' => 'vi_VN',
        'zh_CN' => 'zh_CN',
        'zh_TW' => 'zh_TW',
    ];
    $editorLocale = $vditorLocales[$translationLocale] ?? 'en_US';
    $editorDirection = $localeDefinition['dir'] ?? 'ltr';
@endphp
<html lang="{{ $localeDefinition['html'] ?? str_replace('_', '-', $displayLocale) }}" dir="{{ $localeDefinition['dir'] ?? 'ltr' }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('ui.app.name').' · '.__('ui.app.tagline'))</title>
    <script>
        window.YuejingI18n = {
            frontend: @json(trans('ui.frontend')),
            reviews: @json(trans('reviews')),
            reader: @json(trans('ui.reader')),
            editorLocale: @json($editorLocale),
            editorDirection: @json($editorDirection),
        };
        (() => {
            try {
                const theme = localStorage.getItem('yuejing-theme');
                if (theme === 'light' || theme === 'dark' || theme === 'eye-care') document.documentElement.dataset.theme = theme;
            } catch (_) {
                // Use the system theme when storage is unavailable.
            }
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="site-body device-{{ $device['platform'] ?? 'desktop' }} device-type-{{ $device['device_type'] ?? 'desktop' }}" data-server-auth-state="{{ auth()->check() ? 'authenticated' : 'guest' }}" data-device-platform="{{ $device['platform'] ?? 'desktop' }}" data-device-type="{{ $device['device_type'] ?? 'desktop' }}" data-device-mobile="{{ ! empty($device['is_mobile']) ? '1' : '0' }}" data-device-webview="{{ ! empty($device['is_webview']) ? '1' : '0' }}">
    <a class="skip-link" href="#main-content">{{ __('ui.common.skip_to_content') }}</a>
    <header class="site-header">
        @php
            $currentUser = auth()->user();
            $canAccessAuthorStudio = $currentUser?->isRole(['author', 'editor', 'admin']) ?? false;
        @endphp
        <div class="site-shell nav-shell">
            <a class="brand" href="{{ Route::has('home') ? route('home') : url('/') }}" aria-label="{{ __('ui.app.name') }}"><span class="brand-mark" aria-hidden="true">Y</span><span>{{ __('ui.app.name') }}</span></a>
            <nav class="desktop-nav" aria-label="{{ __('ui.nav.main_navigation') }}">
                <a class="nav-link {{ request()->routeIs('home') ? 'is-active' : '' }}" href="{{ Route::has('home') ? route('home') : url('/') }}" @if(request()->routeIs('home')) aria-current="page" @endif>{{ __('ui.nav.home') }}</a>
                <a class="nav-link {{ request()->routeIs('novels.*') ? 'is-active' : '' }}" href="{{ Route::has('novels.index') ? route('novels.index') : '#' }}" @if(request()->routeIs('novels.*')) aria-current="page" @endif>{{ __('ui.nav.library') }}</a>
            </nav>
            <div class="nav-actions">
                <form class="nav-search" action="{{ Route::has('novels.index') ? route('novels.index') : '#' }}" method="get" role="search">
                    <label class="sr-only" for="global-search">{{ __('ui.nav.search_label') }}</label><button type="submit" aria-label="{{ __('ui.nav.search') }}">⌕</button><input id="global-search" name="q" value="{{ request('q', '') }}" placeholder="{{ __('ui.nav.search_placeholder') }}" autocomplete="off">
                </form>
                @auth
                    <a class="profile-link" href="{{ Route::has('dashboard') ? route('dashboard') : '#' }}" aria-label="{{ __('ui.nav.open_personal_center') }}" title="{{ __('ui.nav.personal_center') }}" data-auth-state="authenticated">
                        <svg class="profile-link-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="8" r="3.25"></circle><path d="M5.5 19.25c.7-3.25 3-5 6.5-5s5.8 1.75 6.5 5"></path></svg>
                        <span class="profile-link-label">{{ __('ui.nav.logged_in') }}</span>
                    </a>
                    @if (auth()->user()->isRole('admin') && Route::has('admin.dashboard'))
                        <a class="login-link" href="{{ route('admin.dashboard') }}">{{ __('ui.nav.admin') }}</a>
                    @endif
                    @if (Route::has('logout'))
                        <form method="POST" action="{{ route('logout') }}"><button class="login-link" type="submit">{{ __('ui.nav.logout') }}</button>@csrf</form>
                    @endif
                @else
                    <a class="login-link" href="{{ Route::has('login') ? route('login') : '#' }}">{{ __('ui.nav.login') }}</a>
                    <a class="button button-small" href="{{ Route::has('register') ? route('register') : '#' }}">{{ __('ui.nav.register') }}</a>
                @endauth
                <x-language-switcher />
                <x-theme-toggle />
                <button class="mobile-menu-toggle" type="button" data-menu-toggle aria-expanded="false" aria-controls="mobile-menu" aria-label="{{ __('ui.nav.open_menu') }}"><span></span><span></span><span></span><span class="sr-only">{{ __('ui.nav.open_menu') }}</span></button>
            </div>
        </div>
        <div id="mobile-menu" class="mobile-menu" data-mobile-menu hidden aria-hidden="true" inert>
            <button class="mobile-menu-close" type="button" data-menu-close aria-label="{{ __('ui.nav.close_menu') }}">✕</button>
            <nav class="site-shell mobile-nav" aria-label="{{ __('ui.nav.mobile_navigation') }}"><a href="{{ Route::has('home') ? route('home') : url('/') }}">{{ __('ui.nav.home') }}</a><a href="{{ Route::has('novels.index') ? route('novels.index') : '#' }}">{{ __('ui.nav.library') }}</a>@auth <a href="{{ Route::has('dashboard') ? route('dashboard') : '#' }}">{{ __('ui.nav.logged_in') }}</a>@if (auth()->user()->isRole('admin') && Route::has('admin.dashboard'))<a href="{{ route('admin.dashboard') }}">{{ __('ui.nav.admin') }}</a>@endif @if (Route::has('logout'))<form class="mobile-nav-logout" method="POST" action="{{ route('logout') }}"><button type="submit">{{ __('ui.nav.logout') }}</button>@csrf</form>@endif @else<a href="{{ Route::has('login') ? route('login') : '#' }}">{{ __('ui.nav.login') }} / {{ __('ui.nav.register') }}</a>@endauth</nav>
        </div>
    </header>
    @if (session('success'))
        <div class="site-shell" style="margin-top: 18px"><x-alert type="success" :message="session('success')" dismissible /></div>
    @endif
    @if (session('warning'))
        <div class="site-shell" style="margin-top: 18px"><x-alert type="warning" :message="session('warning')" dismissible /></div>
    @endif
    @if (session('error'))
        <div class="site-shell" style="margin-top: 18px"><x-alert type="error" :message="session('error')" dismissible /></div>
    @endif
    @if ($errors->any())
        <div class="site-shell" style="margin-top: 18px">
            <x-alert type="error" :message="implode(' · ', $errors->all())" dismissible />
        </div>
    @endif
    <div id="main-content">@yield('content')</div>
    <x-visitor-ip />
    @php
        $authorEntryUrl = auth()->check() && $canAccessAuthorStudio
            ? route('dashboard', ['section' => 'submissions'])
            : (auth()->check() ? route('contact') : route('register', ['role' => 'author']));
    @endphp
    <footer class="site-footer"><div class="site-shell footer-grid"><div><a class="brand" href="{{ route('home') }}"><span class="brand-mark" aria-hidden="true">Y</span><span>{{ __('ui.app.name') }}</span></a><p class="footer-copy">{{ __('ui.nav.footer_copy') }}</p></div><div class="footer-links"><div><strong>{{ __('ui.nav.explore') }}</strong><a href="{{ route('novels.index') }}">{{ __('ui.nav.all_books') }}</a><a href="{{ route('categories.index') }}">{{ __('ui.nav.categories_link') }}</a></div><div><strong>{{ __('ui.nav.join_us') }}</strong><a href="{{ $authorEntryUrl }}">{{ __('ui.nav.author_submission') }}</a><a href="{{ route('about') }}">{{ __('ui.nav.about') }}</a></div><div><strong>{{ __('ui.nav.help') }}</strong><a href="{{ route('reading-guide') }}">{{ __('ui.nav.reading_guide') }}</a><a href="{{ route('contact') }}">{{ __('ui.nav.contact') }}</a></div></div></div><div class="site-shell footer-bottom"><span>© {{ date('Y') }} {{ __('ui.app.name') }}</span><span>{{ __('ui.nav.footer_note') }}</span></div></footer>
</body>
</html>

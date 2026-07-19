@php
    $localeManager = app(\App\Services\LocaleManager::class);
    $currentLocale = $localeManager->current();
@endphp

<div class="language-switcher">
    <span data-vue-language-switcher aria-hidden="true"></span>
    <form id="language-switch-form" class="language-switch-form" method="POST" action="{{ route('language.switch') }}" data-language-switcher>
        @csrf
        <label class="sr-only" for="site-language">{{ __('ui.locale.choose') }}</label>
        <select id="site-language" name="locale" aria-label="{{ __('ui.locale.choose') }}">
            @foreach ($localeManager->supported() as $code => $definition)
                <option value="{{ $code }}" @selected($code === $currentLocale)>{{ $definition['native'] }}</option>
            @endforeach
        </select>
    </form>
    {{ $slot }}
    <button type="submit" form="language-switch-form" aria-label="{{ __('ui.locale.apply') }}" title="{{ __('ui.locale.apply') }}">↗</button>
</div>

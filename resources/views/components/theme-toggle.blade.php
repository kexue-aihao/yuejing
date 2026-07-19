@php
    $themeOptions = [
        ['value' => 'light', 'label' => __('ui.theme.light'), 'title' => __('ui.theme.use_light'), 'icon' => '☀'],
        ['value' => 'dark', 'label' => __('ui.theme.dark'), 'title' => __('ui.theme.use_dark'), 'icon' => '☾'],
        ['value' => 'eye-care', 'label' => __('ui.theme.eye_care'), 'title' => __('ui.theme.use_eye_care'), 'icon' => '◒'],
        ['value' => 'system', 'label' => __('ui.theme.system'), 'title' => __('ui.theme.use_system'), 'icon' => '◐'],
    ];
@endphp
<div class="theme-toggle" role="radiogroup" aria-label="{{ __('ui.theme.choose') }}" data-vue-theme-toggle data-options='@json($themeOptions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)' data-aria-label="{{ __('ui.theme.choose') }}" data-storage-key="yuejing-theme" data-default-theme="system">
    <button type="button" class="theme-toggle-btn" data-theme-action="light" role="radio" aria-checked="false" tabindex="-1" title="{{ __('ui.theme.use_light') }}">
        <span class="theme-toggle-icon" aria-hidden="true">☀</span><span class="theme-toggle-label">{{ __('ui.theme.light') }}</span>
    </button>
    <button type="button" class="theme-toggle-btn" data-theme-action="dark" role="radio" aria-checked="false" tabindex="-1" title="{{ __('ui.theme.use_dark') }}">
        <span class="theme-toggle-icon" aria-hidden="true">☾</span><span class="theme-toggle-label">{{ __('ui.theme.dark') }}</span>
    </button>
    <button type="button" class="theme-toggle-btn" data-theme-action="eye-care" role="radio" aria-checked="false" tabindex="-1" title="{{ __('ui.theme.use_eye_care') }}">
        <span class="theme-toggle-icon" aria-hidden="true">◒</span><span class="theme-toggle-label">{{ __('ui.theme.eye_care') }}</span>
    </button>
    <button type="button" class="theme-toggle-btn" data-theme-action="system" role="radio" aria-checked="false" tabindex="-1" title="{{ __('ui.theme.use_system') }}">
        <span class="theme-toggle-icon" aria-hidden="true">◐</span><span class="theme-toggle-label">{{ __('ui.theme.system') }}</span>
    </button>
</div>

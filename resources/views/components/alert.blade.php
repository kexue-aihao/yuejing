@props(['type' => 'info', 'message' => '', 'dismissible' => false])

@php
$icons = [
    'success' => '✓',
    'error'   => '✕',
    'warning' => '⚠',
    'info'    => 'ℹ',
];
$icon = $icons[$type] ?? $icons['info'];
@endphp

<div {{ $attributes->merge(['class' => 'toast toast-' . $type]) }} role="{{ in_array($type, ['error', 'warning'], true) ? 'alert' : 'status' }}" aria-live="{{ in_array($type, ['error', 'warning'], true) ? 'assertive' : 'polite' }}">
    <span class="toast-icon" aria-hidden="true">{{ $icon }}</span>
    <span class="toast-text">{{ $message ?: $slot }}</span>
    @if($dismissible)
        <button type="button" class="toast-close" data-toast-dismiss aria-label="{{ __('ui.components.close_notification') }}">✕</button>
    @endif
</div>

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

<div {{ $attributes->merge(['class' => 'toast toast-' . $type]) }} role="alert">
    <span class="toast-icon">{{ $icon }}</span>
    <span class="toast-text">{{ $message ?: $slot }}</span>
    @if($dismissible)
        <button type="button" class="toast-close" data-toast-dismiss aria-label="关闭通知">✕</button>
    @endif
</div>

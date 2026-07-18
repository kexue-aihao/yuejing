@props(['icon' => '📚', 'message' => null, 'class' => ''])

@php($message = $message ?? __('ui.components.no_content'))

<div {{ $attributes->merge(['class' => 'empty-state' . ($class ? " {$class}" : '')]) }}>
    <span class="empty-icon">{{ $icon }}</span>
    <p class="empty-text">{{ $message }}</p>
    @if(isset($slot) && trim((string) $slot) !== '')
        <div class="empty-action">{{ $slot }}</div>
    @endif
</div>

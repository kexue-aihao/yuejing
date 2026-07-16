@props(['label', 'value', 'class' => ''])

<div {{ $attributes->merge(['class' => 'metric-card' . ($class ? " {$class}" : '')]) }}>
    <span class="metric-value">{{ $value }}</span>
    <span class="metric-label">{{ $label }}</span>
</div>

@props(['status' => 'pending'])

@php
$label = __('ui.status.'.$status);
$label = $label === 'ui.status.'.$status ? __('ui.components.no_content') : $label;
@endphp

<span {{ $attributes->merge(['class' => 'status' . ($status !== 'published' ? " {$status}" : '')]) }}>{{ $label }}</span>

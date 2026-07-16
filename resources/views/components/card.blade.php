@props(['class' => '', 'padding' => true])

<div {{ $attributes->merge(['class' => 'panel' . ($padding ? '' : ' panel-flush') . ($class ? " {$class}" : '')]) }}>
    {{ $slot }}
</div>

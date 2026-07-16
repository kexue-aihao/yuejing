@props(['status' => 'pending'])

@php
$labels = [
    'pending'  => '待审核',
    'approved' => '已通过',
    'rejected' => '已拒绝',
    'draft'    => '草稿',
    'published'=> '已发布',
];
$label = $labels[$status] ?? $status;
@endphp

<span {{ $attributes->merge(['class' => 'status' . ($status !== 'published' ? " {$status}" : '')]) }}>{{ $label }}</span>

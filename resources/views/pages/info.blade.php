@extends('layouts.app')

@php
    $isContact = $page === 'contact';
    $title = __('ui.info.'.$page.'.title');
    $eyebrow = __('ui.info.'.$page.'.eyebrow');
    $heading = __('ui.info.'.$page.'.heading');
    $intro = __('ui.info.'.$page.'.intro');
    $sections = __('ui.info.'.$page.'.sections');
@endphp

@section('title', $title)

@section('content')
<main class="site-shell info-page"><header class="info-page-heading"><p class="eyebrow">{{ $eyebrow }}</p><h1>{{ $heading }}</h1><p class="page-intro">{{ $intro }}</p></header><div class="info-content">
    @foreach ($sections as $section)<section class="panel info-panel"><h2>{{ $section['title'] }}</h2><p>{{ $section['body'] }}</p></section>@endforeach
    @if ($isContact)<section class="panel info-contact-card"><p class="panel-kicker">{{ __('ui.info.contact.email_label') }}</p>@if ($contactEmail !== '')<a class="info-contact-link" href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a>@else<p class="muted">{{ __('ui.info.contact.email_unavailable') }}</p>@endif</section>@endif
</div></main>
@endsection

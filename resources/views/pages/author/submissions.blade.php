@extends('layouts.app')

@section('title', __('ui.author.title'))

@section('content')
<main class="site-shell dashboard-page">
    <div class="dashboard-head">
        <div>
            <p class="eyebrow">{{ __('ui.author.eyebrow') }}</p>
            <h1>{{ __('ui.author.heading') }}</h1>
            <p>{{ __('ui.author.intro') }}</p>
        </div>
    </div>

    @include('pages.author._content', ['embedded' => false])
</main>
@endsection

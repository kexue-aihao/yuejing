@extends('layouts.app')

@section('title', __('ui.communication.messages_title').' · '.__('ui.app.name'))

@section('content')
<main class="site-shell communication-shell">
    @include('pages.messages._content', ['api' => $api, 'currentUserId' => $currentUserId, 'embedded' => false])
</main>
@endsection

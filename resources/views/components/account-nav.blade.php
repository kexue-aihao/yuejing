@props(['active'])

@php
    $socialOpen = in_array($active, ['messages', 'groups'], true);
@endphp

<nav class="dashboard-nav" aria-label="{{ __('ui.account.navigation') }}">
    <a class="{{ $active === 'dashboard' ? 'is-active' : '' }}" href="{{ route('dashboard') }}" @if($active === 'dashboard') aria-current="page" @endif>{{ __('ui.account.overview') }}</a>
    <a class="{{ $active === 'favorites' ? 'is-active' : '' }}" href="{{ route('account.favorites') }}" @if($active === 'favorites') aria-current="page" @endif>{{ __('ui.account.favorites') }}</a>
    <a class="{{ $active === 'reading-records' ? 'is-active' : '' }}" href="{{ route('account.reading-records') }}" @if($active === 'reading-records') aria-current="page" @endif>{{ __('ui.account.reading_records') }}</a>
    <a class="{{ $active === 'settings' ? 'is-active' : '' }}" href="{{ route('account.settings') }}" @if($active === 'settings') aria-current="page" @endif>{{ __('ui.account.settings') }}</a>
    @if (Route::has('messages.page') || Route::has('groups.page'))
        <details class="dashboard-nav-group" @if($socialOpen) open @endif>
            <summary class="dashboard-nav-trigger">{{ __('ui.account.social') }}<span class="dashboard-nav-chevron" aria-hidden="true"></span></summary>
            <div class="dashboard-nav-submenu" data-account-social-menu>
                @if (Route::has('messages.page'))
                    <a class="{{ $active === 'messages' ? 'is-active' : '' }}" href="{{ route('dashboard', ['section' => 'messages']) }}" @if($active === 'messages') aria-current="page" @endif>{{ __('ui.account.messages') }}</a>
                @endif
                @if (Route::has('groups.page'))
                    <a class="{{ $active === 'groups' ? 'is-active' : '' }}" href="{{ route('dashboard', ['section' => 'groups']) }}" @if($active === 'groups') aria-current="page" @endif>{{ __('ui.account.groups') }}</a>
                @endif
            </div>
        </details>
    @endif
    @if (auth()->user()?->isRole(['author', 'editor', 'admin']))
        <a class="{{ $active === 'submissions' ? 'is-active' : '' }}" href="{{ route('dashboard', ['section' => 'submissions']) }}" @if($active === 'submissions') aria-current="page" @endif>{{ __('ui.account.submissions') }}</a>
        @if (Route::has('author.novels.index'))
            <a class="{{ in_array($active, ['author-novels', 'author-novel-edit', 'author-chapters'], true) ? 'is-active' : '' }}" href="{{ route('author.novels.index') }}" @if(in_array($active, ['author-novels', 'author-novel-edit', 'author-chapters'], true)) aria-current="page" @endif>{{ __('ui.account.novels') }}</a>
        @endif
    @endif
</nav>

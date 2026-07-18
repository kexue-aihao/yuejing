@props(['active'])

@php
    $socialOpen = in_array($active, ['messages', 'groups'], true);
@endphp

<nav class="dashboard-nav" aria-label="个人中心导航">
    <a class="{{ $active === 'dashboard' ? 'is-active' : '' }}" href="{{ route('dashboard') }}" @if($active === 'dashboard') aria-current="page" @endif>阅读概览</a>
    <a class="{{ $active === 'favorites' ? 'is-active' : '' }}" href="{{ route('account.favorites') }}" @if($active === 'favorites') aria-current="page" @endif>我的收藏</a>
    <a class="{{ $active === 'reading-records' ? 'is-active' : '' }}" href="{{ route('account.reading-records') }}" @if($active === 'reading-records') aria-current="page" @endif>阅读记录</a>
    <a class="{{ $active === 'settings' ? 'is-active' : '' }}" href="{{ route('account.settings') }}" @if($active === 'settings') aria-current="page" @endif>账号设置</a>
    @if (Route::has('messages.page') || Route::has('groups.page'))
        <details class="dashboard-nav-group" @if($socialOpen) open @endif>
            <summary class="dashboard-nav-trigger">社交<span class="dashboard-nav-chevron" aria-hidden="true"></span></summary>
            <div class="dashboard-nav-submenu" data-account-social-menu>
                @if (Route::has('messages.page'))
                    <a class="{{ $active === 'messages' ? 'is-active' : '' }}" href="{{ route('messages.page') }}" @if($active === 'messages') aria-current="page" @endif>站内私信</a>
                @endif
                @if (Route::has('groups.page'))
                    <a class="{{ $active === 'groups' ? 'is-active' : '' }}" href="{{ route('groups.page') }}" @if($active === 'groups') aria-current="page" @endif>实时交流群</a>
                @endif
            </div>
        </details>
    @endif
    @if (auth()->user()?->isRole(['author', 'editor', 'admin']))
        <a class="{{ $active === 'submissions' ? 'is-active' : '' }}" href="{{ route('author.submissions') }}" @if($active === 'submissions') aria-current="page" @endif>作品投稿</a>
    @endif
</nav>

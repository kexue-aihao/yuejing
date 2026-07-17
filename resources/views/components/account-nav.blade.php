@props(['active'])

<nav class="dashboard-nav" aria-label="个人中心导航">
    <a class="{{ $active === 'dashboard' ? 'is-active' : '' }}" href="{{ route('dashboard') }}" @if($active === 'dashboard') aria-current="page" @endif>阅读概览</a>
    <a class="{{ $active === 'favorites' ? 'is-active' : '' }}" href="{{ route('account.favorites') }}" @if($active === 'favorites') aria-current="page" @endif>我的收藏</a>
    <a class="{{ $active === 'reading-records' ? 'is-active' : '' }}" href="{{ route('account.reading-records') }}" @if($active === 'reading-records') aria-current="page" @endif>阅读记录</a>
    <a class="{{ $active === 'settings' ? 'is-active' : '' }}" href="{{ route('account.settings') }}" @if($active === 'settings') aria-current="page" @endif>账号设置</a>
    @if (auth()->user()?->isRole(['author', 'editor', 'admin']))
        <a class="{{ $active === 'submissions' ? 'is-active' : '' }}" href="{{ route('author.submissions') }}" @if($active === 'submissions') aria-current="page" @endif>作品投稿</a>
    @endif
</nav>

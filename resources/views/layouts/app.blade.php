<!doctype html>
<html lang="zh-CN" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', '阅境 · 在故事里相遇')</title>
    <script>
        (() => {
            try {
                const theme = localStorage.getItem('yuejing-theme');
                if (theme === 'light' || theme === 'dark' || theme === 'eye-care') document.documentElement.dataset.theme = theme;
            } catch (_) {
                // Use the system theme when storage is unavailable.
            }
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="site-body">
    <a class="skip-link" href="#main-content">跳到主要内容</a>
    <header class="site-header">
        @php
            $currentUser = auth()->user();
            $canAccessAuthorStudio = $currentUser?->isRole(['author', 'editor', 'admin']) ?? false;
        @endphp
        <div class="site-shell nav-shell">
            <a class="brand" href="{{ Route::has('home') ? route('home') : url('/') }}" aria-label="阅境首页"><span class="brand-mark">阅</span><span>阅境</span></a>
            <nav class="desktop-nav" aria-label="主导航">
                <a class="nav-link {{ request()->routeIs('home') ? 'is-active' : '' }}" href="{{ Route::has('home') ? route('home') : url('/') }}" @if(request()->routeIs('home')) aria-current="page" @endif>首页</a>
                <a class="nav-link {{ request()->routeIs('novels.*') ? 'is-active' : '' }}" href="{{ Route::has('novels.index') ? route('novels.index') : '#' }}" @if(request()->routeIs('novels.*')) aria-current="page" @endif>书库</a>
                <a class="nav-link" href="#categories">分类</a>
            </nav>
            <div class="nav-actions">
                <form class="nav-search" action="{{ Route::has('novels.index') ? route('novels.index') : '#' }}" method="get" role="search">
                    <label class="sr-only" for="global-search">搜索小说</label><input id="global-search" name="q" value="{{ request('q', '') }}" placeholder="搜索作品、作者" autocomplete="off"><button type="submit" aria-label="搜索">⌕</button>
                </form>
                @auth
                    <a class="profile-link" href="{{ Route::has('dashboard') ? route('dashboard') : '#' }}" aria-label="打开个人中心" title="个人中心">
                        <svg class="profile-link-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="8" r="3.25"></circle><path d="M5.5 19.25c.7-3.25 3-5 6.5-5s5.8 1.75 6.5 5"></path></svg>
                        <span class="profile-link-label">个人中心</span>
                    </a>
                    @if (auth()->user()->isRole('admin') && Route::has('admin.dashboard'))
                        <a class="login-link" href="{{ route('admin.dashboard') }}">管理后台</a>
                    @endif
                    @if (Route::has('logout'))
                        <form method="POST" action="{{ route('logout') }}"><button class="login-link" type="submit">退出</button>@csrf</form>
                    @endif
                @else
                    <a class="login-link" href="{{ Route::has('login') ? route('login') : '#' }}">登录</a>
                    <a class="button button-small" href="{{ Route::has('register') ? route('register') : '#' }}">注册</a>
                @endauth
                <x-theme-toggle />
                <button class="mobile-menu-toggle" type="button" data-menu-toggle aria-expanded="false" aria-controls="mobile-menu" aria-label="打开菜单"><span></span><span></span><span></span><span class="sr-only">打开菜单</span></button>
            </div>
        </div>
        <div id="mobile-menu" class="mobile-menu" data-mobile-menu hidden aria-hidden="true" inert>
            <button class="mobile-menu-close" type="button" data-menu-close aria-label="关闭菜单">✕</button>
            <nav class="site-shell mobile-nav" aria-label="移动端主导航"><a href="{{ Route::has('home') ? route('home') : url('/') }}">首页</a><a href="{{ Route::has('novels.index') ? route('novels.index') : '#' }}">书库</a><a href="#categories">分类</a>@auth <a href="{{ Route::has('dashboard') ? route('dashboard') : '#' }}">个人中心</a>@if (auth()->user()->isRole('admin') && Route::has('admin.dashboard'))<a href="{{ route('admin.dashboard') }}">管理后台</a>@endif @else<a href="{{ Route::has('login') ? route('login') : '#' }}">登录 / 注册</a>@endauth</nav>
        </div>
    </header>
    @if (session('success'))
        <div class="site-shell" style="margin-top: 18px"><x-alert type="success" :message="session('success')" dismissible /></div>
    @endif
    @if (session('warning'))
        <div class="site-shell" style="margin-top: 18px"><x-alert type="warning" :message="session('warning')" dismissible /></div>
    @endif
    @if (session('error'))
        <div class="site-shell" style="margin-top: 18px"><x-alert type="error" :message="session('error')" dismissible /></div>
    @endif
    @if ($errors->any())
        <div class="site-shell" style="margin-top: 18px">
            <x-alert type="error" :message="implode('；', $errors->all())" dismissible />
        </div>
    @endif
    <div id="main-content">@yield('content')</div>
    <x-visitor-ip />
    <footer class="site-footer"><div class="site-shell footer-grid"><div><a class="brand" href="{{ Route::has('home') ? route('home') : url('/') }}"><span class="brand-mark">阅</span><span>阅境</span></a><p class="footer-copy">让每一次打开，都遇见值得读完的故事。</p></div><div class="footer-links"><div><strong>探索</strong><a href="{{ Route::has('novels.index') ? route('novels.index') : '#' }}">全部作品</a><a href="#categories">作品分类</a></div><div><strong>加入我们</strong>@if ($canAccessAuthorStudio && Route::has('author.submissions'))<a href="{{ route('author.submissions') }}">作品投稿</a>@endif<a href="#">关于阅境</a></div><div><strong>帮助</strong><a href="#">阅读指南</a><a href="#">联系我们</a></div></div></div><div class="site-shell footer-bottom"><span>© {{ date('Y') }} 阅境阅读</span><span>把时间留给好故事</span></div></footer>
</body>
</html>

<!doctype html>
<html lang="zh-CN" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', '阅境 · 在故事里相遇')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="site-body">
    <a class="skip-link" href="#main-content">跳到主要内容</a>
    <header class="site-header">
        <div class="site-shell nav-shell">
            <a class="brand" href="{{ Route::has('home') ? route('home') : url('/') }}" aria-label="阅境首页"><span class="brand-mark">阅</span><span>阅境</span></a>
            <nav class="desktop-nav" aria-label="主导航">
                <a class="nav-link {{ request()->routeIs('home') ? 'is-active' : '' }}" href="{{ Route::has('home') ? route('home') : url('/') }}">首页</a>
                <a class="nav-link {{ request()->routeIs('novels.*') ? 'is-active' : '' }}" href="{{ Route::has('novels.index') ? route('novels.index') : '#' }}">书库</a>
                <a class="nav-link" href="#categories">分类</a>
                <a class="nav-link" href="{{ Route::has('author.submissions') ? route('author.submissions') : '#' }}">成为作者</a>
            </nav>
            <div class="nav-actions">
                <form class="nav-search" action="{{ Route::has('novels.index') ? route('novels.index') : '#' }}" method="get" role="search">
                    <label class="sr-only" for="global-search">搜索小说</label><input id="global-search" name="q" value="{{ request('q', '') }}" placeholder="搜索作品、作者" autocomplete="off"><button type="submit" aria-label="搜索">⌕</button>
                </form>
                @auth
                    <a class="avatar avatar-small" href="{{ Route::has('dashboard') ? route('dashboard') : '#' }}">{{ mb_substr(auth()->user()->name ?? '阅', 0, 1) }}</a>
                    @if (Route::has('logout'))
                        <form method="POST" action="{{ route('logout') }}"><button class="login-link" type="submit">退出</button>@csrf</form>
                    @endif
                @else
                    <a class="login-link" href="{{ Route::has('login') ? route('login') : '#' }}">登录</a>
                    <a class="button button-small" href="{{ Route::has('register') ? route('register') : '#' }}">注册</a>
                @endauth
                <button class="mobile-menu-toggle" type="button" data-menu-toggle aria-expanded="false" aria-controls="mobile-menu"><span></span><span></span><span></span><span class="sr-only">打开菜单</span></button>
            </div>
        </div>
        <div id="mobile-menu" class="mobile-menu" data-mobile-menu hidden>
            <nav class="site-shell mobile-nav" aria-label="移动端主导航"><a href="{{ Route::has('home') ? route('home') : url('/') }}">首页</a><a href="{{ Route::has('novels.index') ? route('novels.index') : '#' }}">书库</a><a href="#categories">分类</a><a href="{{ Route::has('author.submissions') ? route('author.submissions') : '#' }}">成为作者</a><a href="{{ Route::has('dashboard') ? route('dashboard') : (Route::has('login') ? route('login') : '#') }}">{{ auth()->check() ? '我的阅境' : '登录 / 注册' }}</a></nav>
        </div>
    </header>
    <div id="main-content">@yield('content')</div>
    <footer class="site-footer"><div class="site-shell footer-grid"><div><a class="brand" href="{{ Route::has('home') ? route('home') : url('/') }}"><span class="brand-mark">阅</span><span>阅境</span></a><p class="footer-copy">让每一次打开，都遇见值得读完的故事。</p></div><div class="footer-links"><div><strong>探索</strong><a href="{{ Route::has('novels.index') ? route('novels.index') : '#' }}">全部作品</a><a href="#categories">作品分类</a></div><div><strong>加入我们</strong><a href="{{ Route::has('author.submissions') ? route('author.submissions') : '#' }}">作者投稿</a><a href="#">关于阅境</a></div><div><strong>帮助</strong><a href="#">阅读指南</a><a href="#">联系我们</a></div></div></div><div class="site-shell footer-bottom"><span>© {{ date('Y') }} 阅境阅读</span><span>把时间留给好故事</span></div></footer>
</body>
</html>
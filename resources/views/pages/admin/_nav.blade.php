@php($active = $active ?? '')
<nav class="dashboard-nav admin-nav" aria-label="管理后台导航">
    <a class="{{ $active === 'dashboard' ? 'is-active' : '' }}" href="{{ route('admin.dashboard') }}" @if($active === 'dashboard') aria-current="page" @endif>控制台</a>
    <a class="{{ $active === 'submissions' ? 'is-active' : '' }}" href="{{ route('admin.submissions.index') }}" @if($active === 'submissions') aria-current="page" @endif>投稿审核</a>
    <a class="{{ $active === 'novels' ? 'is-active' : '' }}" href="{{ route('admin.novels.index') }}" @if($active === 'novels') aria-current="page" @endif>小说管理</a>
    <a class="{{ $active === 'categories' ? 'is-active' : '' }}" href="{{ route('admin.categories.index') }}" @if($active === 'categories') aria-current="page" @endif>分类管理</a>
    <a class="{{ $active === 'settings' ? 'is-active' : '' }}" href="{{ route('admin.settings') }}" @if($active === 'settings') aria-current="page" @endif>站点设置</a>
    <a class="{{ $active === 'audit-logs' ? 'is-active' : '' }}" href="{{ route('admin.audit-logs.index') }}" @if($active === 'audit-logs') aria-current="page" @endif>投稿审计日志</a>
</nav>

@php($active = $active ?? '')
<nav class="dashboard-nav admin-nav" aria-label="管理后台导航">
    <a class="{{ $active === 'dashboard' ? 'is-active' : '' }}" href="{{ route('admin.dashboard') }}">控制台</a>
    <a class="{{ $active === 'submissions' ? 'is-active' : '' }}" href="{{ route('admin.submissions.index') }}">投稿审核</a>
    <a class="{{ $active === 'novels' ? 'is-active' : '' }}" href="{{ route('admin.novels.index') }}">小说管理</a>
    <a class="{{ $active === 'categories' ? 'is-active' : '' }}" href="{{ route('admin.categories.index') }}">分类管理</a>
    <a class="{{ $active === 'settings' ? 'is-active' : '' }}" href="{{ route('admin.settings') }}">站点设置</a>
    <a class="{{ $active === 'audit-logs' ? 'is-active' : '' }}" href="{{ route('admin.audit-logs.index') }}">审计日志</a>
</nav>

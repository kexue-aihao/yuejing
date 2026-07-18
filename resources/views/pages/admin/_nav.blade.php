@php($active = $active ?? '')
<nav class="dashboard-nav admin-nav" aria-label="{{ __('ui.admin.navigation') }}">
    <a class="{{ $active === 'dashboard' ? 'is-active' : '' }}" href="{{ route('admin.dashboard') }}" @if($active === 'dashboard') aria-current="page" @endif>{{ __('ui.admin.dashboard') }}</a>
    <a class="{{ $active === 'submissions' ? 'is-active' : '' }}" href="{{ route('admin.submissions.index') }}" @if($active === 'submissions') aria-current="page" @endif>{{ __('ui.admin.review_submissions') }}</a>
    <a class="{{ $active === 'novels' ? 'is-active' : '' }}" href="{{ route('admin.novels.index') }}" @if($active === 'novels') aria-current="page" @endif>{{ __('ui.admin.novel_management') }}</a>
    <a class="{{ $active === 'categories' ? 'is-active' : '' }}" href="{{ route('admin.categories.index') }}" @if($active === 'categories') aria-current="page" @endif>{{ __('ui.admin.category_management') }}</a>
    <a class="{{ $active === 'settings' ? 'is-active' : '' }}" href="{{ route('admin.settings') }}" @if($active === 'settings') aria-current="page" @endif>{{ __('ui.admin.site_settings') }}</a>
    <a class="{{ $active === 'audit-logs' ? 'is-active' : '' }}" href="{{ route('admin.audit-logs.index') }}" @if($active === 'audit-logs') aria-current="page" @endif>{{ __('ui.admin.audit_logs') }}</a>
</nav>

@extends('layouts.app')

@section('title', '账号设置 · 阅境')
@section('content')
<main class="site-shell dashboard-page">
    <div class="dashboard-head"><div><p class="eyebrow">ACCOUNT SETTINGS</p><h1>账号设置</h1><p>管理你的公开信息和登录安全。</p></div></div>
    <div class="dashboard-grid">
        <x-account-nav active="settings" />
        <div class="dashboard-content">
            @if (session('status'))<div class="alert">{{ session('status') }}</div>@endif
            @if ($errors->any())<div class="alert">{{ $errors->first() }}</div>@endif
            <form class="panel" method="POST" action="{{ route('account.settings.update') }}">
                @csrf @method('PUT')
                <div class="panel-heading"><h2>基本信息</h2><button class="button button-primary button-small" type="submit">保存修改</button></div>
                <div class="settings-grid"><div class="form-field"><label for="name">昵称</label><input id="name" name="name" value="{{ old('name', $user->name) }}" required autocomplete="name"></div><div class="form-field"><label for="email">邮箱</label><input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required autocomplete="email"></div></div>
                <p class="settings-hint">修改邮箱后需要重新完成邮箱验证。</p>
            </form>
            <section class="panel security-panel"><div class="panel-heading"><h2>登录安全</h2><span class="status {{ $twoFactorEnabled ? '' : 'pending' }}">{{ $twoFactorEnabled ? '已保护' : '未设置' }}</span></div><div class="security-row"><div><strong>二步验证</strong><p>{{ $twoFactorEnabled ? '登录时需要验证器验证码。' : '使用验证器应用为账号增加一层保护。' }}</p></div><a class="button {{ $twoFactorEnabled ? 'button-outline' : 'button-dark' }}" href="{{ route('two-factor.show') }}">{{ $twoFactorEnabled ? '管理二步验证' : '立即设置' }} <span>→</span></a></div></section>
        </div>
    </div>
</main>
@endsection

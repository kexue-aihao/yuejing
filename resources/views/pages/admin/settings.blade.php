@extends('layouts.app')

@section('title', '阅境 · 站点设置')

@section('content')
<main class="site-shell dashboard-page">
    <div class="dashboard-head"><div><p class="eyebrow">ADMIN CONSOLE</p><h1>站点设置</h1><p>管理站点信息、验证策略和邮件发送状态。</p></div><span class="status">管理权限</span></div>
    <div class="dashboard-grid">
        @include('pages.admin._nav', ['active' => 'settings'])
        <div class="dashboard-content">
            @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
            @if ($errors->any())<div class="alert">{{ $errors->first() }}</div>@endif
            <form class="panel form-stack" method="POST" action="{{ route('admin.settings.update') }}">
                @csrf @method('PUT')
                <div class="panel-heading"><h2>基础信息</h2><button class="button button-primary button-small" type="submit">保存设置</button></div>
                <div class="settings-grid">
                    <div class="form-field"><label for="site_name">站点名称</label><input id="site_name" name="site_name" value="{{ old('site_name', $settingValues['site_name']) }}" required></div>
                    <div class="form-field"><label for="site_tagline">站点副标题</label><input id="site_tagline" name="site_tagline" value="{{ old('site_tagline', $settingValues['site_tagline']) }}" required></div>
                    <div class="form-field"><label for="contact_email">联系邮箱</label><input id="contact_email" name="contact_email" type="email" value="{{ old('contact_email', $settingValues['contact_email']) }}" required></div>
                    <div class="form-field"><label for="accent_color">主题色</label><select id="accent_color" name="accent_color"><option value="coral" @selected(old('accent_color', $settingValues['accent_color']) === 'coral')>朱砂红</option><option value="moss" @selected(old('accent_color', $settingValues['accent_color']) === 'moss')>苔绿色</option><option value="ink" @selected(old('accent_color', $settingValues['accent_color']) === 'ink')>墨黑色</option></select></div>
                </div>
                <div class="settings-divider"></div>
                <div class="form-field"><label class="check-row"><input type="hidden" name="email_verification_required" value="0"><input type="checkbox" name="email_verification_required" value="1" @checked(old('email_verification_required', $settingValues['email_verification_required']))> 新用户必须完成邮箱验证</label><p class="field-hint">开启后，未验证邮箱的账户无法访问需要验证的内容功能。</p></div>
                <div class="settings-grid">
                    <label class="check-row"><input type="hidden" name="show_rank" value="0"><input type="checkbox" name="show_rank" value="1" @checked(old('show_rank', $settingValues['show_rank']))> 首页展示热度榜</label>
                    <label class="check-row"><input type="hidden" name="show_new" value="0"><input type="checkbox" name="show_new" value="1" @checked(old('show_new', $settingValues['show_new']))> 首页展示新书区域</label>
                    <label class="check-row"><input type="hidden" name="allow_comments" value="0"><input type="checkbox" name="allow_comments" value="1" @checked(old('allow_comments', $settingValues['allow_comments']))> 开放章节评论</label>
                </div>
            </form>
            <section class="panel">
                <div class="panel-heading"><div><h2>SMTP 状态</h2><p class="panel-description">使用当前应用邮件配置发送一封测试邮件。</p></div><span class="status">可测试</span></div>
                <form class="inline-form" method="POST" action="{{ route('admin.settings.email-test') }}">@csrf<div class="form-field"><label for="smtp_email">测试收件邮箱</label><input id="smtp_email" name="email" type="email" value="{{ old('email', $settingValues['contact_email']) }}" required></div><button class="button button-dark" type="submit">发送测试邮件</button></form>
                <p class="settings-hint">当前驱动：{{ config('mail.default') }} · 主机：{{ config('mail.mailers.'.config('mail.default').'.host', '未配置') }}</p>
            </section>
        </div>
    </div>
</main>
@endsection

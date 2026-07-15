@extends('layouts.app')
@section('title', '二步验证 · 阅境')
@section('content')
<main class="auth-page">
    <section class="auth-panel">
        <p class="eyebrow">SECURITY SETTINGS</p>
        <h1>二步验证</h1>
        <p>使用验证器应用保护你的阅境账号。</p>
        @if ($errors->any())<div class="alert">{{ $errors->first() }}</div>@endif
        @if (session('status'))<div class="alert">{{ session('status') }}</div>@endif
        @if ($setting?->enabled)
            <p>二步验证已启用。禁用前请输入当前密码或一次性验证码。</p>
            <form class="form-stack" method="POST" action="{{ route('two-factor.disable') }}">
                @csrf @method('DELETE')
                <div class="form-field"><label for="current_password">当前密码</label><input id="current_password" name="current_password" type="password" autocomplete="current-password"></div>
                <div class="form-field"><label for="disable_code">TOTP 验证码</label><input id="disable_code" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code"></div>
                <button class="button button-dark" type="submit">禁用二步验证</button>
            </form>
        @else
            @php($setup = session('two_factor_setup'))
            @if ($setup)
                <div class="alert"><strong>请保存恢复码。</strong><br>{{ implode(' · ', $setup['recovery_codes']) }}</div>
                <p>将这个密钥添加到验证器应用，然后输入应用生成的 6 位验证码确认启用。</p>
                <p><strong>密钥：</strong>{{ $setup['secret'] }}</p>
                <form class="form-stack" method="POST" action="{{ route('two-factor.enable') }}">
                    @csrf
                    <div class="form-field"><label for="enable_code">验证器验证码</label><input id="enable_code" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autocomplete="one-time-code"></div>
                    <button class="button button-primary" type="submit">确认启用</button>
                </form>
            @else
                <form class="form-stack" method="POST" action="{{ route('two-factor.enable') }}">
                    @csrf
                    <button class="button button-primary" type="submit">生成设置密钥</button>
                </form>
            @endif
        @endif
    </section>
</main>
@endsection

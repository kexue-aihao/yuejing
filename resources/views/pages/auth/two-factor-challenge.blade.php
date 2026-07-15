@extends('layouts.app')
@section('title', '验证登录 · 阅境')
@section('content')
<main class="auth-page">
    <section class="auth-panel">
        <p class="eyebrow">SECURE SIGN IN</p>
        <h1>验证你的登录</h1>
        <p>请输入验证器应用生成的 6 位验证码，或使用一个恢复码。</p>
        @if ($errors->any())<div class="alert">{{ $errors->first() }}</div>@endif
        <form class="form-stack" method="POST" action="{{ route('two-factor.challenge') }}">
            @csrf
            <div class="form-field"><label for="code">验证码</label><input id="code" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code"></div>
            <div class="form-field"><label for="recovery_code">恢复码</label><input id="recovery_code" name="recovery_code" autocapitalize="characters" autocomplete="off"></div>
            <button class="button button-primary form-submit" type="submit">完成登录 <span aria-hidden="true">→</span></button>
        </form>
        <p class="auth-foot"><a href="{{ route('login') }}">返回登录</a></p>
    </section>
</main>
@endsection

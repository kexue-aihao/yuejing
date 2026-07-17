@extends('layouts.app')

@section('title', '站内私信 · 阅境')

@section('content')
<main class="site-shell communication-page messages-page"
      data-messages-app
      data-api="@json($api)"
      data-current-user-id="{{ $currentUserId }}">
    <div class="communication-head">
        <div>
            <p class="eyebrow">PRIVATE MESSAGES</p>
            <h1>站内私信</h1>
            <p>和故事里的同行者保持联系。搜索用户后即可发起会话，消息会在打开页面时持续更新。</p>
        </div>
        <nav class="communication-switcher" aria-label="消息入口">
            <a class="is-active" href="{{ route('messages.page') }}" aria-current="page">站内私信</a>
            <a href="{{ route('groups.page') }}">交流群</a>
        </nav>
    </div>

    <div class="communication-layout">
        <aside class="communication-sidebar panel">
            <div class="panel-heading">
                <div><p class="panel-kicker">CONVERSATIONS</p><h2>我的会话</h2></div>
                <span class="live-dot" aria-label="实时更新中"></span>
            </div>
            <form class="communication-search" method="get" action="{{ $api['users'] }}" data-user-search-form>
                @csrf
                <label class="sr-only" for="message-user-search">搜索用户</label>
                <input id="message-user-search" name="q" placeholder="搜索昵称或邮箱" autocomplete="off">
                <button class="button button-small" type="submit">搜索</button>
            </form>
            <div class="search-results" data-user-results aria-live="polite"></div>
            <div class="conversation-list" data-conversation-list aria-live="polite">
                <p class="communication-empty">正在加载会话…</p>
            </div>
            <noscript><p class="no-script-note">启用 JavaScript 后可以搜索用户、切换会话并实时接收新消息。上方表单在关闭脚本时仍会显示，接口会返回 JSON 结果。</p></noscript>
        </aside>

        <section class="communication-main panel" aria-labelledby="private-conversation-title">
            <div class="panel-heading communication-main-heading">
                <div>
                    <p class="panel-kicker">DIRECT CHAT</p>
                    <h2 id="private-conversation-title" data-conversation-title>选择一个会话</h2>
                    <p class="panel-subtitle" data-conversation-meta>从左侧选择会话，或搜索用户发起新的私信。</p>
                </div>
                <span class="connection-status" data-connection-status>未连接</span>
            </div>

            <div class="message-list" data-message-list aria-live="polite" aria-label="私信内容">
                <p class="communication-empty">选择会话后，消息会显示在这里。</p>
            </div>

            <form class="message-compose" method="post" action="{{ $api['store'] }}" data-private-send-form>
                @csrf
                <input type="hidden" name="conversation_id" data-conversation-id>
                <input type="hidden" name="recipient_id" data-recipient-id>
                <label class="sr-only" for="private-message-body">输入私信</label>
                <textarea id="private-message-body" name="body" rows="3" placeholder="写下想说的话…" required></textarea>
                <div class="compose-actions">
                    <span class="form-help" data-compose-help>先选择会话，或从搜索结果中选择用户。</span>
                    <button class="button button-primary" type="submit">发送私信 <span>→</span></button>
                </div>
            </form>
            <noscript><p class="no-script-note">无 JavaScript 时可以直接提交表单；发送成功后，接口会返回 JSON。实时消息和已读状态需要启用 JavaScript。</p></noscript>
        </section>
    </div>
</main>
@endsection

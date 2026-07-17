@extends('layouts.app')

@section('title', '交流群 · 阅境')

@section('content')
<main class="site-shell communication-page groups-page"
      data-groups-app
      data-api='@json($api)'
      data-current-user-id="{{ $currentUserId }}">
    <div class="communication-head">
        <div>
            <p class="eyebrow">COMMUNITY GROUPS</p>
            <h1>交流群</h1>
            <p>创建一个小组，和共同阅读的人聊聊作品、章节与灵感。群成员和消息已读统计都在当前页面集中呈现。</p>
        </div>
        <nav class="communication-switcher" aria-label="消息入口">
            <a href="{{ route('messages.page') }}">站内私信</a>
            <a class="is-active" href="{{ route('groups.page') }}" aria-current="page">交流群</a>
        </nav>
    </div>

    <div class="communication-layout groups-layout">
        <aside class="communication-sidebar panel">
            <div class="panel-heading">
                <div><p class="panel-kicker">YOUR GROUPS</p><h2>我的群聊</h2></div>
                <span class="live-dot" aria-label="实时更新中"></span>
            </div>
            <div class="group-list" data-group-list aria-live="polite">
                <p class="communication-empty">正在加载群聊…</p>
            </div>
            <form class="group-create-form" method="post" action="{{ $api['store'] }}" data-group-create-form>
                @csrf
                <h3>创建交流群</h3>
                <label class="form-field"><span>群名称</span><input name="name" placeholder="例如：本周共读小组" required></label>
                <fieldset class="member-picker">
                    <legend>选择成员</legend>
                    <div data-user-checklist><p class="form-help">启用 JavaScript 后会加载可邀请的用户。</p></div>
                </fieldset>
                <button class="button button-dark" type="submit">创建群聊 <span>→</span></button>
            </form>
            <noscript><p class="no-script-note">无 JavaScript 时可以提交群名称和成员选择表单；群聊 API 会返回 JSON。</p></noscript>
        </aside>

        <section class="communication-main panel" aria-labelledby="group-title">
            <div class="panel-heading communication-main-heading">
                <div>
                    <p class="panel-kicker">GROUP CHAT</p>
                    <h2 id="group-title" data-group-title>选择一个群聊</h2>
                    <p class="panel-subtitle" data-group-meta>从左侧选择群聊，查看成员并开始交流。</p>
                </div>
                <span class="connection-status" data-group-connection-status>未连接</span>
            </div>

            <div class="group-members" data-group-members>
                <div class="section-label"><span>群成员</span><span data-member-count>0 人</span></div>
                <div class="member-chips" data-member-list><span class="muted">选择群聊后显示成员。</span></div>
                <form class="member-add-form" method="post" action="{{ $api['addMember'] }}" data-member-add-form>
                    @csrf
                    <label class="sr-only" for="group-member-select">选择要邀请的成员</label>
                    <select id="group-member-select" name="user_id" data-member-select>
                        <option value="">选择成员</option>
                    </select>
                    <button class="button button-outline button-small" type="submit">添加成员</button>
                </form>
            </div>

            <div class="message-list" data-group-message-list aria-live="polite" aria-label="群聊内容">
                <p class="communication-empty">选择群聊后，消息会显示在这里。</p>
            </div>

            <form class="message-compose" method="post" action="{{ $api['sendMessage'] }}" data-group-send-form>
                @csrf
                <label class="sr-only" for="group-message-body">输入群聊消息</label>
                <textarea id="group-message-body" name="body" rows="3" placeholder="和群友聊聊…" required></textarea>
                <div class="compose-actions">
                    <span class="form-help" data-group-compose-help>先选择群聊。</span>
                    <button class="button button-primary" type="submit">发送消息 <span>→</span></button>
                </div>
            </form>
            <noscript><p class="no-script-note">无 JavaScript 时可以直接提交消息表单；消息和已读统计需要启用 JavaScript 才会在页面内更新。</p></noscript>
        </section>
    </div>
</main>
@endsection

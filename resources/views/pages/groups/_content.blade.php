@php
    $embedded = $embedded ?? false;
    $messagesUrl = $embedded ? route('dashboard', ['section' => 'messages']) : route('messages.page');
    $groupsUrl = $embedded ? route('dashboard', ['section' => 'groups']) : route('groups.page');
@endphp

<div class="communication-page groups-page {{ $embedded ? 'embedded-communication-page' : '' }}"
     data-groups-app
     data-api='@json($api)'
     data-current-user-id="{{ $currentUserId }}">
    <div class="communication-head">
        <div>
            <p class="eyebrow">{{ __('ui.communication.groups_eyebrow') }}</p>
            <h1>{{ __('ui.communication.groups_title') }}</h1>
            <p>{{ __('ui.communication.groups_intro') }}</p>
        </div>
        <nav class="communication-switcher" aria-label="{{ __('ui.communication.entry_label') }}">
            <a href="{{ $messagesUrl }}">{{ __('ui.account.messages') }}</a>
            <a class="is-active" href="{{ $groupsUrl }}" aria-current="page">{{ __('ui.account.groups') }}</a>
        </nav>
    </div>

    <div class="communication-layout groups-layout">
        <aside class="communication-sidebar panel">
            <div class="panel-heading">
                <div><p class="panel-kicker">{{ __('ui.communication.groups_label') }}</p><h2>{{ __('ui.communication.groups') }}</h2></div>
                <span class="live-dot" aria-label="{{ __('ui.communication.live') }}"></span>
            </div>
            <div class="group-list" data-group-list aria-live="polite">
                <p class="communication-empty">{{ __('ui.communication.loading_groups') }}</p>
            </div>
            <form class="group-create-form" method="post" action="{{ $api['store'] ?? $groupsUrl }}" data-group-create-form>
                @csrf
                <h3>{{ __('ui.communication.create_group') }}</h3>
                <label class="form-field"><span>{{ __('ui.communication.group_name') }}</span><input name="name" placeholder="{{ __('ui.communication.group_name_placeholder') }}" required></label>
                <fieldset class="member-picker">
                    <legend>{{ __('ui.communication.choose_members') }}</legend>
                    <div data-user-checklist><p class="form-help">{{ __('ui.communication.load_invitable') }}</p></div>
                </fieldset>
                <button class="button button-dark" type="submit">{{ __('ui.communication.create_group_button') }} <span>→</span></button>
            </form>
            <noscript><p class="no-script-note">{{ __('ui.frontend.noscript_groups_create') }}</p></noscript>
        </aside>

        <section class="communication-main panel" aria-labelledby="group-title">
            <div class="panel-heading communication-main-heading">
                <div>
                    <p class="panel-kicker">{{ __('ui.communication.group_chat') }}</p>
                    <h2 id="group-title" data-group-title>{{ __('ui.communication.choose_group') }}</h2>
                    <p class="panel-subtitle" data-group-meta>{{ __('ui.communication.group_hint') }}</p>
                </div>
                <span class="connection-status" data-group-connection-status>{{ __('ui.communication.not_connected') }}</span>
            </div>

            <div class="group-members" data-group-members>
                <div class="section-label"><span>{{ __('ui.communication.members') }}</span><span data-member-count>{{ __('ui.communication.member_count', ['count' => 0]) }}</span></div>
                <div class="member-chips" data-member-list><span class="muted">{{ __('ui.communication.members_after_choose') }}</span></div>
                <form class="member-add-form" method="post" action="{{ $api['addMember'] ?? $groupsUrl }}" data-member-add-form>
                    @csrf
                    <label class="sr-only" for="group-member-select">{{ __('ui.communication.invite_member') }}</label>
                    <select id="group-member-select" name="user_id" data-member-select>
                        <option value="">{{ __('ui.communication.choose_member') }}</option>
                    </select>
                    <button class="button button-outline button-small" type="submit">{{ __('ui.communication.add_member') }}</button>
                </form>
            </div>

            <div class="message-list" data-group-message-list aria-live="polite" aria-label="{{ __('ui.communication.group_content') }}">
                <p class="communication-empty">{{ __('ui.communication.choose_group_hint') }}</p>
            </div>

            <form class="message-compose" method="post" action="{{ $api['sendMessage'] ?? $groupsUrl }}" data-group-send-form>
                @csrf
                <label class="sr-only" for="group-message-body">{{ __('ui.communication.input_group_message') }}</label>
                <textarea id="group-message-body" name="body" rows="3" placeholder="{{ __('ui.communication.group_placeholder') }}" required></textarea>
                <div class="compose-actions">
                    <span class="form-help" data-group-compose-help>{{ __('ui.communication.compose_group_hint') }}</span>
                    <button class="button button-primary" type="submit">{{ __('ui.communication.send_group_message') }} <span>→</span></button>
                </div>
            </form>
            <noscript><p class="no-script-note">{{ __('ui.frontend.noscript_groups_send') }}</p></noscript>
        </section>
    </div>
</div>

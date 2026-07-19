@php
    $embedded = $embedded ?? false;
    $messagesUrl = $embedded ? route('dashboard', ['section' => 'messages']) : route('messages.page');
    $groupsUrl = $embedded ? route('dashboard', ['section' => 'groups']) : route('groups.page');
    $vueTranslations = array_merge(trans('ui.frontend'), trans('ui.communication'), [
        'messages' => __('ui.account.messages'),
        'groups' => __('ui.account.groups'),
    ]);
@endphp

<div class="communication-page messages-page {{ $embedded ? 'embedded-communication-page' : '' }}"
     data-messages-app
     data-vue-private-messages
     data-api='@json($api, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)'
     data-translations='@json($vueTranslations, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)'
     data-current-user-id="{{ $currentUserId }}"
     data-csrf-token="{{ csrf_token() }}"
     data-messages-url="{{ $messagesUrl }}"
     data-groups-url="{{ $groupsUrl }}"
     data-embedded="{{ $embedded ? '1' : '0' }}">
    <div class="communication-head">
        <div>
            <p class="eyebrow">{{ __('ui.communication.messages_eyebrow') }}</p>
            <h1>{{ __('ui.communication.messages_title') }}</h1>
            <p>{{ __('ui.communication.messages_intro') }}</p>
        </div>
        <nav class="communication-switcher" aria-label="{{ __('ui.communication.entry_label') }}">
            <a class="is-active" href="{{ $messagesUrl }}" aria-current="page">{{ __('ui.account.messages') }}</a>
            <a href="{{ $groupsUrl }}">{{ __('ui.account.groups') }}</a>
        </nav>
    </div>

    <div class="communication-layout">
        <aside class="communication-sidebar panel">
            <div class="panel-heading">
                <div><p class="panel-kicker">{{ __('ui.communication.conversations_label') }}</p><h2>{{ __('ui.communication.conversations') }}</h2></div>
                <span class="live-dot" aria-label="{{ __('ui.communication.live') }}"></span>
            </div>
            <form class="communication-search" method="get" action="{{ $api['users'] ?? $messagesUrl }}" data-user-search-form>
                @csrf
                <label class="sr-only" for="message-user-search">{{ __('ui.communication.search_user') }}</label>
                <input id="message-user-search" name="q" placeholder="{{ __('ui.communication.search_placeholder') }}" autocomplete="off">
                <button class="button button-small" type="submit">{{ __('ui.communication.search') }}</button>
            </form>
            <div class="search-results" data-user-results aria-live="polite"></div>
            <div class="conversation-list" data-conversation-list aria-live="polite">
                <p class="communication-empty">{{ __('ui.communication.loading_conversations') }}</p>
            </div>
            <noscript><p class="no-script-note">{{ __('ui.frontend.noscript_messages_search') }}</p></noscript>
        </aside>

        <section class="communication-main panel" aria-labelledby="private-conversation-title">
            <div class="panel-heading communication-main-heading">
                <div>
                    <p class="panel-kicker">{{ __('ui.communication.direct_chat') }}</p>
                    <h2 id="private-conversation-title" data-conversation-title>{{ __('ui.communication.choose_conversation') }}</h2>
                    <p class="panel-subtitle" data-conversation-meta>{{ __('ui.communication.conversation_hint') }}</p>
                </div>
                <span class="connection-status" data-connection-status>{{ __('ui.communication.not_connected') }}</span>
            </div>

            <div class="message-list" data-message-list aria-live="polite" aria-label="{{ __('ui.communication.message_content') }}">
                <p class="communication-empty">{{ __('ui.communication.choose_conversation_hint') }}</p>
            </div>

            <form class="message-compose" method="post" action="{{ $api['store'] }}" data-private-send-form>
                @csrf
                <input type="hidden" name="conversation_id" data-conversation-id>
                <input type="hidden" name="recipient_id" data-recipient-id>
                <label class="sr-only" for="private-message-body">{{ __('ui.communication.input_message') }}</label>
                <textarea id="private-message-body" name="body" rows="3" placeholder="{{ __('ui.communication.message_placeholder') }}" required></textarea>
                <div class="compose-actions">
                    <span class="form-help" data-compose-help>{{ __('ui.communication.compose_message_hint') }}</span>
                    <button class="button button-primary" type="submit">{{ __('ui.communication.send_message') }} <span>→</span></button>
                </div>
            </form>
            <noscript><p class="no-script-note">{{ __('ui.frontend.noscript_messages_send') }}</p></noscript>
        </section>
    </div>
</div>

<script setup>
import { computed, ref } from 'vue';
import MessageBubble from './MessageBubble.vue';
import { entityId, entityName, translate } from './useCommunicationApi.js';
import { usePrivateMessages } from './usePrivateMessages.js';

const props = defineProps({
    api: {
        type: Object,
        required: true,
    },
    currentUserId: {
        type: [String, Number],
        required: true,
    },
    csrfToken: {
        type: String,
        default: '',
    },
    translations: {
        type: Object,
        default: () => ({}),
    },
    messagesUrl: {
        type: String,
        default: '#',
    },
    groupsUrl: {
        type: String,
        default: '#',
    },
    embedded: {
        type: Boolean,
        default: false,
    },
});

const searchQuery = ref('');
const messageBody = ref('');
const searchError = ref('');
const sendError = ref('');
const isSearching = ref(false);
const isSending = ref(false);

const {
    conversations,
    searchResults,
    messages,
    activeId,
    activeRecipientId,
    status,
    statusState,
    title,
    meta,
    help,
    userId,
    conversationName,
    searchUsers,
    selectConversation,
    sendMessage,
} = usePrivateMessages({
    api: () => props.api,
    currentUserId: () => props.currentUserId,
    csrfToken: () => props.csrfToken,
    translations: () => props.translations,
});

const statusText = computed(() => status.value || '');

function t(key, replacements = {}) {
    return translate(props.translations, key, replacements);
}

function initial(value) {
    return String(value || '?').slice(0, 1).toUpperCase();
}

function participant(conversation) {
    return conversation?.participant || conversation?.user || conversation?.other_user || conversation?.recipient || {};
}

function conversationId(conversation) {
    return String(entityId(conversation));
}

function conversationRecipientId(conversation) {
    return String(entityId(participant(conversation)));
}

function conversationRecipientName(conversation) {
    return entityName(participant(conversation), conversationName(conversation));
}

function conversationPreview(conversation) {
    return conversation?.last_message?.body
        || conversation?.last_message?.content
        || conversation?.preview
        || t('start_conversation');
}

function unreadCount(conversation) {
    return Number(conversation?.unread_count ?? conversation?.unread ?? 0);
}

async function handleSearch() {
    isSearching.value = true;
    searchError.value = '';
    try {
        await searchUsers(searchQuery.value);
    } catch (error) {
        searchError.value = error.message;
    } finally {
        isSearching.value = false;
    }
}

async function chooseUser(user) {
    searchError.value = '';
    try {
        await selectConversation('', entityId(user), entityName(user, t('unnamed_user')));
    } catch (error) {
        searchError.value = error.message;
    }
}

async function chooseConversation(conversation) {
    sendError.value = '';
    try {
        await selectConversation(
            conversationId(conversation),
            conversationRecipientId(conversation),
            conversationRecipientName(conversation),
        );
    } catch (error) {
        sendError.value = error.message;
    }
}

async function handleSend() {
    const body = messageBody.value.trim();
    if (!body || (!activeId.value && !activeRecipientId.value)) return;
    isSending.value = true;
    sendError.value = '';
    try {
        await sendMessage(body);
        messageBody.value = '';
    } catch (error) {
        sendError.value = error.message;
    } finally {
        isSending.value = false;
    }
}
</script>

<template>
    <div class="communication-page messages-page" :class="{ 'embedded-communication-page': embedded }">
        <div class="communication-head">
            <div>
                <p class="eyebrow">{{ t('messages_eyebrow') }}</p>
                <h1>{{ t('messages_title') }}</h1>
                <p>{{ t('messages_intro') }}</p>
            </div>
            <nav class="communication-switcher" :aria-label="t('entry_label')">
                <a class="is-active" :href="messagesUrl" aria-current="page">{{ t('messages') }}</a>
                <a :href="groupsUrl">{{ t('groups') }}</a>
            </nav>
        </div>

        <div class="communication-layout">
            <aside class="communication-sidebar panel">
                <div class="panel-heading">
                    <div><p class="panel-kicker">{{ t('conversations_label') }}</p><h2>{{ t('conversations') }}</h2></div>
                    <span class="live-dot" :aria-label="t('live')"></span>
                </div>
                <form class="communication-search" method="get" :action="api.users" @submit.prevent="handleSearch">
                    <label class="sr-only" for="vue-message-user-search">{{ t('search_user') }}</label>
                    <input id="vue-message-user-search" v-model="searchQuery" name="q" :placeholder="t('search_placeholder')" autocomplete="off">
                    <button class="button button-small" type="submit" :disabled="isSearching">{{ t('search') }}</button>
                </form>
                <div class="search-results" aria-live="polite">
                    <p v-if="searchError" class="communication-error">{{ searchError }}</p>
                    <p v-else-if="isSearching" class="search-empty">{{ t('loading') }}</p>
                    <p v-else-if="searchResults.length === 0 && searchQuery" class="search-empty">{{ t('no_users') }}</p>
                    <button v-for="user in searchResults" :key="entityId(user)" type="button" class="search-result" @click="chooseUser(user)">
                        <span class="avatar avatar-small">{{ initial(entityName(user, t('unnamed_user'))) }}</span>
                        <span><strong>{{ entityName(user, t('unnamed_user')) }}</strong><small>{{ user.email || user.username || '' }}</small></span>
                    </button>
                </div>
                <div class="conversation-list" aria-live="polite">
                    <p v-if="!conversations.length" class="communication-empty">{{ t('empty_conversations') }}</p>
                    <button v-for="conversation in conversations" :key="conversationId(conversation)" type="button" class="conversation-item" :class="{ 'is-active': conversationId(conversation) === activeId }" @click="chooseConversation(conversation)">
                        <span class="avatar avatar-small">{{ initial(conversationRecipientName(conversation)) }}</span>
                        <span class="conversation-copy"><strong>{{ conversationRecipientName(conversation) }}</strong><small>{{ conversationPreview(conversation) }}</small></span>
                        <b v-if="unreadCount(conversation) > 0" class="unread-count">{{ unreadCount(conversation) }}</b>
                    </button>
                </div>
                <noscript><p class="no-script-note">{{ t('noscript_messages_search') }}</p></noscript>
            </aside>

            <section class="communication-main panel" aria-labelledby="vue-private-conversation-title">
                <div class="panel-heading communication-main-heading">
                    <div>
                        <p class="panel-kicker">{{ t('direct_chat') }}</p>
                        <h2 id="vue-private-conversation-title">{{ title }}</h2>
                        <p class="panel-subtitle">{{ meta }}</p>
                    </div>
                    <span class="connection-status" :data-state="statusState" role="status" aria-live="polite">{{ statusText }}</span>
                </div>

                <div class="message-list" aria-live="polite" :aria-label="t('message_content')">
                    <MessageBubble v-for="message in messages" :key="message.id" :message="message" :current-user-id="userId" :translations="translations" />
                    <p v-if="!messages.length" class="communication-empty">{{ activeId ? t('empty_messages') : t('choose_conversation_hint') }}</p>
                </div>

                <form class="message-compose" method="post" :action="api.store" @submit.prevent="handleSend">
                    <input type="hidden" name="conversation_id" :value="activeId">
                    <input type="hidden" name="recipient_id" :value="activeRecipientId">
                    <label class="sr-only" for="vue-private-message-body">{{ t('input_message') }}</label>
                    <textarea id="vue-private-message-body" v-model="messageBody" name="body" rows="3" :placeholder="t('message_placeholder')" required :disabled="isSending"></textarea>
                    <div class="compose-actions">
                        <span class="form-help">{{ sendError || help }}</span>
                        <button class="button button-primary" type="submit" :disabled="isSending || !messageBody.trim()">{{ t('send_message') }} <span aria-hidden="true">→</span></button>
                    </div>
                </form>
                <noscript><p class="no-script-note">{{ t('noscript_messages_send') }}</p></noscript>
            </section>
        </div>
    </div>
</template>

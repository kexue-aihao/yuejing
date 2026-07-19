import { onBeforeUnmount, onMounted, ref, toValue } from 'vue';
import {
    collection,
    createCommunicationApi,
    entityId,
    entityName,
    firstObject,
    messageCollection,
    messageText,
    translate,
} from './useCommunicationApi.js';

function normalizeId(value) {
    return String(value ?? '');
}

export function usePrivateMessages({ api: apiConfig, currentUserId, csrfToken = '', translations = {} } = {}) {
    const config = toValue(apiConfig) || {};
    const userId = normalizeId(toValue(currentUserId));
    const locale = toValue(translations) || {};
    const api = createCommunicationApi({ config, csrfToken: toValue(csrfToken), translations: locale });
    const conversations = ref([]);
    const searchResults = ref([]);
    const messages = ref([]);
    const activeId = ref('');
    const activeRecipientId = ref('');
    const activeRecipientName = ref('');
    const status = ref('loading');
    const statusState = ref('loading');
    const title = ref(translate(locale, 'choose_conversation'));
    const meta = ref(translate(locale, 'conversation_hint'));
    const help = ref(translate(locale, 'compose_message_hint'));
    let lastId = 0;
    let source = null;
    let pollTimer = null;
    let reconnectTimer = null;
    let mounted = false;

    function setStatus(nextStatus, state = '') {
        status.value = nextStatus;
        statusState.value = state;
    }

    function stopStream() {
        source?.close();
        source = null;
        window.clearInterval(pollTimer);
        window.clearTimeout(reconnectTimer);
        pollTimer = null;
        reconnectTimer = null;
    }

    function participant(conversation) {
        return conversation?.participant || conversation?.user || conversation?.other_user || conversation?.recipient || {};
    }

    function conversationName(conversation) {
        return entityName(participant(conversation), entityName(conversation, translate(locale, 'new_conversation')));
    }

    async function loadConversations() {
        try {
            conversations.value = collection(await api.request(config.index), ['conversations', 'items']);
            setStatus(translate(locale, 'connected'), 'connected');
        } catch (error) {
            setStatus(translate(locale, 'retrying'), 'retrying');
            throw error;
        }
    }

    async function searchUsers(query = '') {
        try {
            searchResults.value = collection(
                await api.request(`${config.users}?q=${encodeURIComponent(query)}`),
                ['users', 'items'],
            );
        } catch (error) {
            searchResults.value = [];
            throw error;
        }
    }

    async function markRead(id) {
        if (!id) return;
        try {
            await api.json(`${config.read}/${encodeURIComponent(id)}/read`, 'POST');
        } catch {
            // A read marker must not interrupt an otherwise usable conversation.
        }
    }

    async function loadConversation(id, silent = false) {
        try {
            const payload = await api.request(`${config.show}/${encodeURIComponent(id)}`);
            const nextMessages = messageCollection(payload);
            const conversation = firstObject(payload, ['conversation']);
            const maxId = nextMessages.reduce((max, message) => Math.max(max, Number(entityId(message, 0)) || 0), lastId);
            lastId = Math.max(lastId, maxId);
            messages.value = nextMessages;
            if (!silent) {
                title.value = activeRecipientName.value || conversationName(conversation);
                meta.value = translate(locale, 'private_visible');
            }
            await markRead(id);
        } catch (error) {
            if (!silent) messages.value = [];
            throw error;
        }
    }

    function appendStreamPayload(payload) {
        const payloadMessages = messageCollection(payload);
        const incoming = payloadMessages.length ? payloadMessages : (payload?.message ? [payload.message] : [payload]);
        const valid = incoming.filter((message) => message && typeof message === 'object' && messageText(message) !== '');
        if (!valid.length) return;
        valid.forEach((message) => {
            lastId = Math.max(lastId, Number(entityId(message, 0)) || 0);
        });
        messages.value = [...messages.value, ...valid];
        void markRead(activeId.value);
    }

    function openStream(id) {
        stopStream();
        if (!id || typeof EventSource === 'undefined') return;
        source = new EventSource(
            `${config.stream}/${encodeURIComponent(id)}/stream?after_id=${encodeURIComponent(lastId)}`,
            { withCredentials: true },
        );
        source.onopen = () => setStatus(translate(locale, 'connected'), 'connected');
        source.addEventListener('message', (event) => {
            try {
                appendStreamPayload(JSON.parse(event.data));
            } catch {
                // Ignore keep-alive or malformed events.
            }
        });
        source.onerror = () => {
            stopStream();
            setStatus(translate(locale, 'retrying'), 'retrying');
            pollTimer = window.setInterval(() => {
                void loadConversation(activeId.value, true).catch(() => {});
            }, 4000);
            reconnectTimer = window.setTimeout(() => openStream(activeId.value), 2500);
        };
    }

    async function selectConversation(id = '', recipientId = '', recipientName = '') {
        stopStream();
        activeId.value = normalizeId(id);
        lastId = 0;
        const existing = conversations.value.find((conversation) => normalizeId(entityId(conversation)) === activeId.value);
        const participantUser = participant(existing);
        activeRecipientId.value = normalizeId(recipientId || entityId(participantUser));
        activeRecipientName.value = recipientName || entityName(participantUser, '');
        title.value = activeRecipientName.value || translate(locale, 'loading_messages');
        meta.value = activeId.value ? translate(locale, 'loading_messages') : translate(locale, 'new_message');
        help.value = activeId.value ? translate(locale, 'send_read') : translate(locale, 'choose_user');
        searchResults.value = [];
        messages.value = [];
        if (!activeId.value) {
            setStatus(translate(locale, 'connected'), 'connected');
            return;
        }
        try {
            await loadConversation(activeId.value);
            openStream(activeId.value);
        } catch (error) {
            throw error;
        }
    }

    async function sendMessage(body) {
        const text = String(body || '').trim();
        if (!text || (!activeId.value && !activeRecipientId.value)) return null;
        const data = { body: text, recipient_id: activeRecipientId.value };
        const payload = await api.json(config.store, 'POST', data);
        const conversation = firstObject(payload, ['conversation']);
        const createdId = entityId(conversation, payload?.conversation_id || payload?.data?.conversation_id || activeId.value);
        await loadConversations();
        if (createdId) await selectConversation(createdId, activeRecipientId.value, activeRecipientName.value);
        return createdId;
    }

    onMounted(() => {
        mounted = true;
        setStatus(translate(locale, 'loading'), 'loading');
        void loadConversations().catch(() => {});
    });

    onBeforeUnmount(() => {
        mounted = false;
        stopStream();
    });

    return {
        conversations,
        searchResults,
        messages,
        activeId,
        activeRecipientId,
        activeRecipientName,
        status,
        statusState,
        title,
        meta,
        help,
        userId,
        mounted,
        conversationName,
        searchUsers,
        selectConversation,
        sendMessage,
    };
}

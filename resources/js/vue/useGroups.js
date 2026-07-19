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

export function useGroups({ api: apiConfig, currentUserId, csrfToken = '', translations = {} } = {}) {
    const config = toValue(apiConfig) || {};
    const userId = normalizeId(toValue(currentUserId));
    const locale = toValue(translations) || {};
    const api = createCommunicationApi({ config, csrfToken: toValue(csrfToken), translations: locale });
    const groups = ref([]);
    const users = ref([]);
    const members = ref([]);
    const messages = ref([]);
    const activeId = ref('');
    const title = ref(translate(locale, 'choose_group'));
    const meta = ref(translate(locale, 'group_hint'));
    const help = ref(translate(locale, 'compose_group_hint'));
    const status = ref('loading');
    const statusState = ref('loading');
    const listError = ref('');
    let lastId = 0;
    let source = null;
    let pollTimer = null;
    let reconnectTimer = null;

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

    async function loadUsers() {
        users.value = collection(await api.request(config.users), ['users', 'items']);
    }

    async function loadGroups() {
        try {
            groups.value = collection(await api.request(config.index), ['groups', 'items']);
            listError.value = '';
            setStatus(translate(locale, 'connected'), 'connected');
        } catch (error) {
            listError.value = error.message;
            setStatus(translate(locale, 'retrying'), 'retrying');
            throw error;
        }
    }

    async function markRead(id) {
        if (!id) return;
        try {
            await api.json(`${config.read}/${encodeURIComponent(id)}/read`, 'POST', { latest: true });
        } catch {
            // Read tracking must not interrupt group chat.
        }
    }

    async function loadGroup(id, silent = false) {
        const payload = await api.request(`${config.show}/${encodeURIComponent(id)}`);
        const group = firstObject(payload, ['group']);
        const nextMembers = collection(payload, ['members']);
        const nextMessages = messageCollection(payload);
        const maxId = nextMessages.reduce((max, message) => Math.max(max, Number(entityId(message, 0)) || 0), lastId);
        lastId = Math.max(lastId, maxId);
        groups.value = groups.value.map((item) => String(entityId(item)) === String(id) ? { ...item, ...group } : item);
        members.value = nextMembers;
        messages.value = nextMessages;
        if (!silent) {
            title.value = entityName(group, translate(locale, 'group_default'));
            meta.value = translate(locale, 'group_meta', { count: nextMembers.length });
        }
        await markRead(id);
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
                void loadGroup(activeId.value, true).catch(() => {});
            }, 4000);
            reconnectTimer = window.setTimeout(() => openStream(activeId.value), 2500);
        };
    }

    async function selectGroup(id = '') {
        stopStream();
        activeId.value = normalizeId(id);
        lastId = 0;
        if (!activeId.value) return;
        title.value = translate(locale, 'opening_group');
        meta.value = translate(locale, 'loading_group');
        help.value = translate(locale, 'send_group_read');
        messages.value = [];
        try {
            await loadGroup(activeId.value);
            openStream(activeId.value);
        } catch (error) {
            throw error;
        }
    }

    async function createGroup(name, memberIds = []) {
        const groupName = String(name || '').trim();
        if (!groupName) return null;
        const payload = await api.json(config.store, 'POST', {
            name: groupName,
            member_ids: memberIds,
        });
        const group = firstObject(payload, ['group']);
        const id = entityId(group, payload?.group_id || payload?.data?.group_id);
        await loadGroups();
        if (id) await selectGroup(id);
        return id;
    }

    async function addMember(userIdToAdd) {
        if (!activeId.value || !userIdToAdd) return;
        await api.json(`${config.addMember}/${encodeURIComponent(activeId.value)}/members`, 'POST', {
            user_id: userIdToAdd,
        });
        await loadGroup(activeId.value);
    }

    async function removeMember(userIdToRemove) {
        if (!activeId.value || !userIdToRemove) return;
        await api.json(`${config.removeMember}/${encodeURIComponent(activeId.value)}/members/${encodeURIComponent(userIdToRemove)}`, 'DELETE');
        await loadGroup(activeId.value);
    }

    async function sendMessage(body) {
        const text = String(body || '').trim();
        if (!activeId.value || !text) return null;
        const payload = await api.json(`${config.sendMessage}/${encodeURIComponent(activeId.value)}/messages`, 'POST', { body: text });
        await loadGroup(activeId.value, true);
        return payload;
    }

    onMounted(() => {
        setStatus(translate(locale, 'loading'), 'loading');
        void loadUsers().catch(() => {});
        void loadGroups().catch(() => {});
    });

    onBeforeUnmount(stopStream);

    return {
        groups,
        users,
        members,
        messages,
        activeId,
        title,
        meta,
        help,
        status,
        statusState,
        listError,
        userId,
        loadGroups,
        selectGroup,
        createGroup,
        addMember,
        removeMember,
        sendMessage,
    };
}

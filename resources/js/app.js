// ── Theme Manager ──
class ThemeManager {
    constructor() {
        this.STORAGE_KEY = 'yuejing-theme';
        this.validThemes = ['light', 'dark', 'eye-care', 'system'];
        this.mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
        this.currentTheme = this.getStoredTheme();
        this.applyTheme(this.currentTheme);
        this.initializeToggles();
        this.listenToSystemChanges();
    }

    getStoredTheme() {
        try {
            const storedTheme = localStorage.getItem(this.STORAGE_KEY);
            return this.validThemes.includes(storedTheme) ? storedTheme : 'system';
        } catch {
            return 'system';
        }
    }

    applyTheme(theme) {
        const nextTheme = this.validThemes.includes(theme) ? theme : 'system';
        if (nextTheme === 'system') {
            document.documentElement.removeAttribute('data-theme');
        } else {
            document.documentElement.setAttribute('data-theme', nextTheme);
        }

        try {
            localStorage.setItem(this.STORAGE_KEY, nextTheme);
        } catch {
            // The current DOM theme still applies without persistent storage.
        }

        this.currentTheme = nextTheme;
        this.updateToggleUI();
    }

    initializeToggles() {
        const buttons = [...document.querySelectorAll('[data-theme-action]')];
        buttons.forEach((button, index) => {
            button.addEventListener('click', () => {
                this.applyTheme(button.dataset.themeAction);
                button.focus();
            });
            button.addEventListener('keydown', (event) => {
                if (!['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Home', 'End'].includes(event.key)) return;
                event.preventDefault();
                const direction = event.key === 'ArrowLeft' || event.key === 'ArrowUp' ? -1 : 1;
                const nextIndex = event.key === 'Home' ? 0 : event.key === 'End' ? buttons.length - 1 : (index + direction + buttons.length) % buttons.length;
                const nextButton = buttons[nextIndex];
                this.applyTheme(nextButton.dataset.themeAction);
                nextButton.focus();
            });
        });
    }

    updateToggleUI() {
        document.querySelectorAll('[data-theme-action]').forEach((button) => {
            const isActive = button.dataset.themeAction === this.currentTheme;
            button.classList.toggle('active', isActive);
            button.setAttribute('aria-checked', String(isActive));
            button.tabIndex = isActive ? 0 : -1;
        });
    }

    listenToSystemChanges() {
        const update = () => {
            if (this.currentTheme === 'system') this.updateToggleUI();
        };
        if (typeof this.mediaQuery.addEventListener === 'function') {
            this.mediaQuery.addEventListener('change', update);
        } else {
            this.mediaQuery.addListener(update);
        }
    }
}

// ── Mobile Menu ──
function initMobileMenu() {
    const toggle = document.querySelector('[data-menu-toggle]');
    const menu = document.querySelector('[data-mobile-menu]');
    if (!toggle || !menu) return;

    const closeBtn = menu.querySelector('[data-menu-close]');
    const setMenuState = (isOpen) => {
        menu.toggleAttribute('hidden', !isOpen);
        menu.setAttribute('aria-hidden', String(!isOpen));
        menu.inert = !isOpen;
        toggle.setAttribute('aria-expanded', String(isOpen));
        toggle.setAttribute('aria-label', isOpen ? '关闭菜单' : '打开菜单');
        const toggleText = toggle.querySelector('.sr-only');
        if (toggleText) toggleText.textContent = isOpen ? '关闭菜单' : '打开菜单';
        if (isOpen) {
            requestAnimationFrame(() => closeBtn?.focus());
        } else {
            toggle.focus();
        }
    };
    const isOpen = () => !menu.hasAttribute('hidden');

    setMenuState(false);
    toggle.addEventListener('click', () => setMenuState(!isOpen()));
    closeBtn?.addEventListener('click', () => setMenuState(false));
    menu.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => setMenuState(false)));

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && isOpen()) {
            event.preventDefault();
            setMenuState(false);
        }
    });

    document.addEventListener('click', (event) => {
        if (isOpen() && !menu.contains(event.target) && !toggle.contains(event.target)) setMenuState(false);
    });
}

// ── Reader Controls ──
function initReaderControls() {
    const reader = document.querySelector('[data-reader-copy]');
    if (!reader) return;
    const sizeKey = 'yuejing-reader-size';
    const nightKey = 'yuejing-reader-night';
    const minSize = 14;
    const maxSize = 26;
    let size = Number.parseInt(reader.dataset.fontSize, 10) || Number.parseInt(getComputedStyle(reader).fontSize, 10) || 18;

    try {
        const storedSize = Number.parseInt(localStorage.getItem(sizeKey), 10);
        if (Number.isFinite(storedSize)) size = storedSize;
        if (localStorage.getItem(nightKey) === 'true') document.body.classList.add('reader-night');
    } catch {
        // Reader preferences remain available for the current page.
    }

    const status = document.querySelector('[data-reader-status]');
    const announce = (message) => {
        if (status) status.textContent = message;
    };
    const setSize = (nextSize) => {
        size = Math.min(maxSize, Math.max(minSize, Number(nextSize) || 18));
        reader.style.fontSize = `${size}px`;
        reader.dataset.fontSize = String(size);
        document.querySelectorAll('[data-reader-size]').forEach((button) => {
            const isDecrease = button.dataset.readerSize === 'decrease';
            button.disabled = isDecrease ? size <= minSize : size >= maxSize;
            button.setAttribute('aria-disabled', String(button.disabled));
        });
        announce(`字号 ${size} 像素`);
        try { localStorage.setItem(sizeKey, String(size)); } catch { /* Ignore unavailable storage. */ }
    };

    document.querySelectorAll('[data-reader-size]').forEach((button) => {
        button.addEventListener('click', () => setSize(size + (button.dataset.readerSize === 'increase' ? 2 : -2)));
    });
    setSize(size);

    const themeButton = document.querySelector('[data-reader-theme]');
    themeButton?.addEventListener('click', () => {
        document.body.classList.toggle('reader-night');
        const isNight = document.body.classList.contains('reader-night');
        themeButton.setAttribute('aria-pressed', String(isNight));
        announce(isNight ? '已开启阅读夜间模式' : '已关闭阅读夜间模式');
        try { localStorage.setItem(nightKey, String(isNight)); } catch { /* Ignore unavailable storage. */ }
    });
    themeButton?.setAttribute('aria-pressed', String(document.body.classList.contains('reader-night')));
}

// ── Toast Dismiss ──
function initToastDismiss() {
    document.querySelectorAll('[data-toast-dismiss]').forEach((btn) => {
        btn.addEventListener('click', () => btn.closest('.toast')?.remove());
    });
}

// ── Communication helpers ──
function isConfiguredApiUrl(value) {
    return typeof value === 'string'
        && value.trim() !== ''
        && !/(^|\/)undefined(?:\/|$)/.test(value);
}

function readApiConfig(element) {
    const requiredKeys = element.matches('[data-groups-app]')
        ? ['users', 'index', 'store', 'show', 'addMember', 'removeMember', 'sendMessage', 'read', 'stream']
        : ['users', 'index', 'store', 'show', 'read', 'stream'];

    try {
        const config = JSON.parse(element.dataset.api || '{}');
        if (!config || typeof config !== 'object' || !requiredKeys.every((key) => isConfiguredApiUrl(config[key]))) {
            throw new Error('消息接口配置缺失');
        }
        return config;
    } catch (error) {
        console.error('[communication] API 配置解析失败', error);
        return {};
    }
}

function csrfHeaders(json = false) {
    const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
    return {
        Accept: 'application/json',
        ...(json ? { 'Content-Type': 'application/json' } : {}),
        ...(token ? { 'X-CSRF-TOKEN': token } : {}),
    };
}

async function apiRequest(url, options = {}) {
    if (!isConfiguredApiUrl(url)) {
        throw new Error('消息接口配置缺失，无法发送请求。');
    }

    const response = await fetch(url, {
        credentials: 'same-origin',
        ...options,
        headers: {
            ...csrfHeaders(Boolean(options.body)),
            ...(options.headers || {}),
        },
    });
    const contentType = response.headers.get('content-type') || '';
    const payload = contentType.includes('application/json') ? await response.json() : await response.text();
    if (!response.ok) {
        const message = typeof payload === 'object' ? payload.message : payload;
        throw new Error(message || `请求失败（${response.status}）`);
    }
    return payload;
}

function apiJson(url, method, data = {}) {
    return apiRequest(url, { method, body: JSON.stringify(data) });
}

function collection(payload, keys = []) {
    if (Array.isArray(payload)) return payload;
    for (const key of keys) {
        if (Array.isArray(payload?.[key])) return payload[key];
        if (Array.isArray(payload?.data?.[key])) return payload.data[key];
    }
    if (Array.isArray(payload?.data)) return payload.data;
    return [];
}

function firstObject(payload, keys = []) {
    if (!payload || typeof payload !== 'object' || Array.isArray(payload)) return {};
    for (const key of keys) {
        if (payload[key] && typeof payload[key] === 'object' && !Array.isArray(payload[key])) return payload[key];
        if (payload.data?.[key] && typeof payload.data[key] === 'object' && !Array.isArray(payload.data[key])) return payload.data[key];
    }
    return payload.data && !Array.isArray(payload.data) ? payload.data : payload;
}

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function formatTime(value) {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return String(value);
    return new Intl.DateTimeFormat('zh-CN', { month: 'numeric', day: 'numeric', hour: '2-digit', minute: '2-digit' }).format(date);
}

function entityId(entity, fallback = '') {
    return entity?.id ?? entity?.conversation_id ?? entity?.group_id ?? entity?.user_id ?? fallback;
}

function entityName(entity, fallback = '未命名') {
    return entity?.name || entity?.title || entity?.username || entity?.display_name || fallback;
}

function messageCollection(payload) {
    return collection(payload, ['messages', 'items']);
}

function messageText(message) {
    return message?.body ?? message?.content ?? message?.message ?? '';
}

function messageSender(message) {
    return message?.sender || message?.from_user || message?.user || {};
}

function messageReceiver(message) {
    return message?.receiver || message?.to_user || message?.recipient || {};
}

function messageRead(message) {
    return Boolean(message?.is_read ?? message?.read ?? message?.read_at ?? message?.readAt);
}

function messageReadStats(message) {
    if (message?.read_stats) return message.read_stats;
    if (message?.readers) return message.readers;
    if (message?.read_count !== undefined || message?.read_by) {
        return { read: message.read_count ?? 0, readers: message.read_by ?? [] };
    }
    return null;
}

function renderMessage(message, currentUserId, group = false) {
    const sender = messageSender(message);
    const receiver = messageReceiver(message);
    const senderId = message?.sender_id ?? sender?.id;
    const own = String(senderId) === String(currentUserId);
    const senderName = own ? '我' : entityName(sender, message?.sender_name || '对方');
    const receiverName = entityName(receiver, message?.recipient_name || (group ? '群聊成员' : own ? '对方' : '我'));
    const read = messageRead(message);
    const stats = messageReadStats(message);
    const statsText = group && stats
        ? ` · 已读 ${Array.isArray(stats) ? stats.length : (stats.read ?? stats.count ?? stats.total ?? 0)}`
        : '';
    const status = group ? statsText : ` · ${read ? '已读' : '未读'}`;
    const id = entityId(message);

    return `<article class="message-bubble-row ${own ? 'is-own' : ''}" data-message-id="${escapeHtml(id)}">
        <div class="message-bubble">
            <div class="message-byline"><strong>${escapeHtml(senderName)}</strong><span>→ ${escapeHtml(receiverName)}</span></div>
            <p>${escapeHtml(messageText(message)).replaceAll('\n', '<br>')}</p>
            <div class="message-meta"><time datetime="${escapeHtml(message?.created_at || '')}">${escapeHtml(formatTime(message?.created_at || message?.createdAt))}</time><span>${status}</span></div>
        </div>
    </article>`;
}

function setPanelStatus(element, text, state = '') {
    if (!element) return;
    element.textContent = text;
    element.dataset.state = state;
}

function initPrivateMessages() {
    const app = document.querySelector('[data-messages-app]');
    if (!app) return;

    const api = readApiConfig(app);
    const currentUserId = app.dataset.currentUserId;
    const list = app.querySelector('[data-conversation-list]');
    const results = app.querySelector('[data-user-results]');
    const messageList = app.querySelector('[data-message-list]');
    const title = app.querySelector('[data-conversation-title]');
    const meta = app.querySelector('[data-conversation-meta]');
    const status = app.querySelector('[data-connection-status]');
    const sendForm = app.querySelector('[data-private-send-form]');
    const conversationId = sendForm?.querySelector('[data-conversation-id]');
    const recipientId = sendForm?.querySelector('[data-recipient-id]');
    const help = app.querySelector('[data-compose-help]');
    let conversations = [];
    let activeId = '';
    let activeRecipientId = '';
    let activeRecipientName = '';
    let lastId = 0;
    let source = null;
    let pollTimer = null;
    let reconnectTimer = null;

    const stopStream = () => {
        if (source) source.close();
        source = null;
        window.clearInterval(pollTimer);
        window.clearTimeout(reconnectTimer);
        pollTimer = null;
        reconnectTimer = null;
    };

    const setActiveForm = (id = '', userId = '', userName = '') => {
        activeId = String(id || '');
        activeRecipientId = String(userId || '');
        activeRecipientName = userName || '';
        if (conversationId) conversationId.value = activeId;
        if (recipientId) recipientId.value = activeRecipientId;
        if (sendForm) sendForm.querySelector('textarea')?.focus();
    };

    const renderConversations = () => {
        if (!conversations.length) {
            list.innerHTML = '<p class="communication-empty">还没有会话，搜索一位用户开始聊天。</p>';
            return;
        }
        list.innerHTML = conversations.map((conversation) => {
            const id = entityId(conversation);
            const other = conversation.other_user || conversation.user || conversation.participant || conversation.recipient || {};
            const name = entityName(other, entityName(conversation, '新会话'));
            const preview = conversation.last_message?.body || conversation.last_message?.content || conversation.preview || '开始一段新的对话';
            const unread = conversation.unread_count ?? conversation.unread ?? 0;
            return `<button class="conversation-item ${String(id) === activeId ? 'is-active' : ''}" type="button" data-conversation-id="${escapeHtml(id)}" data-recipient-id="${escapeHtml(entityId(other))}" data-recipient-name="${escapeHtml(name)}">
                <span class="avatar avatar-small">${escapeHtml(name.slice(0, 1))}</span><span class="conversation-copy"><strong>${escapeHtml(name)}</strong><small>${escapeHtml(preview)}</small></span>${Number(unread) > 0 ? `<b class="unread-count">${escapeHtml(unread)}</b>` : ''}
            </button>`;
        }).join('');
        list.querySelectorAll('[data-conversation-id]').forEach((button) => {
            button.addEventListener('click', () => selectConversation(button.dataset.conversationId, button.dataset.recipientId, button.dataset.recipientName));
        });
    };

    const renderUsers = (users) => {
        if (!users.length) {
            results.innerHTML = '<p class="search-empty">没有找到匹配的用户。</p>';
            return;
        }
        results.innerHTML = users.map((user) => {
            const id = entityId(user);
            const name = entityName(user, '未命名用户');
            return `<button type="button" class="search-result" data-user-id="${escapeHtml(id)}" data-user-name="${escapeHtml(name)}"><span class="avatar avatar-small">${escapeHtml(name.slice(0, 1))}</span><span><strong>${escapeHtml(name)}</strong><small>${escapeHtml(user.email || user.username || '')}</small></span></button>`;
        }).join('');
        results.querySelectorAll('[data-user-id]').forEach((button) => {
            button.addEventListener('click', () => {
                results.innerHTML = '';
                selectConversation('', button.dataset.userId, button.dataset.userName);
            });
        });
    };

    const loadConversations = async () => {
        try {
            conversations = collection(await apiRequest(api.index), ['conversations', 'items']);
            renderConversations();
        } catch (error) {
            list.innerHTML = `<p class="communication-error">${escapeHtml(error.message)}</p>`;
        }
    };

    const renderMessages = (messages) => {
        messageList.innerHTML = messages.length
            ? messages.map((message) => renderMessage(message, currentUserId, false, { otherName: activeRecipientName })).join('')
            : '<p class="communication-empty">这段对话还没有消息，写下第一句吧。</p>';
        messageList.scrollTop = messageList.scrollHeight;
    };

    const markRead = async (id) => {
        if (!id) return;
        try { await apiJson(`${api.read}/${encodeURIComponent(id)}/read`, 'POST'); } catch { /* A read marker should not interrupt the chat. */ }
    };

    const loadConversation = async (id, silent = false) => {
        if (!id) return;
        try {
            const payload = await apiRequest(`${api.show}/${encodeURIComponent(id)}`);
            const messages = messageCollection(payload);
            const conversation = firstObject(payload, ['conversation']);
            const maxId = messages.reduce((max, message) => Math.max(max, Number(entityId(message, 0)) || 0), lastId);
            lastId = Math.max(lastId, maxId);
            if (!silent) {
                const other = conversation.other_user || conversation.user || conversation.participant || {};
                title.textContent = entityName(other, entityName(conversation, '私信会话'));
                meta.textContent = conversation.email || other.email || '私信内容仅对会话双方可见。';
            }
            renderMessages(messages);
            await markRead(id);
        } catch (error) {
            if (!silent) messageList.innerHTML = `<p class="communication-error">${escapeHtml(error.message)}</p>`;
        }
    };

    const appendStreamPayload = (payload) => {
        const messages = messageCollection(payload).length ? messageCollection(payload) : (payload?.message ? [payload.message] : [payload]);
        const valid = messages.filter((message) => message && typeof message === 'object' && messageText(message) !== '');
        if (!valid.length) return;
        valid.forEach((message) => { lastId = Math.max(lastId, Number(entityId(message, 0)) || 0); });
        valid.forEach((message) => messageList.insertAdjacentHTML('beforeend', renderMessage(message, currentUserId, false, { otherName: activeRecipientName })));
        messageList.scrollTop = messageList.scrollHeight;
        markRead(activeId);
    };

    const openStream = (id) => {
        stopStream();
        if (!id) return;
        const url = `${api.stream}/${encodeURIComponent(id)}/stream?after_id=${encodeURIComponent(lastId)}`;
        source = new EventSource(url, { withCredentials: true });
        setPanelStatus(status, '实时连接中', 'connected');
        source.onmessage = (event) => {
            try { appendStreamPayload(JSON.parse(event.data)); } catch { /* Ignore keep-alive or malformed events. */ }
        };
        source.onerror = () => {
            stopStream();
            setPanelStatus(status, '连接暂时中断，正在重试', 'retrying');
            pollTimer = window.setInterval(() => loadConversation(activeId, true), 4000);
            reconnectTimer = window.setTimeout(() => openStream(activeId), 2500);
        };
    };

    async function selectConversation(id, userId = '', userName = '') {
        stopStream();
        lastId = 0;
        const existing = conversations.find((conversation) => String(entityId(conversation)) === String(id));
        const participant = existing?.participant || existing?.user || {};
        const resolvedName = userName || entityName(participant, '');
        setActiveForm(id, userId || entityId(participant), resolvedName);
        title.textContent = resolvedName || '正在打开会话…';
        meta.textContent = id ? '正在加载消息…' : '发送第一条消息后会创建会话。';
        help.textContent = id ? '消息发送后会自动标记为已读。' : '请选择用户后发送消息。';
        renderConversations();
        if (!id) {
            messageList.innerHTML = '<p class="communication-empty">这是新的会话，写下第一句吧。</p>';
            setPanelStatus(status, '等待发送', 'idle');
            return;
        }
        await loadConversation(id);
        openStream(id);
    }

    app.querySelector('[data-user-search-form]')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const query = new FormData(event.currentTarget).get('q') || '';
        try {
            renderUsers(collection(await apiRequest(`${api.users}?q=${encodeURIComponent(query)}`), ['users', 'items']));
        } catch (error) {
            results.innerHTML = `<p class="communication-error">${escapeHtml(error.message)}</p>`;
        }
    });

    sendForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = event.currentTarget;
        const body = form.querySelector('textarea')?.value.trim();
        if (!body || (!activeId && !activeRecipientId)) return;
        const data = { body };
        if (activeId) data.conversation_id = activeId;
        if (activeRecipientId) data.recipient_id = activeRecipientId;
        const button = form.querySelector('button[type="submit"]');
        button.disabled = true;
        try {
            const payload = await apiJson(api.store, 'POST', data);
            form.reset();
            const createdConversation = firstObject(payload, ['conversation']);
            const createdId = entityId(createdConversation, payload?.conversation_id || payload?.data?.conversation_id || activeId);
            await loadConversations();
            if (createdId) await selectConversation(createdId, activeRecipientId, activeRecipientName);
        } catch (error) {
            help.textContent = error.message;
        } finally {
            button.disabled = false;
        }
    });

    setPanelStatus(status, '加载中', 'loading');
    loadConversations();
}

function initGroups() {
    const app = document.querySelector('[data-groups-app]');
    if (!app) return;

    const api = readApiConfig(app);
    const currentUserId = app.dataset.currentUserId;
    const groupList = app.querySelector('[data-group-list]');
    const groupTitle = app.querySelector('[data-group-title]');
    const groupMeta = app.querySelector('[data-group-meta]');
    const status = app.querySelector('[data-group-connection-status]');
    const memberList = app.querySelector('[data-member-list]');
    const memberCount = app.querySelector('[data-member-count]');
    const memberSelect = app.querySelector('[data-member-select]');
    const messageList = app.querySelector('[data-group-message-list]');
    const sendForm = app.querySelector('[data-group-send-form]');
    const sendHelp = app.querySelector('[data-group-compose-help]');
    let groups = [];
    let users = [];
    let activeId = '';
    let lastId = 0;
    let source = null;
    let pollTimer = null;
    let reconnectTimer = null;

    const stopStream = () => {
        source?.close();
        source = null;
        window.clearInterval(pollTimer);
        window.clearTimeout(reconnectTimer);
        pollTimer = null;
        reconnectTimer = null;
    };

    const renderGroups = () => {
        if (!groups.length) {
            groupList.innerHTML = '<p class="communication-empty">还没有群聊，创建一个共读小组吧。</p>';
            return;
        }
        groupList.innerHTML = groups.map((group) => {
            const id = entityId(group);
            const name = entityName(group, '未命名群聊');
            const members = group.member_count ?? group.members_count ?? group.members?.length ?? '';
            return `<button type="button" class="conversation-item ${String(id) === activeId ? 'is-active' : ''}" data-group-id="${escapeHtml(id)}"><span class="avatar avatar-small">${escapeHtml(name.slice(0, 1))}</span><span class="conversation-copy"><strong>${escapeHtml(name)}</strong><small>${members === '' ? '交流群' : `${escapeHtml(members)} 位成员`}</small></span></button>`;
        }).join('');
        groupList.querySelectorAll('[data-group-id]').forEach((button) => button.addEventListener('click', () => selectGroup(button.dataset.groupId)));
    };

    const renderUserChoices = () => {
        const choices = users.filter((user) => String(entityId(user)) !== String(currentUserId));
        const optionHtml = choices.map((user) => `<option value="${escapeHtml(entityId(user))}">${escapeHtml(entityName(user, '未命名用户'))}</option>`).join('');
        memberSelect.innerHTML = `<option value="">选择成员</option>${optionHtml}`;
        const checklist = app.querySelector('[data-user-checklist]');
        checklist.innerHTML = choices.length ? choices.map((user) => `<label class="check-option"><input type="checkbox" name="member_ids[]" value="${escapeHtml(entityId(user))}"><span>${escapeHtml(entityName(user, '未命名用户'))}</span></label>`).join('') : '<p class="form-help">没有可邀请的用户。</p>';
    };

    const loadUsers = async () => {
        try {
            users = collection(await apiRequest(api.users), ['users', 'items']);
            renderUserChoices();
        } catch { /* Group loading remains useful if the user directory is unavailable. */ }
    };

    const renderMembers = (members) => {
        memberCount.textContent = `${members.length} 人`;
        memberList.innerHTML = members.length ? members.map((member) => {
            const user = member.user || member;
            const id = entityId(user, member.user_id);
            const name = entityName(user, '未命名用户');
            const remove = String(id) === String(currentUserId) ? '' : `<button type="button" title="移除成员" data-remove-member="${escapeHtml(id)}">×</button>`;
            return `<span class="member-chip"><span class="avatar avatar-tiny">${escapeHtml(name.slice(0, 1))}</span>${escapeHtml(name)}${remove}</span>`;
        }).join('') : '<span class="muted">暂无成员信息。</span>';
        memberList.querySelectorAll('[data-remove-member]').forEach((button) => button.addEventListener('click', () => removeMember(button.dataset.removeMember)));
    };

    const renderMessages = (messages) => {
        messageList.innerHTML = messages.length
            ? messages.map((message) => renderMessage(message, currentUserId, true)).join('')
            : '<p class="communication-empty">这个群聊还没有消息，开始聊聊吧。</p>';
        messageList.scrollTop = messageList.scrollHeight;
    };

    const markRead = async (id) => {
        if (!id) return;
        try { await apiJson(`${api.read}/${encodeURIComponent(id)}/read`, 'POST', { latest: true }); } catch { /* Keep the chat usable if read tracking is unavailable. */ }
    };

    const loadGroup = async (id, silent = false) => {
        try {
            const payload = await apiRequest(`${api.show}/${encodeURIComponent(id)}`);
            const group = firstObject(payload, ['group']);
            const members = collection(payload, ['members']);
            const messages = messageCollection(payload);
            const maxId = messages.reduce((max, message) => Math.max(max, Number(entityId(message, 0)) || 0), lastId);
            lastId = Math.max(lastId, maxId);
            if (!silent) {
                groupTitle.textContent = entityName(group, '交流群');
                groupMeta.textContent = `${members.length} 位成员 · 消息已读状态会随成员更新`;
                renderMembers(members);
            }
            renderMessages(messages);
            await markRead(id);
        } catch (error) {
            if (!silent) messageList.innerHTML = `<p class="communication-error">${escapeHtml(error.message)}</p>`;
        }
    };

    const appendStreamPayload = (payload) => {
        const messages = messageCollection(payload).length ? messageCollection(payload) : (payload?.message ? [payload.message] : [payload]);
        const valid = messages.filter((message) => message && typeof message === 'object' && messageText(message) !== '');
        if (!valid.length) return;
        valid.forEach((message) => { lastId = Math.max(lastId, Number(entityId(message, 0)) || 0); });
        valid.forEach((message) => messageList.insertAdjacentHTML('beforeend', renderMessage(message, currentUserId, true)));
        messageList.scrollTop = messageList.scrollHeight;
        markRead(activeId);
    };

    const openStream = (id) => {
        stopStream();
        if (!id) return;
        source = new EventSource(`${api.stream}/${encodeURIComponent(id)}/stream?after_id=${encodeURIComponent(lastId)}`, { withCredentials: true });
        setPanelStatus(status, '实时连接中', 'connected');
        source.onmessage = (event) => {
            try { appendStreamPayload(JSON.parse(event.data)); } catch { /* Ignore keep-alive or malformed events. */ }
        };
        source.onerror = () => {
            stopStream();
            setPanelStatus(status, '连接暂时中断，正在重试', 'retrying');
            pollTimer = window.setInterval(() => loadGroup(activeId, true), 4000);
            reconnectTimer = window.setTimeout(() => openStream(activeId), 2500);
        };
    };

    async function selectGroup(id) {
        stopStream();
        activeId = String(id || '');
        lastId = 0;
        renderGroups();
        groupTitle.textContent = '正在打开群聊…';
        groupMeta.textContent = '正在加载成员和消息…';
        sendHelp.textContent = '消息发送后会自动标记为已读。';
        await loadGroup(activeId);
        openStream(activeId);
    }

    const removeMember = async (userId) => {
        if (!activeId || !userId) return;
        try {
            await apiJson(`${api.removeMember}/${encodeURIComponent(activeId)}/members/${encodeURIComponent(userId)}`, 'DELETE');
            await loadGroup(activeId);
        } catch (error) { sendHelp.textContent = error.message; }
    };

    app.querySelector('[data-group-create-form]')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = event.currentTarget;
        const data = { name: new FormData(form).get('name'), member_ids: [...form.querySelectorAll('input[name="member_ids[]"]:checked')].map((input) => input.value) };
        try {
            const payload = await apiJson(api.store, 'POST', data);
            const group = firstObject(payload, ['group']);
            const id = entityId(group, payload?.group_id || payload?.data?.group_id);
            form.reset();
            await loadGroups();
            if (id) await selectGroup(id);
        } catch (error) { form.querySelector('.button')?.setAttribute('title', error.message); }
    });

    app.querySelector('[data-member-add-form]')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const userId = memberSelect.value;
        if (!activeId || !userId) return;
        try { await apiJson(`${api.addMember}/${encodeURIComponent(activeId)}/members`, 'POST', { user_id: userId }); memberSelect.value = ''; await loadGroup(activeId); } catch (error) { sendHelp.textContent = error.message; }
    });

    sendForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const body = sendForm.querySelector('textarea')?.value.trim();
        if (!activeId || !body) return;
        const button = sendForm.querySelector('button[type="submit"]');
        button.disabled = true;
        try { await apiJson(`${api.sendMessage}/${encodeURIComponent(activeId)}/messages`, 'POST', { body }); sendForm.reset(); await loadGroup(activeId, true); } catch (error) { sendHelp.textContent = error.message; } finally { button.disabled = false; }
    });

    async function loadGroups() {
        try { groups = collection(await apiRequest(api.index), ['groups', 'items']); renderGroups(); } catch (error) { groupList.innerHTML = `<p class="communication-error">${escapeHtml(error.message)}</p>`; }
    }

    setPanelStatus(status, '加载中', 'loading');
    loadUsers();
    loadGroups();
}

// ── Bootstrap ──
document.addEventListener('DOMContentLoaded', () => {
    new ThemeManager();
    initMobileMenu();
    initReaderControls();
    initToastDismiss();
    initPrivateMessages();
    initGroups();
});

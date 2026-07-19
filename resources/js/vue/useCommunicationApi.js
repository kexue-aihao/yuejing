function isConfiguredApiUrl(value) {
    return typeof value === 'string'
        && value.trim() !== ''
        && !/(^|\/)undefined(?:\/|$)/.test(value);
}

function replaceTokens(value, replacements = {}) {
    return Object.entries(replacements).reduce(
        (text, [key, replacement]) => text.replaceAll(`:${key}`, String(replacement)),
        String(value ?? ''),
    );
}

export function translate(translations, key, replacements = {}) {
    return replaceTokens(translations?.[key] ?? key, replacements);
}

export function collection(payload, keys = []) {
    if (Array.isArray(payload)) return payload;
    for (const key of keys) {
        if (Array.isArray(payload?.[key])) return payload[key];
        if (Array.isArray(payload?.data?.[key])) return payload.data[key];
    }
    if (Array.isArray(payload?.data)) return payload.data;
    return [];
}

export function firstObject(payload, keys = []) {
    if (!payload || typeof payload !== 'object' || Array.isArray(payload)) return {};
    for (const key of keys) {
        if (payload[key] && typeof payload[key] === 'object' && !Array.isArray(payload[key])) return payload[key];
        if (payload.data?.[key] && typeof payload.data[key] === 'object' && !Array.isArray(payload.data[key])) return payload.data[key];
    }
    return payload.data && !Array.isArray(payload.data) ? payload.data : payload;
}

export function entityId(entity, fallback = '') {
    return entity?.id ?? entity?.conversation_id ?? entity?.group_id ?? entity?.user_id ?? fallback;
}

export function entityName(entity, fallback = '') {
    return entity?.name || entity?.title || entity?.username || entity?.display_name || fallback;
}

export function messageCollection(payload) {
    return collection(payload, ['messages', 'items']);
}

export function messageText(message) {
    return message?.body ?? message?.content ?? message?.message ?? '';
}

export function messageRead(message) {
    return Boolean(message?.is_read ?? message?.read ?? message?.read_at ?? message?.readAt);
}

export function messageReadStats(message) {
    if (message?.read_stats) return message.read_stats;
    if (message?.readers) return message.readers;
    if (message?.read_count !== undefined || message?.read_by) {
        return { read: message.read_count ?? 0, readers: message.read_by ?? [] };
    }
    return null;
}

export function createCommunicationApi({ config = {}, csrfToken = '', translations = {} } = {}) {
    async function request(url, options = {}) {
        if (!isConfiguredApiUrl(url)) {
            throw new Error(translate(translations, 'api_request_missing'));
        }

        const headers = {
            Accept: 'application/json',
            ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
            ...(options.headers || {}),
        };
        if (typeof options.body === 'string') headers['Content-Type'] = 'application/json';

        const response = await fetch(url, {
            credentials: 'same-origin',
            ...options,
            headers,
        });
        const contentType = response.headers.get('content-type') || '';
        const payload = contentType.includes('application/json')
            ? await response.json()
            : await response.text();

        if (!response.ok) {
            const message = typeof payload === 'object'
                ? payload?.message || Object.values(payload?.errors || {}).flat()[0]
                : payload;
            throw new Error(message || translate(translations, 'request_failed', { status: response.status }));
        }

        return payload;
    }

    function json(url, method, data = {}) {
        return request(url, { method, body: JSON.stringify(data) });
    }

    return { config, request, json };
}

export function formatCommunicationTime(value) {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return String(value);

    return new Intl.DateTimeFormat(document.documentElement.lang || 'en', {
        month: 'numeric',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);
}

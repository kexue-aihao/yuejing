import { computed, onBeforeUnmount, onMounted, shallowRef, toValue } from 'vue';

const DEFAULT_LIMIT = 6;
const DEFAULT_POLL_DELAY = 60_000;

function normalizeItems(items) {
    return Array.isArray(items) ? items : [];
}

function pollDelay(payload, fallback) {
    const seconds = Number(payload?.next_poll_after);

    return Number.isFinite(seconds) && seconds > 0 ? seconds * 1000 : fallback;
}

export function useRecommendations({
    apiUrl,
    initialItems = [],
    limit = DEFAULT_LIMIT,
    fallbackPollDelay = DEFAULT_POLL_DELAY,
} = {}) {
    const items = shallowRef(normalizeItems(toValue(initialItems)));
    const status = shallowRef('loading');
    const isLoading = computed(() => status.value === 'loading');
    let pollTimer = null;
    let requestController = null;
    let mounted = false;

    function clearPollTimer() {
        if (pollTimer !== null) {
            window.clearTimeout(pollTimer);
            pollTimer = null;
        }
    }

    function schedulePoll(delay) {
        clearPollTimer();
        if (!mounted) return;

        pollTimer = window.setTimeout(() => {
            pollTimer = null;
            load();
        }, Math.max(0, Number(delay) || fallbackPollDelay));
    }

    async function load() {
        if (!mounted) return;

        const configuredUrl = String(toValue(apiUrl) ?? '').trim();
        if (!configuredUrl) {
            status.value = 'retrying';
            schedulePoll(fallbackPollDelay);
            return;
        }

        requestController?.abort();
        requestController = new AbortController();

        try {
            const url = new URL(configuredUrl, window.location.href);
            url.searchParams.set('limit', String(Number(toValue(limit)) || DEFAULT_LIMIT));

            const response = await fetch(url, {
                credentials: 'same-origin',
                cache: 'no-store',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                signal: requestController.signal,
            });

            if (!response.ok) throw new Error(`HTTP ${response.status}`);

            const payload = await response.json();
            if (!mounted) return;

            items.value = normalizeItems(payload?.data);
            status.value = 'connected';
            schedulePoll(pollDelay(payload, fallbackPollDelay));
        } catch (error) {
            if (!mounted || error?.name === 'AbortError') return;

            status.value = 'retrying';
            schedulePoll(fallbackPollDelay);
        }
    }

    function reload() {
        clearPollTimer();
        status.value = 'loading';
        return load();
    }

    onMounted(() => {
        mounted = true;
        load();
    });

    onBeforeUnmount(() => {
        mounted = false;
        clearPollTimer();
        requestController?.abort();
        requestController = null;
    });

    return {
        items,
        status,
        isLoading,
        load,
        reload,
    };
}

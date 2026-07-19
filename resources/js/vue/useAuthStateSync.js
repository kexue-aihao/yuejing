import { onBeforeUnmount, onMounted } from 'vue';

function refreshKeyFor(pathname) {
    return `yuejing-auth-refresh:${pathname}`;
}

function readRefreshState(key) {
    try {
        return window.sessionStorage.getItem(key);
    } catch {
        return null;
    }
}

function writeRefreshState(key, state) {
    try {
        window.sessionStorage.setItem(key, state);
    } catch {
        // The unique URL still prevents a normal cache hit.
    }
}

function clearRefreshState(key) {
    try {
        window.sessionStorage.removeItem(key);
    } catch {
        // Continue when session storage is unavailable.
    }
}

export function useAuthStateSync() {
    let controller = null;
    let active = true;

    async function refresh() {
        const serverState = document.body?.dataset.serverAuthState;
        if (!['authenticated', 'guest'].includes(serverState)) return;

        const refreshKey = refreshKeyFor(window.location.pathname);
        try {
            const response = await fetch('/auth/me', {
                credentials: 'same-origin',
                cache: 'no-store',
                redirect: 'manual',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                signal: controller?.signal,
            });
            if (!active) return;

            const actualState = response.ok ? 'authenticated' : 'guest';
            if (actualState === serverState) {
                clearRefreshState(refreshKey);
                return;
            }

            if (readRefreshState(refreshKey) === serverState) return;
            writeRefreshState(refreshKey, serverState);
            const refreshUrl = new URL(window.location.href);
            refreshUrl.searchParams.set('_auth_refresh', String(Date.now()));
            window.location.replace(refreshUrl.toString());
        } catch {
            // Authentication refresh is defensive; server-rendered navigation remains usable.
        }
    }

    onMounted(() => {
        controller = typeof AbortController === 'function' ? new AbortController() : null;
        void refresh();
    });

    onBeforeUnmount(() => {
        active = false;
        controller?.abort();
        controller = null;
    });

    return { refresh };
}

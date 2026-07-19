import { onBeforeUnmount, onMounted } from 'vue';

export function useTimezoneLocale() {
    let controller = null;
    let active = true;

    async function sync() {
        const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
        const token = document.querySelector('meta[name="csrf-token"]')?.content;
        if (!timezone || !token) return;

        try {
            const response = await fetch('/language/timezone', {
                method: 'POST',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': token,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ timezone }),
                signal: controller?.signal,
            });
            if (!active || !response.ok) return;

            const payload = await response.json();
            if (payload?.changed && payload.locale) window.location.reload();
        } catch {
            // Locale detection is an enhancement; page rendering still works.
        }
    }

    onMounted(() => {
        controller = typeof AbortController === 'function' ? new AbortController() : null;
        void sync();
    });

    onBeforeUnmount(() => {
        active = false;
        controller?.abort();
        controller = null;
    });

    return { sync };
}


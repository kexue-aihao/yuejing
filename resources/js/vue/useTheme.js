import { computed, onMounted, onUnmounted, readonly, shallowRef } from 'vue';

export const THEME_VALUES = Object.freeze([
    'light',
    'dark',
    'eye-care',
    'system',
]);

export const THEME_STORAGE_KEY = 'yuejing-theme';

function isValidTheme(value) {
    return THEME_VALUES.includes(value);
}

function normalizeTheme(value, fallback = 'system') {
    return isValidTheme(value) ? value : fallback;
}

function readStoredTheme(storageKey) {
    if (typeof window === 'undefined') return null;

    try {
        const storedTheme = window.localStorage.getItem(storageKey);
        return isValidTheme(storedTheme) ? storedTheme : null;
    } catch {
        return null;
    }
}

function readSystemPreference() {
    if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') {
        return false;
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches;
}

export function useTheme(options = {}) {
    const storageKey = options.storageKey || THEME_STORAGE_KEY;
    const defaultTheme = normalizeTheme(options.defaultTheme);
    const initialTheme = normalizeTheme(
        options.initialTheme || readStoredTheme(storageKey) || defaultTheme,
        defaultTheme,
    );
    const theme = shallowRef(initialTheme);
    const systemIsDark = shallowRef(readSystemPreference());

    let mediaQuery = null;

    const resolvedTheme = computed(() => (
        theme.value === 'system'
            ? (systemIsDark.value ? 'dark' : 'light')
            : theme.value
    ));

    function persistTheme(nextTheme) {
        if (typeof window === 'undefined') return;

        try {
            window.localStorage.setItem(storageKey, nextTheme);
        } catch {
            // The active DOM theme remains usable when storage is unavailable.
        }
    }

    function applyTheme(nextTheme, persist = true) {
        const normalizedTheme = normalizeTheme(nextTheme, defaultTheme);

        if (typeof document !== 'undefined') {
            if (normalizedTheme === 'system') {
                document.documentElement.removeAttribute('data-theme');
            } else {
                document.documentElement.setAttribute('data-theme', normalizedTheme);
            }
        }

        theme.value = normalizedTheme;
        if (persist) persistTheme(normalizedTheme);

        return normalizedTheme;
    }

    function setTheme(nextTheme) {
        return applyTheme(nextTheme);
    }

    function handleSystemPreferenceChange(event) {
        systemIsDark.value = Boolean(event.matches);

        if (theme.value === 'system') {
            applyTheme('system', false);
        }
    }

    function addMediaListener() {
        if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') return;

        mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
        systemIsDark.value = mediaQuery.matches;

        if (typeof mediaQuery.addEventListener === 'function') {
            mediaQuery.addEventListener('change', handleSystemPreferenceChange);
        } else if (typeof mediaQuery.addListener === 'function') {
            mediaQuery.addListener(handleSystemPreferenceChange);
        }
    }

    function removeMediaListener() {
        if (!mediaQuery) return;

        if (typeof mediaQuery.removeEventListener === 'function') {
            mediaQuery.removeEventListener('change', handleSystemPreferenceChange);
        } else if (typeof mediaQuery.removeListener === 'function') {
            mediaQuery.removeListener(handleSystemPreferenceChange);
        }

        mediaQuery = null;
    }

    onMounted(() => {
        applyTheme(theme.value);
        addMediaListener();
    });

    onUnmounted(removeMediaListener);

    return {
        theme: readonly(theme),
        resolvedTheme,
        systemIsDark: readonly(systemIsDark),
        setTheme,
    };
}

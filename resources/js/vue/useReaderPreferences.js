import { onMounted, ref, toValue } from 'vue';
import { translate } from './useCommunicationApi.js';

const MIN_SIZE = 14;
const MAX_SIZE = 26;
const DEFAULT_SIZE = 18;
const SIZE_KEY = 'yuejing-reader-size';
const NIGHT_KEY = 'yuejing-reader-night';

export function useReaderPreferences({ translations = {} } = {}) {
    const locale = toValue(translations) || {};
    const size = ref(DEFAULT_SIZE);
    const isNight = ref(false);
    const reader = ref(null);

    function announce(message) {
        const status = document.querySelector('[data-reader-status]');
        if (status) status.textContent = message;
    }

    function persist(key, value) {
        try {
            localStorage.setItem(key, String(value));
        } catch {
            // The current page preference remains active without storage.
        }
    }

    function setSize(nextSize, notify = true) {
        size.value = Math.min(MAX_SIZE, Math.max(MIN_SIZE, Number(nextSize) || DEFAULT_SIZE));
        if (reader.value) {
            reader.value.style.fontSize = `${size.value}px`;
            reader.value.dataset.fontSize = String(size.value);
        }
        if (notify) announce(translate(locale, 'font_size', { size: size.value }));
        persist(SIZE_KEY, size.value);
    }

    function toggleNight() {
        isNight.value = !isNight.value;
        document.body.classList.toggle('reader-night', isNight.value);
        announce(translate(locale, isNight.value ? 'night_on' : 'night_off'));
        persist(NIGHT_KEY, isNight.value);
    }

    onMounted(() => {
        reader.value = document.querySelector('[data-reader-copy]');
        let storedSize = Number.NaN;
        let storedNight = false;
        try {
            storedSize = Number.parseInt(localStorage.getItem(SIZE_KEY), 10);
            storedNight = localStorage.getItem(NIGHT_KEY) === 'true';
        } catch {
            // Use the server-rendered defaults when storage is unavailable.
        }
        const computedSize = Number.parseInt(reader.value?.dataset.fontSize, 10) || DEFAULT_SIZE;
        setSize(Number.isFinite(storedSize) ? storedSize : computedSize);
        isNight.value = storedNight;
        document.body.classList.toggle('reader-night', isNight.value);
    });

    return {
        size,
        isNight,
        setSize,
        toggleNight,
        minSize: MIN_SIZE,
        maxSize: MAX_SIZE,
    };
}

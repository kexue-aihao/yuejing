<script setup>
import { computed } from 'vue';
import { translate } from './useCommunicationApi.js';
import { useReaderPreferences } from './useReaderPreferences.js';

const props = defineProps({
    translations: {
        type: Object,
        default: () => ({}),
    },
});

const { size, isNight, setSize, toggleNight, minSize, maxSize } = useReaderPreferences({
    translations: () => props.translations,
});

const decreaseDisabled = computed(() => size.value <= minSize);
const increaseDisabled = computed(() => size.value >= maxSize);

function t(key, replacements = {}) {
    return translate(props.translations, key, replacements);
}
</script>

<template>
    <button type="button" data-reader-size="decrease" :aria-label="t('decrease_font')" :aria-disabled="decreaseDisabled" :disabled="decreaseDisabled" @click="setSize(size - 2)">A−</button>
    <button class="reader-size" type="button" data-reader-theme :aria-pressed="isNight" :aria-label="t('toggle_night')" @click="toggleNight">{{ t('settings_label') }}</button>
    <button type="button" data-reader-size="increase" :aria-label="t('increase_font')" :aria-disabled="increaseDisabled" :disabled="increaseDisabled" @click="setSize(size + 2)">A＋</button>
</template>

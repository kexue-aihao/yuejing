<script setup>
import { computed } from 'vue';
import { Minus, Moon, Plus, Sun } from 'lucide-vue-next';
import UiButton from './ui/UiButton.vue';
import UiIconButton from './ui/UiIconButton.vue';
import { translate } from './useCommunicationApi.js';
import { useReaderPreferences } from './useReaderPreferences.js';

const props = defineProps({
    translations: { type: Object, default: () => ({}) },
});

const { size, isNight, setSize, toggleNight, minSize, maxSize } = useReaderPreferences({
    translations: () => props.translations,
});

const decreaseDisabled = computed(() => size.value <= minSize);
const increaseDisabled = computed(() => size.value >= maxSize);
const themeIcon = computed(() => isNight.value ? Sun : Moon);

function t(key, replacements = {}) {
    return translate(props.translations, key, replacements);
}
</script>

<template>
    <UiIconButton
        type="button"
        size="sm"
        :icon="Minus"
        :label="t('decrease_font')"
        :disabled="decreaseDisabled"
        data-reader-size="decrease"
        @click="setSize(size - 2)"
    />
    <UiButton
        variant="ghost"
        size="sm"
        type="button"
        :icon="themeIcon"
        :aria-pressed="isNight"
        data-reader-theme
        @click="toggleNight"
    >{{ t('settings_label') }}</UiButton>
    <UiIconButton
        type="button"
        size="sm"
        :icon="Plus"
        :label="t('increase_font')"
        :disabled="increaseDisabled"
        data-reader-size="increase"
        @click="setSize(size + 2)"
    />
</template>

<script setup>
import { computed, onBeforeUpdate, shallowRef, watch } from 'vue';
import { THEME_VALUES, useTheme } from './useTheme.js';

const props = defineProps({
    options: {
        type: Array,
        required: true,
    },
    ariaLabel: {
        type: String,
        default: '',
    },
    modelValue: {
        type: String,
        default: null,
    },
    storageKey: {
        type: String,
        default: 'yuejing-theme',
    },
    defaultTheme: {
        type: String,
        default: 'system',
    },
});

const emit = defineEmits(['update:modelValue', 'change']);

const controlledTheme = computed(() => (
    THEME_VALUES.includes(props.modelValue) ? props.modelValue : undefined
));

const { theme, setTheme } = useTheme({
    storageKey: props.storageKey,
    defaultTheme: props.defaultTheme,
    initialTheme: controlledTheme.value,
});

const visibleOptions = computed(() => props.options.filter((option) => (
    option && THEME_VALUES.includes(option.value)
)));

const optionRefs = shallowRef([]);

function setOptionRef(element, index) {
    optionRefs.value[index] = element;
}

onBeforeUpdate(() => {
    optionRefs.value = [];
});

watch(controlledTheme, (nextTheme) => {
    if (nextTheme && nextTheme !== theme.value) setTheme(nextTheme);
});

function chooseTheme(nextTheme, shouldFocus = false) {
    if (!THEME_VALUES.includes(nextTheme)) return;

    const appliedTheme = setTheme(nextTheme);
    emit('update:modelValue', appliedTheme);
    emit('change', appliedTheme);

    if (shouldFocus) {
        const index = visibleOptions.value.findIndex((option) => option.value === appliedTheme);
        optionRefs.value[index]?.focus();
    }
}

function handleKeydown(event, index) {
    const key = event.key;
    const direction = key === 'ArrowLeft' || key === 'ArrowUp' ? -1 : 1;
    const isArrow = ['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(key);
    const isBoundary = key === 'Home' || key === 'End';

    if (!isArrow && !isBoundary) return;

    event.preventDefault();
    const length = visibleOptions.value.length;
    if (!length) return;

    const nextIndex = key === 'Home'
        ? 0
        : key === 'End'
            ? length - 1
            : (index + direction + length) % length;
    const nextOption = visibleOptions.value[nextIndex];
    chooseTheme(nextOption.value, true);
}
</script>

<template>
    <div class="theme-toggle" role="radiogroup" :aria-label="props.ariaLabel">
        <button
            v-for="(option, index) in visibleOptions"
            :key="option.value"
            :ref="(element) => setOptionRef(element, index)"
            type="button"
            class="theme-toggle-btn"
            :class="{ active: option.value === theme }"
            role="radio"
            :aria-checked="String(option.value === theme)"
            :tabindex="option.value === theme ? 0 : -1"
            :title="option.title || undefined"
            @click="chooseTheme(option.value)"
            @keydown="handleKeydown($event, index)"
        >
            <span v-if="option.icon" class="theme-toggle-icon" aria-hidden="true">{{ option.icon }}</span>
            <span class="theme-toggle-label">{{ option.label }}</span>
        </button>
    </div>
</template>

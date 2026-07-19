<script setup>
import { computed } from 'vue';

const props = defineProps({
    icon: { type: Object, required: true },
    label: { type: String, required: true },
    type: { type: String, default: 'button' },
    size: { type: String, default: 'md' },
    disabled: { type: Boolean, default: false },
    pressed: { type: Boolean, default: undefined },
});

const classes = computed(() => ['ui-icon-button', `ui-icon-button-${props.size}`]);
</script>

<template>
    <button
        :type="type"
        :class="classes"
        :aria-label="label"
        :title="label"
        :aria-pressed="pressed"
        :disabled="disabled"
    >
        <component :is="icon" aria-hidden="true" />
    </button>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    as: { type: String, default: 'section' },
    variant: { type: String, default: 'default' },
    flush: { type: Boolean, default: false },
});

const classes = computed(() => [
    'ui-panel',
    `ui-panel-${props.variant}`,
    { 'ui-panel-flush': props.flush },
]);
</script>

<template>
    <component :is="as" :class="classes"><slot /></component>
</template>

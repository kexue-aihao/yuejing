<script setup>
import { computed } from 'vue';

const props = defineProps({
    state: { type: String, default: 'neutral' },
    label: { type: String, default: '' },
    live: { type: Boolean, default: false },
});

const tone = computed(() => ({
    connected: 'success',
    success: 'success',
    loading: 'info',
    retrying: 'pending',
    pending: 'pending',
    error: 'error',
}[props.state] || 'neutral'));
const classes = computed(() => ['ui-status', `ui-status-${tone.value}`]);
const role = computed(() => tone.value === 'error' ? 'alert' : (props.live ? 'status' : undefined));
const liveMode = computed(() => tone.value === 'error' ? 'assertive' : (props.live ? 'polite' : undefined));
</script>

<template>
    <span :class="classes" :role="role" :aria-live="liveMode">
        <span class="ui-status-dot" aria-hidden="true"></span>
        <span class="ui-status-label">{{ label }}</span>
    </span>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    onFailure: { type: Function, default: null },
});

const host = ref(null);
let toast = null;
let button = null;
let handleDismiss = null;

onMounted(() => {
    toast = host.value?.closest('.toast');
    button = toast?.querySelector('[data-toast-dismiss]');
    if (!toast || !button) {
        props.onFailure?.();
        return;
    }

    handleDismiss = () => toast.remove();
    button.addEventListener('click', handleDismiss);
});

onBeforeUnmount(() => {
    button?.removeEventListener('click', handleDismiss);
    toast = null;
    button = null;
    handleDismiss = null;
});
</script>

<template>
    <span ref="host" class="toast-dismiss-vue" aria-hidden="true"></span>
</template>


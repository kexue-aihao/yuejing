<script setup>
import { useMobileMenu } from './useMobileMenu.js';

const props = defineProps({
    translations: {
        type: Object,
        default: () => ({}),
    },
});

useMobileMenu({ translations: () => props.translations });
</script>

<template>
    <span hidden aria-hidden="true"></span>
</template>

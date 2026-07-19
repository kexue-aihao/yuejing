<script setup>
import { ref } from 'vue';
import { useManuscriptFileUpload } from './useManuscriptFileUpload.js';

const props = defineProps({
    onFailure: { type: Function, default: null },
});

const host = ref(null);

useManuscriptFileUpload({
    host,
    onFailure: () => props.onFailure?.(),
});
</script>

<template>
    <span ref="host" class="chapter-manuscript-upload-vue" aria-hidden="true"></span>
</template>


<script setup>
import { ref } from 'vue';
import { useMarkdownEditor } from './useMarkdownEditor.js';

const props = defineProps({
    onFailure: { type: Function, default: null },
});

const editorHost = ref(null);

useMarkdownEditor({
    host: editorHost,
    onFailure: () => props.onFailure?.(),
});
</script>

<template>
    <span ref="editorHost" class="markdown-editor-vue" aria-hidden="true"></span>
</template>

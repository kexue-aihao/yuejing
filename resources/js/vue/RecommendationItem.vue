<script setup>
import { computed } from 'vue';

const props = defineProps({
    item: {
        type: Object,
        required: true,
    },
    novelBase: {
        type: String,
        required: true,
    },
    anonymousAuthor: {
        type: String,
        default: '',
    },
    unnamedTitle: {
        type: String,
        default: '',
    },
});

const emit = defineEmits({
    open: (item) => Boolean(item && typeof item === 'object'),
});

const title = computed(() => String(props.item.title || props.unnamedTitle));
const author = computed(() => props.item.author || props.anonymousAuthor);
const categories = computed(() => {
    if (!Array.isArray(props.item.categories)) return '';

    return props.item.categories.filter(Boolean).join(' · ');
});
const href = computed(() => {
    const base = props.novelBase.replace(/\/$/, '');
    const identifier = props.item.slug || props.item.id;

    return identifier === undefined || identifier === null
        ? base
        : `${base}/${encodeURIComponent(String(identifier))}`;
});

function handleOpen() {
    emit('open', props.item);
}
</script>

<template>
    <a class="recommendation-item" :href="href" @click="handleOpen">
        <span class="recommendation-mark" aria-hidden="true">Y</span>
        <span>
            <strong>{{ title }}</strong>
            <small>
                {{ author }}<template v-if="categories"> · {{ categories }}</template>
            </small>
        </span>
        <span aria-hidden="true">→</span>
    </a>
</template>

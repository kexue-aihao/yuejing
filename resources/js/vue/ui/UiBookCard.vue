<script setup>
import { computed, ref } from 'vue';
import { ArrowUpRight } from 'lucide-vue-next';

const props = defineProps({
    href: { type: String, required: true },
    title: { type: String, required: true },
    author: { type: String, default: '' },
    eyebrow: { type: String, default: '' },
    meta: { type: String, default: '' },
    initial: { type: String, default: 'Y' },
    coverSrc: { type: String, default: '' },
    coverAlt: { type: String, default: '' },
});

const initial = computed(() => String(props.initial || 'Y').slice(0, 1).toUpperCase());
const coverFailed = ref(false);

function handleCoverError() {
    coverFailed.value = true;
}
</script>

<template>
    <a class="ui-book-card" :href="href">
        <span class="ui-book-card-cover" aria-hidden="true">
            <span class="ui-book-card-spine"></span>
            <img v-if="coverSrc && !coverFailed" class="ui-book-card-image" :src="coverSrc" :alt="coverAlt || title" @error="handleCoverError">
            <span v-else class="ui-book-card-initial">{{ initial }}</span>
        </span>
        <span class="ui-book-card-body">
            <span v-if="eyebrow" class="ui-book-card-eyebrow">{{ eyebrow }}</span>
            <strong class="ui-book-card-title">{{ title }}</strong>
            <span v-if="author" class="ui-book-card-author">{{ author }}</span>
            <span v-if="meta" class="ui-book-card-meta">{{ meta }}</span>
        </span>
        <ArrowUpRight class="ui-book-card-arrow" aria-hidden="true" />
    </a>
</template>

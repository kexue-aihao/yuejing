<script setup>
import { computed } from 'vue';
import RecommendationItem from './RecommendationItem.vue';
import UiStatus from './ui/UiStatus.vue';
import { useRecommendations } from './useRecommendations';

const props = defineProps({
    apiUrl: {
        type: String,
        required: true,
    },
    novelBase: {
        type: String,
        required: true,
    },
    initialItems: {
        type: Array,
        default: () => [],
    },
    emptyText: {
        type: String,
        default: '',
    },
    loadingText: {
        type: String,
        default: '',
    },
    connectedText: {
        type: String,
        default: '',
    },
    retryingText: {
        type: String,
        default: '',
    },
    anonymousAuthor: {
        type: String,
        default: '',
    },
    unnamedTitle: {
        type: String,
        default: '',
    },
    limit: {
        type: Number,
        default: 6,
    },
    fallbackPollDelay: {
        type: Number,
        default: 60_000,
    },
});

const emit = defineEmits({
    open: (item) => Boolean(item && typeof item === 'object'),
});

const { items, status, isLoading } = useRecommendations({
    apiUrl: () => props.apiUrl,
    initialItems: () => props.initialItems,
    limit: () => props.limit,
    fallbackPollDelay: props.fallbackPollDelay,
});

const statusText = computed(() => ({
    loading: props.loadingText,
    connected: props.connectedText,
    retrying: props.retryingText,
}[status.value] || ''));

function handleOpen(item) {
    emit('open', item);
}
</script>

<template>
    <div class="recommendation-feed">
        <UiStatus class="recommendation-status" :state="status" :label="statusText" live />
        <div class="recommendation-grid">
            <RecommendationItem
                v-for="item in items"
                :key="item.id ?? item.slug"
                :item="item"
                :novel-base="novelBase"
                :anonymous-author="anonymousAuthor"
                :unnamed-title="unnamedTitle"
                @open="handleOpen"
            />
            <p v-if="!isLoading && !items.length" class="muted">
                {{ emptyText }}
            </p>
        </div>
    </div>
</template>

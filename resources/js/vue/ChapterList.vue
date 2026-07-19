<script setup>
import ChapterCard from './ChapterCard.vue';
import { useChapterList } from './useChapterList.js';

const props = defineProps({
    chapters: {
        type: Array,
        default: () => [],
    },
    csrfToken: {
        type: String,
        required: true,
    },
    translations: {
        type: Object,
        required: true,
    },
});

const { statusFilter, visibleChapters, setStatusFilter } = useChapterList({
    chapters: () => props.chapters,
});
</script>

<template>
    <div class="chapter-list-vue">
        <div class="chapter-list-toolbar">
            <label for="chapter-status-filter">{{ translations.filterLabel }}</label>
            <select id="chapter-status-filter" :value="statusFilter" @change="setStatusFilter($event.target.value)">
                <option value="all">{{ translations.all }}</option>
                <option value="draft">{{ translations.status?.draft }}</option>
                <option value="published">{{ translations.status?.published }}</option>
            </select>
        </div>
        <ChapterCard
            v-for="chapter in visibleChapters"
            :key="chapter.id"
            :chapter="chapter"
            :csrf-token="csrfToken"
            :translations="translations"
        />
        <div v-if="!visibleChapters.length" class="empty-state">
            <h2>{{ translations.noChapters }}</h2>
            <p>{{ translations.noChaptersIntro }}</p>
        </div>
    </div>
</template>

<style scoped>
.chapter-list-toolbar {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 10px;
    margin-bottom: 18px;
}

.chapter-list-toolbar label {
    color: var(--c-text-muted);
    font-size: 11px;
}

.chapter-list-toolbar select {
    min-width: 140px;
}
</style>

<script setup>
import { computed } from 'vue';
import { formatCommunicationTime, translate } from './useCommunicationApi.js';

const props = defineProps({
    review: {
        type: Object,
        required: true,
    },
    translations: {
        type: Object,
        default: () => ({}),
    },
});

const criteriaKeys = ['plot', 'writing', 'characters', 'originality'];
const userName = computed(() => props.review.user || translate(props.translations, 'anonymous_user'));
const rating = computed(() => {
    const numeric = Number(props.review.rating);
    return Number.isFinite(numeric) ? numeric.toFixed(1) : '';
});
const level = computed(() => props.review.level ? translate(props.translations, `level_${props.review.level}`) : '');
const criteria = computed(() => Object.entries(props.review.criteria || {})
    .filter(([key, value]) => criteriaKeys.includes(key) && value !== null && value !== '')
    .map(([key, value]) => ({ key, label: translate(props.translations, key), value })));
const createdAt = computed(() => props.review.created_at || '');
</script>

<template>
    <article class="panel review-item">
        <div class="review-item-head"><strong>{{ userName }}</strong><span>{{ rating }}<template v-if="level"> · {{ level }}</template></span></div>
        <p v-if="review.review" class="review-body">{{ review.review }}</p>
        <dl v-if="criteria.length" class="review-criteria-summary">
            <div v-for="item in criteria" :key="item.key"><dt>{{ item.label }}</dt><dd>{{ item.value }}/10</dd></div>
        </dl>
        <time v-if="createdAt" class="review-item-date" :datetime="createdAt">{{ formatCommunicationTime(createdAt) }}</time>
    </article>
</template>

<style scoped>
.review-body {
    white-space: pre-wrap;
}
</style>

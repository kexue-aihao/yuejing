<script setup>
import { computed, watch } from 'vue';
import UiButton from './ui/UiButton.vue';
import UiPanel from './ui/UiPanel.vue';
import UiStatus from './ui/UiStatus.vue';
import ReviewItem from './ReviewItem.vue';
import { translate } from './useCommunicationApi.js';
import { useNovelReviews } from './useNovelReviews.js';

const props = defineProps({
    apiUrl: { type: String, default: '' },
    initialStatistics: { type: Object, default: () => ({}) },
    initialReviews: { type: Array, default: () => [] },
    initialForm: { type: Object, default: () => ({}) },
    currentRating: { type: Boolean, default: false },
    authenticated: { type: Boolean, default: false },
    rateUrl: { type: String, default: '' },
    withdrawUrl: { type: String, default: '' },
    loginUrl: { type: String, default: '#' },
    csrfToken: { type: String, default: '' },
    translations: { type: Object, default: () => ({}) },
});

const {
    statistics,
    reviews,
    form,
    hasCurrentRating,
    status,
    statusError,
    isSubmitting,
    isWithdrawing,
    syncPageStatistics,
    submitReview,
    withdrawReview,
} = useNovelReviews({
    apiUrl: () => props.apiUrl,
    initialStatistics: () => props.initialStatistics,
    initialReviews: () => props.initialReviews,
    initialForm: () => props.initialForm,
    currentRating: () => props.currentRating,
    rateUrl: () => props.rateUrl,
    withdrawUrl: () => props.withdrawUrl,
    csrfToken: () => props.csrfToken,
    translations: () => props.translations,
});

const ratingSummary = computed(() => {
    const averageValue = statistics.value.average_rating;
    const hasAverage = averageValue !== null && averageValue !== undefined && averageValue !== '';
    const average = Number(averageValue);
    const level = statistics.value.average_rating_level ? t(`level_${statistics.value.average_rating_level}`) : '';
    return {
        summary: hasAverage && Number.isFinite(average)
            ? `${average.toFixed(1)} / 9.9${level ? ` · ${level}` : ''}`
            : t('no_rating'),
    };
});
const ratingCount = computed(() => Number(statistics.value.rating_count ?? statistics.value.reviews_count ?? 0));
const criteria = ['plot', 'writing', 'characters', 'originality'];

function t(key, replacements = {}) {
    return translate(props.translations, key, replacements);
}

watch(statistics, (next) => syncPageStatistics(next), { deep: true });
</script>

<template>
    <div class="review-vue-island">
        <div class="section-heading">
            <div><p class="eyebrow">{{ t('eyebrow') }}</p><h2>{{ t('title') }}</h2></div>
            <div class="review-summary" aria-live="polite">
                <span class="muted" data-review-summary>{{ ratingSummary.summary }}</span>
                <span class="muted" data-review-rating-count>{{ t('rating_count', { count: ratingCount }) }}</span>
            </div>
        </div>
        <UiStatus v-if="status" class="review-status" :state="statusError ? 'error' : 'info'" :label="status" live />

        <UiPanel v-if="authenticated" class="review-form-panel panel" variant="surface">
            <form method="POST" :action="rateUrl" @submit.prevent="submitReview">
                <input type="hidden" name="_token" :value="csrfToken">
                <div class="review-form-grid">
                    <div class="form-field"><label for="vue-rating">{{ t('rating_label') }}</label><input id="vue-rating" v-model="form.rating" name="rating" type="number" min="1" max="9.9" step="0.1" required :disabled="isSubmitting"></div>
                    <div class="form-field"><label for="vue-review">{{ t('review_label') }}</label><textarea id="vue-review" v-model="form.review" name="review" maxlength="2000" :placeholder="t('review_placeholder')" :disabled="isSubmitting"></textarea></div>
                </div>
                <fieldset class="review-criteria"><legend>{{ t('criteria_label') }}</legend><div class="review-criteria-grid">
                    <label v-for="criterion in criteria" :key="criterion" class="form-field" :for="`vue-criteria-${criterion}`"><span>{{ t(criterion) }}</span><input :id="`vue-criteria-${criterion}`" v-model="form.criteria[criterion]" :name="`criteria[${criterion}]`" type="number" min="1" max="10" :disabled="isSubmitting"></label>
                </div></fieldset>
                <div class="review-actions"><UiButton variant="primary" type="submit" :loading="isSubmitting">{{ t('submit') }}</UiButton><span v-if="hasCurrentRating" class="muted">{{ t('withdraw_hint') }}</span></div>
            </form>
            <form v-if="hasCurrentRating" method="POST" :action="withdrawUrl" class="review-withdraw-form" @submit.prevent="withdrawReview">
                <input type="hidden" name="_token" :value="csrfToken"><input type="hidden" name="_method" value="DELETE">
                <UiButton variant="outline" type="submit" :loading="isWithdrawing">{{ t('withdraw') }}</UiButton>
            </form>
        </UiPanel>
        <p v-else class="muted"><a class="text-link" :href="loginUrl">{{ t('login') }}</a> {{ t('login_to_review') }}</p>

        <div class="review-list">
            <ReviewItem v-for="(review, index) in reviews" :key="review.id ?? `${review.user || 'review'}-${index}`" :review="review" :translations="translations" />
            <p v-if="!reviews.length" class="muted">{{ t('no_rating') }}</p>
        </div>
    </div>
</template>

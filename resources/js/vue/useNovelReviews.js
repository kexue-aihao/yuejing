import { onBeforeUnmount, onMounted, reactive, ref, toValue } from 'vue';
import { translate } from './useCommunicationApi.js';

const REVIEW_STAT_KEYS = [
    'views_count',
    'published_chapters_count',
    'favorites_count',
    'reviews_count',
    'rating_count',
    'word_count',
];

function isConfiguredUrl(value) {
    return typeof value === 'string'
        && value.trim() !== ''
        && !/(^|\/)undefined(?:\/|$)/.test(value);
}

function normalizeReviews(value) {
    return Array.isArray(value) ? value : [];
}

function normalizeStatistics(value) {
    return value && typeof value === 'object' && !Array.isArray(value) ? value : {};
}

function formDataFromReview(form, csrfToken) {
    const data = new FormData();
    if (csrfToken) data.append('_token', csrfToken);
    data.append('rating', String(form.rating ?? ''));
    data.append('review', String(form.review ?? ''));
    Object.entries(form.criteria || {}).forEach(([key, value]) => {
        if (value !== null && value !== undefined && String(value) !== '') data.append(`criteria[${key}]`, String(value));
    });
    return data;
}

async function parseResponse(response, translations) {
    const contentType = response.headers.get('content-type') || '';
    const payload = contentType.includes('application/json')
        ? await response.json()
        : await response.text();
    if (!response.ok) {
        const message = typeof payload === 'object'
            ? payload?.message || Object.values(payload?.errors || {}).flat()[0]
            : payload;
        throw new Error(message || translate(translations, 'request_failed'));
    }
    return payload;
}

export function useNovelReviews({
    apiUrl,
    initialStatistics = {},
    initialReviews = [],
    initialForm = {},
    currentRating = false,
    rateUrl = '',
    withdrawUrl = '',
    csrfToken = '',
    translations = {},
} = {}) {
    const api = toValue(apiUrl) || '';
    const locale = toValue(translations) || {};
    const statistics = ref(normalizeStatistics(toValue(initialStatistics)));
    const reviews = ref(normalizeReviews(toValue(initialReviews)));
    const form = reactive({
        rating: toValue(initialForm)?.rating ?? '',
        review: toValue(initialForm)?.review ?? '',
        criteria: { ...(toValue(initialForm)?.criteria || {}) },
    });
    const hasCurrentRating = ref(Boolean(toValue(currentRating)));
    const status = ref('');
    const statusError = ref(false);
    const isLoading = ref(false);
    const isSubmitting = ref(false);
    const isWithdrawing = ref(false);
    let refreshTimer = null;
    let mounted = false;

    function setStatus(message, error = false) {
        status.value = message;
        statusError.value = error;
    }

    function syncPageStatistics(nextStatistics) {
        const next = normalizeStatistics(nextStatistics);
        const formatCount = (value) => new Intl.NumberFormat(document.documentElement.lang || 'en').format(Number(value) || 0);
        const formatStat = (key, value) => key === 'last_updated_at'
            ? (value ? new Intl.DateTimeFormat(document.documentElement.lang || 'en', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : translate(locale, 'no_update'))
            : REVIEW_STAT_KEYS.includes(key) ? formatCount(value) : String(value ?? '');
        const hasAverage = next.average_rating !== null && next.average_rating !== undefined && next.average_rating !== '';
        const average = Number(next.average_rating);
        const averageText = hasAverage && Number.isFinite(average) ? average.toFixed(1) : translate(locale, 'no_rating');
        const level = next.average_rating_level ? translate(locale, `level_${next.average_rating_level}`) : '';
        const count = Number(next.rating_count ?? next.reviews_count ?? 0);

        document.querySelectorAll('[data-review-average]').forEach((element) => { element.textContent = averageText; });
        document.querySelectorAll('[data-review-stat]').forEach((element) => {
            const key = element.dataset.reviewStat;
            if (Object.prototype.hasOwnProperty.call(next, key)) element.textContent = formatStat(key, next[key]);
        });
        document.querySelectorAll('[data-review-chapter-total]').forEach((element) => {
            if (Object.prototype.hasOwnProperty.call(next, 'published_chapters_count')) {
                element.textContent = translate(locale, 'chapter_total', { count: next.published_chapters_count });
            }
        });

        return {
            averageText,
            summary: hasAverage && Number.isFinite(average)
                ? `${average.toFixed(1)} / 9.9${level ? ` · ${level}` : ''}`
                : translate(locale, 'no_rating'),
            count,
        };
    }

    async function loadReviews() {
        if (!mounted || isLoading.value || !isConfiguredUrl(api)) return false;
        isLoading.value = true;
        try {
            const response = await fetch(api, {
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const payload = await parseResponse(response, locale);
            statistics.value = normalizeStatistics(payload?.statistics);
            reviews.value = normalizeReviews(payload?.reviews);
            setStatus(translate(locale, 'updated'));
            return true;
        } catch (error) {
            setStatus(error.message || translate(locale, 'network_error'), true);
            return false;
        } finally {
            isLoading.value = false;
        }
    }

    async function submitReview() {
        if (!isConfiguredUrl(rateUrl) || isSubmitting.value) return false;
        isSubmitting.value = true;
        try {
            const response = await fetch(rateUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}) },
                body: formDataFromReview(form, csrfToken),
            });
            await parseResponse(response, locale);
            hasCurrentRating.value = true;
            const loaded = await loadReviews();
            if (loaded) setStatus(translate(locale, 'rating_saved'));
            return loaded;
        } catch (error) {
            setStatus(error.message || translate(locale, 'request_failed'), true);
            return false;
        } finally {
            isSubmitting.value = false;
        }
    }

    async function withdrawReview() {
        if (!isConfiguredUrl(withdrawUrl) || isWithdrawing.value) return false;
        isWithdrawing.value = true;
        const data = new FormData();
        if (csrfToken) data.append('_token', csrfToken);
        data.append('_method', 'DELETE');
        try {
            const response = await fetch(withdrawUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}) },
                body: data,
            });
            await parseResponse(response, locale);
            hasCurrentRating.value = false;
            form.rating = '';
            form.review = '';
            form.criteria = {};
            const loaded = await loadReviews();
            if (loaded) setStatus(translate(locale, 'rating_withdrawn'));
            return loaded;
        } catch (error) {
            setStatus(error.message || translate(locale, 'request_failed'), true);
            return false;
        } finally {
            isWithdrawing.value = false;
        }
    }

    onMounted(() => {
        mounted = true;
        syncPageStatistics(statistics.value);
        void loadReviews();
        refreshTimer = window.setInterval(() => { void loadReviews(); }, 30_000);
    });

    onBeforeUnmount(() => {
        mounted = false;
        window.clearInterval(refreshTimer);
        refreshTimer = null;
    });

    return {
        statistics,
        reviews,
        form,
        hasCurrentRating,
        status,
        statusError,
        isLoading,
        isSubmitting,
        isWithdrawing,
        syncPageStatistics,
        submitReview,
        withdrawReview,
    };
}

import { computed, readonly, shallowRef, toValue } from 'vue';

const FILTER_VALUES = Object.freeze(['all', 'draft', 'published']);

export function useChapterList({ chapters = [] } = {}) {
    const sourceChapters = shallowRef(Array.isArray(toValue(chapters)) ? toValue(chapters) : []);
    const statusFilter = shallowRef('all');

    const visibleChapters = computed(() => {
        if (statusFilter.value === 'all') return sourceChapters.value;
        return sourceChapters.value.filter((chapter) => chapter.status === statusFilter.value);
    });

    function setStatusFilter(value) {
        statusFilter.value = FILTER_VALUES.includes(value) ? value : 'all';
    }

    return {
        statusFilter: readonly(statusFilter),
        visibleChapters,
        setStatusFilter,
    };
}

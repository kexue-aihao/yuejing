<script setup>
import { computed } from 'vue';

const props = defineProps({
    chapter: {
        type: Object,
        required: true,
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

const statusClass = computed(() => props.chapter.status === 'draft' ? 'pending' : '');
const statusLabel = computed(() => props.translations.status?.[props.chapter.status] || props.chapter.status);
const chapterLabel = computed(() => String(props.translations.chapterPrefix || '').replace(':number', String(props.chapter.chapter_number)));
const deleteFormId = computed(() => `delete-chapter-${props.chapter.id}`);
</script>

<template>
    <section class="panel author-chapter-card">
        <form
            class="form-stack"
            method="POST"
            :action="chapter.updateUrl"
            enctype="multipart/form-data"
            data-chapter-manuscript-form
        >
            <span class="chapter-manuscript-upload-vue-island" data-vue-chapter-manuscript-upload aria-hidden="true"></span>
            <input type="hidden" name="_token" :value="csrfToken">
            <input type="hidden" name="_method" value="PUT">
            <input type="hidden" name="content_format" :value="chapter.content_format || 'markdown'" data-manuscript-format>
            <div class="panel-heading">
                <div><p class="panel-kicker">{{ chapterLabel }}</p><h2>{{ chapter.title }}</h2></div>
                <span class="status" :class="statusClass">{{ statusLabel }}</span>
            </div>
            <div class="settings-grid">
                <div class="form-field"><label :for="`chapter_number_${chapter.id}`">{{ translations.chapterNumber }}</label><input :id="`chapter_number_${chapter.id}`" name="chapter_number" type="number" min="1" :value="chapter.chapter_number" required></div>
                <div class="form-field"><label :for="`chapter_title_${chapter.id}`">{{ translations.chapterTitle }}</label><input :id="`chapter_title_${chapter.id}`" name="title" :value="chapter.title" required></div>
                <div class="form-field"><label :for="`chapter_status_${chapter.id}`">{{ translations.chapterStatus }}</label><select :id="`chapter_status_${chapter.id}`" name="status"><option value="draft" :selected="chapter.status === 'draft'">{{ translations.status?.draft }}</option><option value="published" :selected="chapter.status === 'published'">{{ translations.status?.published }}</option></select></div>
            </div>
            <div class="form-field">
                <label :for="`chapter_content_${chapter.id}`">{{ translations.chapterContent }}</label>
                <textarea :id="`chapter_content_${chapter.id}`" name="content" data-manuscript-content rows="12">{{ chapter.content || '' }}</textarea>
                <label :for="`chapter_file_${chapter.id}`">{{ translations.uploadManuscript }}</label>
                <input :id="`chapter_file_${chapter.id}`" name="chapter_file" type="file" accept=".md,.markdown,.txt,text/markdown,text/plain" data-manuscript-file>
                <span class="form-help" data-manuscript-file-name aria-live="polite"></span>
                <p class="form-help">{{ translations.uploadManuscriptHelp }}</p>
            </div>
            <div class="card-actions">
                <button class="button button-primary button-small" type="submit">{{ translations.saveChapter }}</button>
                <button class="button button-outline button-small" type="submit" :form="deleteFormId">{{ translations.deleteChapter }}</button>
            </div>
        </form>
        <form :id="deleteFormId" method="POST" :action="chapter.deleteUrl" class="sr-only">
            <input type="hidden" name="_token" :value="csrfToken">
            <input type="hidden" name="_method" value="DELETE">
        </form>
    </section>
</template>

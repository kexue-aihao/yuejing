<script setup>
import { toRef } from 'vue';
import { useCoverPreview } from './useCoverPreview';

const props = defineProps({
    inputId: {
        type: String,
        required: true,
    },
    inputName: {
        type: String,
        required: true,
    },
    accept: {
        type: String,
        default: 'image/*',
    },
    required: {
        type: Boolean,
        default: false,
    },
    previewAlt: {
        type: String,
        default: '',
    },
});

const emit = defineEmits([
    'file-change',
    'invalid-file',
    'preview-cleared',
]);

const {
    previewUrl,
    invalidReason,
    handleFileChange,
} = useCoverPreview({ accept: toRef(props, 'accept') });

function onInputChange(event) {
    const result = handleFileChange(event);

    if (!result.valid) {
        const input = event?.target;
        if (input && typeof input === 'object' && 'value' in input) {
            input.value = '';
        }

        emit('invalid-file', {
            file: result.file,
            reason: result.reason || invalidReason.value,
        });
        emit('file-change', null);

        return;
    }

    emit('file-change', result.file);

    if (!result.file) {
        emit('preview-cleared');
    }
}
</script>

<template>
    <input
        :id="inputId"
        :name="inputName"
        type="file"
        :accept="accept"
        :required="required"
        :aria-invalid="invalidReason ? 'true' : undefined"
        @change="onInputChange"
    >
    <img
        v-if="previewUrl"
        class="cover-upload-preview"
        :src="previewUrl"
        :alt="previewAlt"
    >
</template>

<style scoped>
.cover-upload-preview {
    display: block;
}
</style>

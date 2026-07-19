import { onBeforeUnmount, readonly, shallowRef, toValue } from 'vue';

const IMAGE_EXTENSIONS = new Set([
    '.avif',
    '.bmp',
    '.gif',
    '.jpeg',
    '.jpg',
    '.png',
    '.svg',
    '.webp',
]);

function getFileExtension(fileName) {
    const name = String(fileName || '').trim().toLowerCase();
    const lastDot = name.lastIndexOf('.');

    return lastDot >= 0 ? name.slice(lastDot) : '';
}

function extractFile(source) {
    if (source && typeof source === 'object' && 'target' in source) {
        return source.target?.files?.[0] || null;
    }

    return source || null;
}

function matchesAccept(file, accept) {
    const acceptedTypes = String(toValue(accept) || 'image/*')
        .split(',')
        .map((value) => value.trim().toLowerCase())
        .filter(Boolean);
    const fileType = String(file.type || '').toLowerCase();
    const extension = getFileExtension(file.name);

    return acceptedTypes.some((acceptedType) => {
        if (acceptedType === 'image/*') {
            return fileType.startsWith('image/') || IMAGE_EXTENSIONS.has(extension);
        }

        if (acceptedType.endsWith('/*')) {
            return fileType.startsWith(acceptedType.slice(0, -1));
        }

        if (acceptedType.startsWith('.')) {
            return extension === acceptedType;
        }

        return fileType === acceptedType;
    });
}

function isValidImage(file, accept) {
    if (!file || typeof file !== 'object') return false;

    const fileType = String(file.type || '').toLowerCase();
    const isImage = fileType.startsWith('image/') || IMAGE_EXTENSIONS.has(getFileExtension(file.name));

    return isImage && matchesAccept(file, accept);
}

export function useCoverPreview({ accept = 'image/*' } = {}) {
    const previewUrl = shallowRef(null);
    const selectedFile = shallowRef(null);
    const invalidReason = shallowRef(null);
    let currentObjectUrl = null;

    function revokeCurrentObjectUrl() {
        if (!currentObjectUrl) return;

        if (typeof URL !== 'undefined' && typeof URL.revokeObjectURL === 'function') {
            URL.revokeObjectURL(currentObjectUrl);
        }

        currentObjectUrl = null;
    }

    function clearPreview(reason = null) {
        revokeCurrentObjectUrl();
        previewUrl.value = null;
        selectedFile.value = null;
        invalidReason.value = reason;
    }

    function handleFileChange(source) {
        const file = extractFile(source);

        if (!file) {
            clearPreview();

            return { valid: true, file: null, previewUrl: null };
        }

        if (!isValidImage(file, accept)) {
            clearPreview('invalid-image');

            return { valid: false, file, previewUrl: null, reason: invalidReason.value };
        }

        if (typeof URL === 'undefined' || typeof URL.createObjectURL !== 'function') {
            revokeCurrentObjectUrl();
            previewUrl.value = null;
            selectedFile.value = file;
            invalidReason.value = null;

            return { valid: true, file, previewUrl: null, previewUnavailable: true };
        }

        revokeCurrentObjectUrl();

        try {
            currentObjectUrl = URL.createObjectURL(file);
            previewUrl.value = currentObjectUrl;
            selectedFile.value = file;
            invalidReason.value = null;

            return { valid: true, file, previewUrl: currentObjectUrl };
        } catch {
            revokeCurrentObjectUrl();
            previewUrl.value = null;
            selectedFile.value = file;
            invalidReason.value = null;

            return { valid: true, file, previewUrl: null, previewUnavailable: true };
        }
    }

    onBeforeUnmount(() => {
        revokeCurrentObjectUrl();
    });

    return {
        previewUrl: readonly(previewUrl),
        selectedFile: readonly(selectedFile),
        invalidReason: readonly(invalidReason),
        handleFileChange,
        clearPreview,
    };
}

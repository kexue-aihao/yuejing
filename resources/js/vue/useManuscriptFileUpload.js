import { onBeforeUnmount, onMounted, readonly, ref, toValue } from 'vue';

function resolveValue(value) {
    let resolved = typeof value === 'function' ? value() : toValue(value);

    while (resolved && typeof resolved === 'object'
        && !('nodeType' in resolved) && 'value' in resolved) {
        resolved = resolved.value;
    }

    return resolved;
}

function resolveElement(value) {
    const resolved = resolveValue(value);

    return resolved && typeof resolved.querySelector === 'function'
        ? resolved
        : null;
}

export async function readManuscriptFile(file) {
    if (!file || typeof file.arrayBuffer !== 'function') {
        throw new Error('Invalid manuscript file');
    }

    const buffer = await file.arrayBuffer();
    const bytes = new Uint8Array(buffer);
    if (typeof TextDecoder === 'undefined') throw new Error('TextDecoder unavailable');

    let encoding = 'utf-8';
    let offset = 0;

    if (bytes[0] === 0xff && bytes[1] === 0xfe) {
        encoding = 'utf-16le';
        offset = 2;
    } else if (bytes[0] === 0xfe && bytes[1] === 0xff) {
        encoding = 'utf-16be';
        offset = 2;
    } else if (bytes[0] === 0xef && bytes[1] === 0xbb && bytes[2] === 0xbf) {
        offset = 3;
    }

    const encodings = offset > 0 ? [encoding] : ['utf-8', 'gb18030', 'big5', 'windows-1252'];
    for (const candidate of encodings) {
        try {
            return new TextDecoder(candidate, { fatal: true })
                .decode(bytes.slice(offset))
                .replace(/\r\n?/g, '\n');
        } catch {
            // Try the next supported encoding before reporting an error.
        }
    }

    throw new Error('Unsupported manuscript encoding');
}

export function manuscriptFormatFor(file) {
    return /\.txt$/i.test(file?.name || '') ? 'text' : 'markdown';
}

export function useManuscriptFileUpload({
    host = null,
    form = null,
    onFailure = null,
} = {}) {
    const fileLoadedIntoEditor = ref(false);
    let boundForm = null;
    let boundTextarea = null;
    let boundFile = null;
    let boundFileName = null;
    let boundFormat = null;
    let boundFileChange = null;
    let boundSubmit = null;
    let readSequence = 0;

    function bindDom() {
        const hostElement = resolveElement(host);
        boundForm = resolveElement(form)
            || hostElement?.closest('form');
        boundTextarea = boundForm?.querySelector('[data-manuscript-content]') || null;
        boundFile = boundForm?.querySelector('[data-manuscript-file]') || null;
        boundFileName = boundForm?.querySelector('[data-manuscript-file-name]') || null;
        boundFormat = boundForm?.querySelector('[data-manuscript-format]') || null;
    }

    function invalidateRead() {
        readSequence += 1;
    }

    async function handleFileChange(event) {
        const fileInput = event?.target || boundFile;
        const file = fileInput?.files?.[0];
        const sequence = ++readSequence;

        if (!file) {
            fileLoadedIntoEditor.value = false;
            if (boundFileName) boundFileName.textContent = '';
            return false;
        }

        if (boundFileName) boundFileName.textContent = file.name;

        try {
            const content = await readManuscriptFile(file);
            if (sequence !== readSequence || boundFile?.files?.[0] !== file) return false;

            if (boundTextarea) boundTextarea.value = content;
            if (boundFormat) boundFormat.value = manuscriptFormatFor(file);
            fileLoadedIntoEditor.value = true;
            boundTextarea?.dispatchEvent(new Event('input', { bubbles: true }));
            return true;
        } catch {
            if (sequence === readSequence) fileLoadedIntoEditor.value = false;
            return false;
        }
    }

    function handleSubmit() {
        if (fileLoadedIntoEditor.value && boundFile?.files?.length) boundFile.value = '';
    }

    function unbind() {
        invalidateRead();
        boundFile?.removeEventListener('change', boundFileChange);
        boundForm?.removeEventListener('submit', boundSubmit);
        boundFile = null;
        boundForm = null;
        boundTextarea = null;
        boundFileName = null;
        boundFormat = null;
        boundFileChange = null;
        boundSubmit = null;
    }

    onMounted(() => {
        bindDom();
        if (!boundForm || !boundTextarea || !boundFile) {
            unbind();
            onFailure?.();
            return;
        }

        boundFileChange = (event) => { void handleFileChange(event); };
        boundSubmit = () => handleSubmit();
        boundFile.addEventListener('change', boundFileChange);
        boundForm.addEventListener('submit', boundSubmit);
    });

    onBeforeUnmount(unbind);

    return {
        fileLoadedIntoEditor: readonly(fileLoadedIntoEditor),
        handleFileChange,
        handleSubmit,
        readManuscriptFile,
        manuscriptFormatFor,
    };
}


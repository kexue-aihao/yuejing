import { onBeforeUnmount, onMounted, readonly, ref, shallowRef, toValue } from 'vue';
import Vditor from 'vditor';
import 'vditor/dist/index.css';

const DRAFT_PREFIX = 'yuejing-markdown-draft:';
const DEFAULT_HEIGHT = 460;
const DEFAULT_TOOLBAR = [
    'headings', 'bold', 'italic', 'strike', '|', 'quote', 'line',
    'list', 'ordered-list', 'check', 'link', 'code', 'table', '|',
    'undo', 'redo', '|', 'fullscreen', 'edit-mode', 'preview',
];

function resolveValue(value) {
    let resolved = typeof value === 'function' ? value() : toValue(value);

    // DOM nodes have a value property too. Only unwrap Vue refs, otherwise
    // textarea and file inputs would be mistaken for their string values.
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

function storageKeyFor(pathname = '') {
    return `${DRAFT_PREFIX}${pathname || ''}`;
}

function canUseStorage() {
    return typeof window !== 'undefined' && typeof window.localStorage !== 'undefined';
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

function pagePath() {
    return typeof window !== 'undefined' ? window.location.pathname : '';
}

function editorLocale() {
    return typeof window !== 'undefined'
        ? window.YuejingI18n?.editorLocale || 'en_US'
        : 'en_US';
}

function editorIsRtl() {
    return typeof window !== 'undefined'
        && (window.YuejingI18n?.editorDirection === 'rtl'
            || document.documentElement.dir === 'rtl');
}

export function useMarkdownEditor({
    host = null,
    form = null,
    source = null,
    editor = null,
    manuscriptFile = null,
    manuscriptFileName = null,
    manuscriptFormat = null,
    clearDraftButton = null,
    height = DEFAULT_HEIGHT,
    toolbar = DEFAULT_TOOLBAR,
    onSourceSync = null,
    onFailure = null,
} = {}) {
    const sourceValue = ref('');
    const isInitialized = ref(false);
    const initializationFailed = ref(false);
    const fileLoadedIntoEditor = ref(false);
    const storageKey = ref(storageKeyFor(pagePath()));
    const editorInstance = shallowRef(null);

    let instance = null;
    let boundForm = null;
    let boundSource = null;
    let boundEditor = null;
    let boundFile = null;
    let boundFileName = null;
    let boundFormat = null;
    let boundClearButton = null;
    let boundFormSubmit = null;
    let boundFileChange = null;
    let boundClear = null;
    let boundEditorInput = null;

    function readDraft() {
        if (!canUseStorage()) return '';

        try {
            return window.localStorage.getItem(storageKey.value) || '';
        } catch {
            return '';
        }
    }

    function persist(value) {
        if (!canUseStorage()) return;

        try {
            window.localStorage.setItem(storageKey.value, value);
        } catch {
            // Editing remains available when browser storage is unavailable.
        }
    }

    function removeDraft() {
        if (!canUseStorage()) return;

        try {
            window.localStorage.removeItem(storageKey.value);
        } catch {
            // Ignore unavailable storage.
        }
    }

    function syncSource(value, persistDraft = true) {
        const nextValue = String(value ?? '');
        sourceValue.value = nextValue;
        if (boundSource && 'value' in boundSource) boundSource.value = nextValue;
        if (persistDraft) persist(nextValue);
        if (typeof onSourceSync === 'function') onSourceSync(nextValue);
    }

    function clearFileInput() {
        if (boundFile && 'value' in boundFile) boundFile.value = '';
    }

    function clearEditor() {
        instance?.setValue('');
        syncSource('', false);
        removeDraft();
        fileLoadedIntoEditor.value = false;
        clearFileInput();
        if (boundFileName) boundFileName.textContent = '';
    }

    async function handleFileChange(event) {
        const fileInput = event?.target || boundFile;
        const file = fileInput?.files?.[0];

        if (!file) {
            fileLoadedIntoEditor.value = false;
            if (boundFileName) boundFileName.textContent = '';
            return false;
        }

        if (boundFileName) boundFileName.textContent = file.name;

        try {
            const content = await readManuscriptFile(file);
            instance?.setValue(content);
            syncSource(content);
            if (boundFormat && 'value' in boundFormat) boundFormat.value = manuscriptFormatFor(file);

            fileLoadedIntoEditor.value = true;
            // The editor now owns the content. Clear the file control so the
            // browser cannot submit both manuscript sources.
            clearFileInput();
            return true;
        } catch {
            fileLoadedIntoEditor.value = false;
            return false;
        }
    }

    function handleSubmit() {
        syncSource(instance?.getValue?.() ?? sourceValue.value);
        if (fileLoadedIntoEditor.value) clearFileInput();
        removeDraft();
    }

    function bindDom() {
        const hostElement = resolveElement(host);
        boundForm = resolveElement(form)
            || hostElement?.closest('form')
            || resolveElement(source)?.closest('form');
        boundSource = resolveElement(source)
            || boundForm?.querySelector('textarea[data-markdown-source]');
        boundEditor = resolveElement(editor)
            || boundForm?.querySelector('[data-vditor-editor]');
        boundFile = resolveElement(manuscriptFile)
            || boundForm?.querySelector('[data-manuscript-file]');
        boundFileName = resolveElement(manuscriptFileName)
            || boundForm?.querySelector('[data-manuscript-file-name]');
        boundFormat = resolveElement(manuscriptFormat)
            || boundForm?.querySelector('[data-manuscript-format]');
        boundClearButton = resolveElement(clearDraftButton)
            || boundForm?.querySelector('[data-clear-markdown-draft]');
    }

    function bindEvents() {
        if (boundFile) {
            boundFileChange = (event) => { void handleFileChange(event); };
            boundFile.addEventListener('change', boundFileChange);
        }
        if (boundForm) {
            boundFormSubmit = () => handleSubmit();
            boundForm.addEventListener('submit', boundFormSubmit);
        }
        if (boundClearButton) {
            boundClear = () => clearEditor();
            boundClearButton.addEventListener('click', boundClear);
        }
    }

    function unbindEvents() {
        boundFile?.removeEventListener('change', boundFileChange);
        boundForm?.removeEventListener('submit', boundFormSubmit);
        boundClearButton?.removeEventListener('click', boundClear);
        boundEditor?.removeEventListener('input', boundEditorInput);
        boundFile = null;
        boundForm = null;
        boundClearButton = null;
        boundFileChange = null;
        boundFormSubmit = null;
        boundClear = null;
        boundEditorInput = null;
    }

    function restoreFallback() {
        if (boundEditor) boundEditor.hidden = true;
        if (boundSource) boundSource.hidden = false;
        isInitialized.value = false;
        initializationFailed.value = true;
    }

    function initialize() {
        if (instance) return instance;

        bindDom();
        if (!boundEditor) {
            restoreFallback();
            return null;
        }

        const sourceInitialValue = boundSource && 'value' in boundSource
            ? String(boundSource.value || '')
            : '';
        const draft = readDraft();
        const initialValue = sourceInitialValue.trim() ? sourceInitialValue : draft;

        try {
            boundEditor.hidden = false;
            instance = new Vditor(boundEditor, {
                lang: editorLocale(),
                rtl: editorIsRtl(),
                mode: 'sv',
                height,
                value: initialValue,
                cache: { enable: false },
                counter: { enable: true },
                toolbar,
                after: () => {
                    if (!instance || !boundEditor) return;
                    boundEditorInput = () => syncSource(instance.getValue());
                    boundEditor.addEventListener('input', boundEditorInput);
                    if (boundSource) boundSource.hidden = true;
                    syncSource(instance.getValue());
                    isInitialized.value = true;
                },
            });
            editorInstance.value = instance;
            initializationFailed.value = false;
            return instance;
        } catch {
            instance?.destroy?.();
            instance = null;
            editorInstance.value = null;
            restoreFallback();
            return null;
        }
    }

    onMounted(() => {
        bindDom();
        bindEvents();
        if (!initialize()) {
            unbindEvents();
            onFailure?.();
        }
    });

    onBeforeUnmount(() => {
        unbindEvents();
        instance?.destroy?.();
        instance = null;
        editorInstance.value = null;
        if (boundSource) boundSource.hidden = false;
        if (boundEditor) boundEditor.hidden = true;
        boundEditor = null;
        boundSource = null;
        isInitialized.value = false;
    });

    return {
        sourceValue: readonly(sourceValue),
        storageKey: readonly(storageKey),
        isInitialized: readonly(isInitialized),
        initializationFailed: readonly(initializationFailed),
        fileLoadedIntoEditor: readonly(fileLoadedIntoEditor),
        instance: readonly(editorInstance),
        initialize,
        syncSource,
        clearEditor,
        handleFileChange,
        handleSubmit,
        readManuscriptFile,
        manuscriptFormatFor,
    };
}

export { DEFAULT_HEIGHT, DEFAULT_TOOLBAR, storageKeyFor };

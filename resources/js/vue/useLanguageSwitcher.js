import { onBeforeUnmount, onMounted } from 'vue';

export function useLanguageSwitcher() {
    let form = null;
    let select = null;

    function submitForm() {
        if (!form) return;
        if (typeof form.requestSubmit === 'function') form.requestSubmit();
        else form.submit();
    }

    onMounted(() => {
        form = document.querySelector('[data-language-switcher]');
        select = form?.querySelector('select[name="locale"]') || null;
        select?.addEventListener('change', submitForm);
    });

    onBeforeUnmount(() => {
        select?.removeEventListener('change', submitForm);
    });

    return { submitForm };
}

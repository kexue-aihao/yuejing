import { onBeforeUnmount, onMounted, ref, toValue } from 'vue';
import { translate } from './useCommunicationApi.js';

export function useMobileMenu({ translations = {} } = {}) {
    const locale = toValue(translations) || {};
    const isOpen = ref(false);
    let toggle = null;
    let menu = null;
    let closeButton = null;
    let links = [];

    function setMenuState(nextState) {
        isOpen.value = Boolean(nextState);
        if (!menu || !toggle) return;
        menu.toggleAttribute('hidden', !isOpen.value);
        menu.setAttribute('aria-hidden', String(!isOpen.value));
        menu.inert = !isOpen.value;
        toggle.setAttribute('aria-expanded', String(isOpen.value));
        toggle.setAttribute('aria-label', translate(locale, isOpen.value ? 'close_menu' : 'open_menu'));
        const toggleText = toggle.querySelector('.sr-only');
        if (toggleText) toggleText.textContent = translate(locale, isOpen.value ? 'close_menu' : 'open_menu');
        if (isOpen.value) {
            window.requestAnimationFrame(() => closeButton?.focus());
        } else {
            toggle.focus();
        }
    }

    function closeMenu() {
        setMenuState(false);
    }

    function handleToggleClick() {
        setMenuState(!isOpen.value);
    }

    function handleKeydown(event) {
        if (event.key === 'Escape' && isOpen.value) {
            event.preventDefault();
            closeMenu();
        }
    }

    function handleDocumentClick(event) {
        if (isOpen.value && menu && toggle && !menu.contains(event.target) && !toggle.contains(event.target)) closeMenu();
    }

    onMounted(() => {
        toggle = document.querySelector('[data-menu-toggle]');
        menu = document.querySelector('[data-mobile-menu]');
        if (!toggle || !menu) return;
        closeButton = menu.querySelector('[data-menu-close]');
        links = [...menu.querySelectorAll('a')];
        setMenuState(false);
        toggle.addEventListener('click', handleToggleClick);
        closeButton?.addEventListener('click', closeMenu);
        links.forEach((link) => link.addEventListener('click', closeMenu));
        document.addEventListener('keydown', handleKeydown);
        document.addEventListener('click', handleDocumentClick);
    });

    onBeforeUnmount(() => {
        toggle?.removeEventListener('click', handleToggleClick);
        closeButton?.removeEventListener('click', closeMenu);
        links.forEach((link) => link.removeEventListener('click', closeMenu));
        document.removeEventListener('keydown', handleKeydown);
        document.removeEventListener('click', handleDocumentClick);
    });

    return { isOpen, closeMenu, setMenuState };
}

// ── Theme Manager ──
class ThemeManager {
    constructor() {
        this.STORAGE_KEY = 'yuejing-theme';
        this.validThemes = ['light', 'dark', 'system'];
        this.mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
        this.currentTheme = this.getStoredTheme();
        this.applyTheme(this.currentTheme);
        this.initializeToggles();
        this.listenToSystemChanges();
    }

    getStoredTheme() {
        try {
            const storedTheme = localStorage.getItem(this.STORAGE_KEY);
            return this.validThemes.includes(storedTheme) ? storedTheme : 'system';
        } catch {
            return 'system';
        }
    }

    applyTheme(theme) {
        const nextTheme = this.validThemes.includes(theme) ? theme : 'system';
        if (nextTheme === 'system') {
            document.documentElement.removeAttribute('data-theme');
        } else {
            document.documentElement.setAttribute('data-theme', nextTheme);
        }

        try {
            localStorage.setItem(this.STORAGE_KEY, nextTheme);
        } catch {
            // The current DOM theme still applies without persistent storage.
        }

        this.currentTheme = nextTheme;
        this.updateToggleUI();
    }

    initializeToggles() {
        const buttons = [...document.querySelectorAll('[data-theme-action]')];
        buttons.forEach((button, index) => {
            button.addEventListener('click', () => {
                this.applyTheme(button.dataset.themeAction);
                button.focus();
            });
            button.addEventListener('keydown', (event) => {
                if (!['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Home', 'End'].includes(event.key)) return;
                event.preventDefault();
                const direction = event.key === 'ArrowLeft' || event.key === 'ArrowUp' ? -1 : 1;
                const nextIndex = event.key === 'Home' ? 0 : event.key === 'End' ? buttons.length - 1 : (index + direction + buttons.length) % buttons.length;
                const nextButton = buttons[nextIndex];
                this.applyTheme(nextButton.dataset.themeAction);
                nextButton.focus();
            });
        });
    }

    updateToggleUI() {
        document.querySelectorAll('[data-theme-action]').forEach((button) => {
            const isActive = button.dataset.themeAction === this.currentTheme;
            button.classList.toggle('active', isActive);
            button.setAttribute('aria-checked', String(isActive));
            button.tabIndex = isActive ? 0 : -1;
        });
    }

    listenToSystemChanges() {
        const update = () => {
            if (this.currentTheme === 'system') this.updateToggleUI();
        };
        if (typeof this.mediaQuery.addEventListener === 'function') {
            this.mediaQuery.addEventListener('change', update);
        } else {
            this.mediaQuery.addListener(update);
        }
    }
}

// ── Mobile Menu ──
function initMobileMenu() {
    const toggle = document.querySelector('[data-menu-toggle]');
    const menu = document.querySelector('[data-mobile-menu]');
    if (!toggle || !menu) return;

    const closeBtn = menu.querySelector('[data-menu-close]');
    const setMenuState = (isOpen) => {
        menu.toggleAttribute('hidden', !isOpen);
        menu.setAttribute('aria-hidden', String(!isOpen));
        menu.inert = !isOpen;
        toggle.setAttribute('aria-expanded', String(isOpen));
        toggle.setAttribute('aria-label', isOpen ? '关闭菜单' : '打开菜单');
        const toggleText = toggle.querySelector('.sr-only');
        if (toggleText) toggleText.textContent = isOpen ? '关闭菜单' : '打开菜单';
        if (isOpen) {
            requestAnimationFrame(() => closeBtn?.focus());
        } else {
            toggle.focus();
        }
    };
    const isOpen = () => !menu.hasAttribute('hidden');

    setMenuState(false);
    toggle.addEventListener('click', () => setMenuState(!isOpen()));
    closeBtn?.addEventListener('click', () => setMenuState(false));
    menu.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => setMenuState(false)));

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && isOpen()) {
            event.preventDefault();
            setMenuState(false);
        }
    });

    document.addEventListener('click', (event) => {
        if (isOpen() && !menu.contains(event.target) && !toggle.contains(event.target)) setMenuState(false);
    });
}

// ── Reader Controls ──
function initReaderControls() {
    const reader = document.querySelector('[data-reader-copy]');
    if (!reader) return;
    const sizeKey = 'yuejing-reader-size';
    const nightKey = 'yuejing-reader-night';
    const minSize = 14;
    const maxSize = 26;
    let size = Number.parseInt(reader.dataset.fontSize, 10) || Number.parseInt(getComputedStyle(reader).fontSize, 10) || 18;

    try {
        const storedSize = Number.parseInt(localStorage.getItem(sizeKey), 10);
        if (Number.isFinite(storedSize)) size = storedSize;
        if (localStorage.getItem(nightKey) === 'true') document.body.classList.add('reader-night');
    } catch {
        // Reader preferences remain available for the current page.
    }

    const status = document.querySelector('[data-reader-status]');
    const announce = (message) => {
        if (status) status.textContent = message;
    };
    const setSize = (nextSize) => {
        size = Math.min(maxSize, Math.max(minSize, Number(nextSize) || 18));
        reader.style.fontSize = `${size}px`;
        reader.dataset.fontSize = String(size);
        document.querySelectorAll('[data-reader-size]').forEach((button) => {
            const isDecrease = button.dataset.readerSize === 'decrease';
            button.disabled = isDecrease ? size <= minSize : size >= maxSize;
            button.setAttribute('aria-disabled', String(button.disabled));
        });
        announce(`字号 ${size} 像素`);
        try { localStorage.setItem(sizeKey, String(size)); } catch { /* Ignore unavailable storage. */ }
    };

    document.querySelectorAll('[data-reader-size]').forEach((button) => {
        button.addEventListener('click', () => setSize(size + (button.dataset.readerSize === 'increase' ? 2 : -2)));
    });
    setSize(size);

    const themeButton = document.querySelector('[data-reader-theme]');
    themeButton?.addEventListener('click', () => {
        document.body.classList.toggle('reader-night');
        const isNight = document.body.classList.contains('reader-night');
        themeButton.setAttribute('aria-pressed', String(isNight));
        announce(isNight ? '已开启阅读夜间模式' : '已关闭阅读夜间模式');
        try { localStorage.setItem(nightKey, String(isNight)); } catch { /* Ignore unavailable storage. */ }
    });
    themeButton?.setAttribute('aria-pressed', String(document.body.classList.contains('reader-night')));
}

// ── Toast Dismiss ──
function initToastDismiss() {
    document.querySelectorAll('[data-toast-dismiss]').forEach((btn) => {
        btn.addEventListener('click', () => btn.closest('.toast')?.remove());
    });
}

// ── Bootstrap ──
document.addEventListener('DOMContentLoaded', () => {
    new ThemeManager();
    initMobileMenu();
    initReaderControls();
    initToastDismiss();
});

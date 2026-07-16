// ── Theme Manager ──
class ThemeManager {
    constructor() {
        this.STORAGE_KEY = 'yuejing-theme';
        this.currentTheme = this.getStoredTheme() || 'system';
        this.applyTheme(this.currentTheme);
        this.initializeToggles();
        this.listenToSystemChanges();
    }

    getStoredTheme() { return localStorage.getItem(this.STORAGE_KEY); }

    getSystemTheme() {
        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    applyTheme(theme) {
        if (theme === 'system') {
            document.documentElement.removeAttribute('data-theme');
            localStorage.removeItem(this.STORAGE_KEY);
        } else {
            document.documentElement.setAttribute('data-theme', theme);
            localStorage.setItem(this.STORAGE_KEY, theme);
        }
        this.currentTheme = theme;
        this.updateToggleUI();
    }

    initializeToggles() {
        document.querySelectorAll('[data-theme-action]').forEach(btn => {
            btn.addEventListener('click', () => {
                const theme = btn.dataset.themeAction;
                this.applyTheme(theme);
            });
        });
    }

    updateToggleUI() {
        document.querySelectorAll('[data-theme-action]').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.themeAction === this.currentTheme);
        });
    }

    listenToSystemChanges() {
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
            if (this.currentTheme === 'system') {
                // Visual update happens via CSS media query automatically — just refresh toggle UI
                this.updateToggleUI();
            }
        });
    }
}

// ── Mobile Menu ──
function initMobileMenu() {
    const toggle = document.querySelector('[data-menu-toggle]');
    const menu = document.querySelector('[data-mobile-menu]');
    if (!toggle || !menu) return;

    const open = () => {
        menu.removeAttribute('hidden');
        toggle.setAttribute('aria-expanded', 'true');
    };
    const close = () => {
        menu.setAttribute('hidden', '');
        toggle.setAttribute('aria-expanded', 'false');
    };

    toggle.addEventListener('click', () => {
        menu.hasAttribute('hidden') ? open() : close();
    });

    // Close button inside menu
    const closeBtn = menu.querySelector('[data-menu-close]');
    closeBtn?.addEventListener('click', close);

    // Close on nav link click
    menu.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', () => close());
    });

    // Close on Escape
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !menu.hasAttribute('hidden')) close();
    });

    // Close on outside click
    document.addEventListener('click', (e) => {
        if (!menu.hasAttribute('hidden') && !menu.contains(e.target) && e.target !== toggle && !toggle.contains(e.target)) {
            close();
        }
    });
}

// ── Reader Controls ──
function initReaderControls() {
    document.querySelectorAll('[data-reader-size]').forEach((button) => {
        button.addEventListener('click', () => {
            const reader = document.querySelector('[data-reader-copy]');
            if (!reader) return;
            const current = Number.parseInt(getComputedStyle(reader).fontSize, 10) || 18;
            const next = button.dataset.readerSize === 'increase' ? Math.min(current + 2, 26) : Math.max(current - 2, 14);
            reader.style.fontSize = `${next}px`;
            reader.dataset.fontSize = String(next);
        });
    });

    document.querySelector('[data-reader-theme]')?.addEventListener('click', (event) => {
        document.body.classList.toggle('reader-night');
        event.currentTarget.setAttribute('aria-pressed', document.body.classList.contains('reader-night'));
    });
}

// ── Toast Dismiss ──
function initToastDismiss() {
    document.querySelectorAll('[data-toast-dismiss]').forEach(btn => {
        btn.addEventListener('click', () => {
            btn.closest('.toast')?.remove();
        });
    });
}

// ── Bootstrap ──
document.addEventListener('DOMContentLoaded', () => {
    new ThemeManager();
    initMobileMenu();
    initReaderControls();
    initToastDismiss();
});

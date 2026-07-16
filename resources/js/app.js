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

    getSystemTheme() {
        return this.mediaQuery.matches ? 'dark' : 'light';
    }

    applyTheme(theme) {
        const nextTheme = this.validThemes.includes(theme) ? theme : 'system';

        if (nextTheme === 'system') {
            document.documentElement.removeAttribute('data-theme');
            try {
                localStorage.removeItem(this.STORAGE_KEY);
            } catch {
                // Storage may be unavailable in private browsing contexts.
            }
        } else {
            document.documentElement.setAttribute('data-theme', nextTheme);
            try {
                localStorage.setItem(this.STORAGE_KEY, nextTheme);
            } catch {
                // The current DOM theme still applies without persistent storage.
            }
        }

        this.currentTheme = nextTheme;
        this.updateToggleUI();
    }

    initializeToggles() {
        document.querySelectorAll('[data-theme-action]').forEach(btn => {
            btn.addEventListener('click', () => this.applyTheme(btn.dataset.themeAction));
        });
    }

    updateToggleUI() {
        document.querySelectorAll('[data-theme-action]').forEach(btn => {
            const isActive = btn.dataset.themeAction === this.currentTheme;
            btn.classList.toggle('active', isActive);
            btn.setAttribute('aria-checked', String(isActive));
        });
    }

    listenToSystemChanges() {
        this.mediaQuery.addEventListener('change', () => {
            if (this.currentTheme === 'system') this.updateToggleUI();
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

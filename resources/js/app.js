const menuToggle = document.querySelector('[data-menu-toggle]');
const mobileMenu = document.querySelector('[data-mobile-menu]');

menuToggle?.addEventListener('click', () => {
    const isOpen = menuToggle.getAttribute('aria-expanded') === 'true';
    menuToggle.setAttribute('aria-expanded', String(!isOpen));
    if (mobileMenu) mobileMenu.hidden = isOpen;
});

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

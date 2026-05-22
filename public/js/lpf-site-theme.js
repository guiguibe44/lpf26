/**
 * Thème global du site (clair / sombre) — non administrable, hors EasyAdmin.
 * Clair : html.lpf-classic-light — Sombre : html.dark (lpf-app-dark.css, lpf-auth-dark.css).
 */
(function () {
    const STORAGE_KEY = 'lpfSiteTheme';
    const BOUND_FLAG = 'lpfSiteThemeBound';
    const LIGHT = 'light';
    const DARK = 'dark';

    const META_COLOR = {
        light: '#f8fafc',
        dark: '#09090b',
    };

    function readStoredTheme() {
        try {
            const stored = localStorage.getItem(STORAGE_KEY);
            if (stored === LIGHT || stored === DARK) {
                return stored;
            }
        } catch (e) {
            /* ignore */
        }

        return LIGHT;
    }

    function persistTheme(theme) {
        try {
            localStorage.setItem(STORAGE_KEY, theme);
            localStorage.setItem('lpfTheme', theme === DARK ? 'classic' : 'classic-light');
            localStorage.setItem('lpfGtaTheme', '0');
        } catch (e) {
            /* ignore */
        }
    }

    function syncToggleUi(theme) {
        const isDark = theme === DARK;
        document.querySelectorAll('[data-lpf-site-theme-toggle]').forEach((btn) => {
            btn.setAttribute('aria-pressed', isDark ? 'true' : 'false');
            btn.setAttribute(
                'title',
                isDark ? 'Passer en mode clair' : 'Passer en mode sombre',
            );
            btn.setAttribute(
                'aria-label',
                isDark ? 'Passer en mode clair' : 'Passer en mode sombre',
            );
            const icon = btn.querySelector('[data-lpf-site-theme-icon]');
            if (icon) {
                icon.className = isDark ? 'ti ti-sun' : 'ti ti-moon';
            }
        });
    }

    function applyTheme(theme) {
        const resolved = theme === DARK ? DARK : LIGHT;
        const html = document.documentElement;
        const body = document.body;

        html.classList.remove('dark', 'lpf-classic-light', 'lpf-gta-theme', 'lpf-gta-miami');
        if (body) {
            body.classList.remove(
                'lpf-gta-theme-active',
                'lpf-gta-miami-active',
                'lpf-classic-light-active',
            );
        }

        if (resolved === DARK) {
            html.classList.add('dark');
        } else {
            html.classList.add('lpf-classic-light');
            if (body) {
                body.classList.add('lpf-classic-light-active');
            }
        }

        const meta = document.querySelector('meta[name="theme-color"]');
        if (meta) {
            meta.setAttribute('content', META_COLOR[resolved]);
        }

        syncToggleUi(resolved);
    }

    function toggleTheme() {
        const next = readStoredTheme() === DARK ? LIGHT : DARK;
        persistTheme(next);
        applyTheme(next);
    }

    function onClick(event) {
        const toggle = event.target.closest('[data-lpf-site-theme-toggle]');
        if (!toggle) {
            return;
        }
        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation();
        toggleTheme();
    }

    function bindEventsOnce() {
        if (document.documentElement.getAttribute(BOUND_FLAG) === '1') {
            return;
        }
        document.documentElement.setAttribute(BOUND_FLAG, '1');
        document.addEventListener('click', onClick, true);
    }

    function boot() {
        bindEventsOnce();
        const theme = readStoredTheme();
        persistTheme(theme);
        applyTheme(theme);
    }

    document.addEventListener('turbo:load', boot);
    document.addEventListener('DOMContentLoaded', boot);

    if (document.readyState !== 'loading') {
        boot();
    }

    window.lpfSiteTheme = {
        getTheme: readStoredTheme,
        setTheme: (theme) => {
            const resolved = theme === DARK ? DARK : LIGHT;
            persistTheme(resolved);
            applyTheme(resolved);
        },
        toggle: toggleTheme,
    };
})();

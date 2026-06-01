/**
 * Bascule clair / sombre (sidebar + pages auth).
 */
(function () {
    const SITE_STORAGE_KEY = 'lpfSiteTheme';
    const THEME_STORAGE_KEY = 'lpfTheme';
    const LEGACY_GTA_KEY = 'lpfGtaTheme';
    const BOUND_FLAG = 'lpfSiteThemeBound';
    const LIGHT = 'light';
    const DARK = 'dark';

    const META_COLOR = {
        light: '#f8fafc',
        dark: '#09090b',
    };

    function migrateLegacyTheme() {
        try {
            const stored = localStorage.getItem(THEME_STORAGE_KEY);
            const legacyGta = localStorage.getItem(LEGACY_GTA_KEY) === '1';
            if (stored === 'gta' || stored === 'miami' || legacyGta) {
                localStorage.setItem(THEME_STORAGE_KEY, 'classic-light');
                localStorage.setItem(SITE_STORAGE_KEY, LIGHT);
                localStorage.setItem(LEGACY_GTA_KEY, '0');
            }
        } catch (e) {
            /* ignore */
        }
    }

    function readStoredSiteTheme() {
        migrateLegacyTheme();
        try {
            const stored = localStorage.getItem(SITE_STORAGE_KEY);
            if (stored === LIGHT || stored === DARK) {
                return stored;
            }
            const lpf = localStorage.getItem(THEME_STORAGE_KEY);
            if (lpf === 'classic') {
                return DARK;
            }
            if (lpf === 'classic-light') {
                return LIGHT;
            }
        } catch (e) {
            /* ignore */
        }

        return LIGHT;
    }

    function persistSiteTheme(theme) {
        const resolved = theme === DARK ? DARK : LIGHT;
        try {
            localStorage.setItem(SITE_STORAGE_KEY, resolved);
            localStorage.setItem(THEME_STORAGE_KEY, resolved === DARK ? 'classic' : 'classic-light');
            localStorage.setItem(LEGACY_GTA_KEY, '0');
        } catch (e) {
            /* ignore */
        }
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

    function toggleTheme() {
        const next = readStoredSiteTheme() === DARK ? LIGHT : DARK;
        persistSiteTheme(next);
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
        const site = readStoredSiteTheme();
        persistSiteTheme(site);
        applyTheme(site);
    }

    document.addEventListener('turbo:load', boot);
    document.addEventListener('DOMContentLoaded', boot);

    if (document.readyState !== 'loading') {
        boot();
    }

    window.lpfSiteTheme = {
        getTheme: readStoredSiteTheme,
        setTheme: (theme) => {
            const resolved = theme === DARK ? DARK : LIGHT;
            persistSiteTheme(resolved);
            applyTheme(resolved);
        },
        toggle: toggleTheme,
    };
})();

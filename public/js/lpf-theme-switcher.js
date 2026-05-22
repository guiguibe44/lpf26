/**
 * Thèmes LPF'26 : classique sombre | classique clair | GTA.
 */
(function () {
    const STORAGE_KEY = 'lpfTheme';
    const SITE_THEME_KEY = 'lpfSiteTheme';
    const LEGACY_KEY = 'lpfGtaTheme';
    const VALID = ['classic', 'classic-light', 'gta', 'miami'];
    const PUBLIC_THEMES = ['classic', 'classic-light', 'gta'];
    const HIDDEN_THEMES = ['miami'];
    const DEFAULT_THEME = 'classic-light';

    const META = {
        classic: '#09090b',
        'classic-light': '#f8fafc',
        gta: '#0d0f0e',
        miami: '#f3e8ff',
    };

    function readStoredTheme() {
        try {
            const stored = localStorage.getItem(STORAGE_KEY);
            if (VALID.includes(stored)) {
                return stored;
            }
            const legacy = localStorage.getItem(LEGACY_KEY);
            if (legacy === '1') {
                return 'gta';
            }
            const site = localStorage.getItem(SITE_THEME_KEY);
            if (site === 'dark') {
                return 'classic';
            }
            if (site === 'light') {
                return 'classic-light';
            }
        } catch (e) {
            /* ignore */
        }

        return DEFAULT_THEME;
    }

    function readTheme() {
        const stored = readStoredTheme();
        if (PUBLIC_THEMES.includes(stored)) {
            return stored;
        }

        return DEFAULT_THEME;
    }

    function persistTheme(theme) {
        try {
            localStorage.setItem(STORAGE_KEY, theme);
            localStorage.setItem(LEGACY_KEY, theme === 'gta' || theme === 'miami' ? '1' : '0');
            if (theme === 'classic') {
                localStorage.setItem(SITE_THEME_KEY, 'dark');
            } else if (theme === 'classic-light') {
                localStorage.setItem(SITE_THEME_KEY, 'light');
            }
        } catch (e) {
            /* ignore */
        }
    }

    function setMetaThemeColor(theme) {
        const meta = document.querySelector('meta[name="theme-color"]');
        if (meta) {
            meta.setAttribute('content', META[theme] || META['classic-light']);
        }
    }

    function syncSwitcherUi(theme) {
        const uiTheme = PUBLIC_THEMES.includes(theme) ? theme : DEFAULT_THEME;
        document.querySelectorAll('[data-lpf-theme-option]').forEach((btn) => {
            if (btn.classList.contains('lpf-theme-switcher__option--hidden')) {
                return;
            }
            const value = btn.getAttribute('data-lpf-theme-option');
            const active = value === uiTheme;
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
    }

    function syncSiteThemeToggle(theme) {
        const isDark = theme === 'classic' || theme === 'gta';
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
        const resolved = VALID.includes(theme) ? theme : DEFAULT_THEME;
        const isGta = resolved === 'gta' || resolved === 'miami';
        const isMiami = resolved === 'miami';
        const isClassicLight = resolved === 'classic-light';
        const isDark = resolved === 'classic' || resolved === 'gta';

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

        if (isGta) {
            html.classList.add('dark', 'lpf-gta-theme');
            if (isMiami) {
                html.classList.add('lpf-gta-miami');
            }
            if (body) {
                body.classList.add('lpf-gta-theme-active');
                if (isMiami) {
                    body.classList.add('lpf-gta-miami-active');
                }
            }
        } else if (isClassicLight) {
            html.classList.add('lpf-classic-light');
            if (body) {
                body.classList.add('lpf-classic-light-active');
            }
        } else if (isDark) {
            html.classList.add('dark');
        }

        setMetaThemeColor(resolved);
        syncSwitcherUi(resolved);
        syncSiteThemeToggle(resolved);
    }

    function setTheme(theme) {
        if (!VALID.includes(theme)) {
            return;
        }
        persistTheme(theme);
        applyTheme(theme);
    }

    function onClick(event) {
        const btn = event.target.closest('[data-lpf-theme-option]');
        if (!btn || btn.classList.contains('lpf-theme-switcher__option--hidden')) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();

        const value = btn.getAttribute('data-lpf-theme-option');
        if (PUBLIC_THEMES.includes(value)) {
            setTheme(value);
        }
    }

    function init() {
        const stored = readStoredTheme();
        if (HIDDEN_THEMES.includes(stored)) {
            persistTheme(DEFAULT_THEME);
            applyTheme(DEFAULT_THEME);

            return;
        }
        applyTheme(stored);
    }

    document.addEventListener('click', onClick, true);
    document.addEventListener('DOMContentLoaded', init);
    document.addEventListener('turbo:load', init);
    document.addEventListener('turbo:render', init);

    if (document.readyState !== 'loading') {
        init();
    }

    window.lpfGtaTheme = {
        getTheme: readStoredTheme,
        getPublicTheme: readTheme,
        setTheme,
        applyTheme,
        isEnabled: () => readStoredTheme() === 'gta',
        enable: () => setTheme('gta'),
        disable: () => setTheme('classic'),
        toggle: () => {
            const t = readTheme();
            if (t === 'gta') {
                setTheme('classic-light');
            } else if (t === 'classic-light') {
                setTheme('classic');
            } else {
                setTheme('gta');
            }
        },
    };

    window.lpfTheme = window.lpfGtaTheme;
})();

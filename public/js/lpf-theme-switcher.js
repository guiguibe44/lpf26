/**
 * Thèmes LPF'26 — visibles : classique sombre | classique clair.
 * GTA / Miami conservés (masqués dans le switcher, réactivables via setTheme).
 */
(function () {
    const STORAGE_KEY = 'lpfTheme';
    const LEGACY_KEY = 'lpfGtaTheme';
    const VALID = ['classic', 'classic-light', 'gta', 'miami'];
    const PUBLIC_THEMES = ['classic', 'classic-light'];
    const HIDDEN_THEMES = ['gta', 'miami'];
    const DEFAULT_THEME = 'classic';

    const META = {
        classic: '#09090b',
        'classic-light': '#f8fafc',
        gta: '#050505',
        miami: '#f3e8ff',
    };

    function readStoredTheme() {
        try {
            const stored = localStorage.getItem(STORAGE_KEY);
            if (VALID.includes(stored)) {
                return stored;
            }
            const legacy = localStorage.getItem(LEGACY_KEY);
            if (legacy === '0') {
                return 'classic';
            }
            if (legacy === '1') {
                return 'gta';
            }
        } catch (e) {
            /* ignore */
        }
        return DEFAULT_THEME;
    }

    /** Thème affiché dans le switcher (GTA/Miami → classique sombre tant qu’ils sont masqués). */
    function readTheme() {
        const stored = readStoredTheme();
        if (PUBLIC_THEMES.includes(stored)) {
            return stored;
        }
        if (HIDDEN_THEMES.includes(stored)) {
            return DEFAULT_THEME;
        }
        return DEFAULT_THEME;
    }

    function persistTheme(theme) {
        try {
            localStorage.setItem(STORAGE_KEY, theme);
            localStorage.setItem(LEGACY_KEY, theme === 'classic' || theme === 'classic-light' ? '0' : '1');
        } catch (e) {
            /* ignore */
        }
    }

    function setMetaThemeColor(theme) {
        const meta = document.querySelector('meta[name="theme-color"]');
        if (meta) {
            meta.setAttribute('content', META[theme] || META.classic);
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

    function applyTheme(theme) {
        const resolved = VALID.includes(theme) ? theme : DEFAULT_THEME;
        const isGta = resolved === 'gta' || resolved === 'miami';
        const isMiami = resolved === 'miami';
        const isClassicLight = resolved === 'classic-light';
        const isDark = resolved === 'classic' || resolved === 'gta';

        const html = document.documentElement;
        html.classList.toggle('dark', isDark);
        html.classList.toggle('lpf-classic-light', isClassicLight);
        html.classList.toggle('lpf-gta-theme', isGta);
        html.classList.toggle('lpf-gta-miami', isMiami);

        document.body.classList.toggle('lpf-gta-theme-active', isGta);
        document.body.classList.toggle('lpf-gta-miami-active', isMiami);
        document.body.classList.toggle('lpf-classic-light-active', isClassicLight);

        setMetaThemeColor(resolved);
        syncSwitcherUi(resolved);
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
        isEnabled: () => HIDDEN_THEMES.includes(readStoredTheme()),
        enable: () => setTheme('gta'),
        disable: () => setTheme('classic'),
        toggle: () => {
            const t = readTheme();
            setTheme(t === 'classic-light' ? 'classic' : 'classic-light');
        },
    };

    window.lpfTheme = window.lpfGtaTheme;
})();

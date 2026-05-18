/**
 * Thème unique : classique clair (switcher désactivé pour l’instant).
 */
(function () {
    const THEME = 'classic-light';
    const STORAGE_KEY = 'lpfTheme';
    const LEGACY_KEY = 'lpfGtaTheme';
    const META_COLOR = '#f8fafc';

    function applyTheme() {
        const html = document.documentElement;
        html.classList.remove('dark', 'lpf-gta-theme', 'lpf-gta-miami');
        html.classList.add('lpf-classic-light');

        document.body.classList.remove('lpf-gta-theme-active', 'lpf-gta-miami-active');
        document.body.classList.add('lpf-classic-light-active');

        const meta = document.querySelector('meta[name="theme-color"]');
        if (meta) {
            meta.setAttribute('content', META_COLOR);
        }
    }

    function persistTheme() {
        try {
            localStorage.setItem(STORAGE_KEY, THEME);
            localStorage.setItem(LEGACY_KEY, '0');
        } catch (e) {
            /* ignore */
        }
    }

    function init() {
        persistTheme();
        applyTheme();
    }

    document.addEventListener('DOMContentLoaded', init);
    document.addEventListener('turbo:load', init);
    document.addEventListener('turbo:render', init);

    if (document.readyState !== 'loading') {
        init();
    }

    window.lpfTheme = {
        getTheme: () => THEME,
        getPublicTheme: () => THEME,
        setTheme: () => {
            persistTheme();
            applyTheme();
        },
    };
    window.lpfGtaTheme = window.lpfTheme;
})();

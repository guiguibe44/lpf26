/**
 * Thème de zone principale (main.ta-main) — codes et défaut fournis par le serveur (BDD / admin).
 */
(function () {
    const STORAGE_KEY = 'lpfMainTheme';

    function readConfig() {
        const html = document.documentElement;
        const codesRaw = html.getAttribute('data-lpf-main-theme-codes');
        const defaultRaw = html.getAttribute('data-lpf-main-theme-default');
        let codes = [];
        let defaultCode = 'default';

        try {
            if (codesRaw) {
                codes = JSON.parse(codesRaw);
            }
            if (defaultRaw) {
                defaultCode = JSON.parse(defaultRaw);
            }
        } catch (e) {
            /* ignore */
        }

        if (!Array.isArray(codes) || codes.length === 0) {
            codes = [defaultCode];
        }

        if (!codes.includes(defaultCode)) {
            defaultCode = codes[0];
        }

        return { codes, defaultCode };
    }

    function readStoredTheme(config) {
        try {
            const stored = localStorage.getItem(STORAGE_KEY);
            if (config.codes.includes(stored)) {
                return stored;
            }
        } catch (e) {
            /* ignore */
        }

        return config.defaultCode;
    }

    function persistTheme(theme) {
        try {
            localStorage.setItem(STORAGE_KEY, theme);
        } catch (e) {
            /* ignore */
        }
    }

    function applyTheme(theme, config) {
        const resolved = config.codes.includes(theme) ? theme : config.defaultCode;
        document.documentElement.setAttribute('data-lpf-main-theme', resolved);
        syncSwitcherUi(resolved);
    }

    function syncSwitcherUi(theme) {
        document.querySelectorAll('[data-lpf-main-theme-option]').forEach((btn) => {
            const value = btn.getAttribute('data-lpf-main-theme-option');
            const active = value === theme;
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-checked', active ? 'true' : 'false');
        });
    }

    function getPanel() {
        return document.querySelector('[data-lpf-main-theme-panel]');
    }

    function getTrigger() {
        return document.querySelector('[data-lpf-main-theme-toggle]');
    }

    function closePanel() {
        const panel = getPanel();
        const trigger = getTrigger();
        if (!panel || !trigger) {
            return;
        }
        panel.hidden = true;
        trigger.setAttribute('aria-expanded', 'false');
    }

    function openPanel() {
        const panel = getPanel();
        const trigger = getTrigger();
        if (!panel || !trigger) {
            return;
        }
        panel.hidden = false;
        trigger.setAttribute('aria-expanded', 'true');
    }

    function togglePanel() {
        const panel = getPanel();
        if (!panel) {
            return;
        }
        if (panel.hidden) {
            openPanel();
        } else {
            closePanel();
        }
    }

    function setTheme(theme, config) {
        if (!config.codes.includes(theme)) {
            return;
        }
        persistTheme(theme);
        applyTheme(theme, config);
        closePanel();
    }

    function onDocumentClick(event) {
        const root = event.target.closest('[data-lpf-main-theme-switcher]');
        if (!root) {
            closePanel();
        }
    }

    function onClick(event, config) {
        const toggle = event.target.closest('[data-lpf-main-theme-toggle]');
        if (toggle) {
            event.preventDefault();
            event.stopPropagation();
            togglePanel();
            return;
        }

        const option = event.target.closest('[data-lpf-main-theme-option]');
        if (option) {
            event.preventDefault();
            event.stopPropagation();
            setTheme(option.getAttribute('data-lpf-main-theme-option'), config);
        }
    }

    function onKeydown(event) {
        if (event.key === 'Escape') {
            closePanel();
        }
    }

    function init() {
        const config = readConfig();
        applyTheme(readStoredTheme(config), config);
    }

    const config = readConfig();

    document.addEventListener('click', (e) => onClick(e, config), true);
    document.addEventListener('click', onDocumentClick);
    document.addEventListener('keydown', onKeydown);
    document.addEventListener('DOMContentLoaded', init);
    document.addEventListener('turbo:load', init);
    document.addEventListener('turbo:render', init);

    if (document.readyState !== 'loading') {
        init();
    }

    window.lpfMainTheme = {
        getTheme: () => readStoredTheme(readConfig()),
        setTheme: (theme) => setTheme(theme, readConfig()),
        getConfig: readConfig,
    };
})();

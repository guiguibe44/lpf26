/**
 * Thème de zone principale (main.ta-main) — codes et défaut fournis par le serveur (BDD / admin).
 * Écouteurs enregistrés une seule fois (compatible Turbo Drive).
 */
(function () {
    const STORAGE_KEY = 'lpfMainTheme';
    const BOUND_FLAG = 'lpfMainThemeSwitcherBound';

    /** @type {((event: MouseEvent) => void)|null} */
    let outsideClickListener = null;

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

    function getPanel() {
        return document.querySelector('[data-lpf-main-theme-panel]');
    }

    function getTrigger() {
        return document.querySelector('[data-lpf-main-theme-toggle]');
    }

    function isPanelOpen() {
        const panel = getPanel();
        return !!panel && !panel.hidden;
    }

    function syncSwitcherUi(theme) {
        document.querySelectorAll('[data-lpf-main-theme-option]').forEach((btn) => {
            const value = btn.getAttribute('data-lpf-main-theme-option');
            const active = value === theme;
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-checked', active ? 'true' : 'false');
        });
    }

    function applyTheme(theme) {
        const config = readConfig();
        const resolved = config.codes.includes(theme) ? theme : config.defaultCode;
        document.documentElement.setAttribute('data-lpf-main-theme', resolved);
        syncSwitcherUi(resolved);
    }

    function detachOutsideClickListener() {
        if (!outsideClickListener) {
            return;
        }
        document.removeEventListener('click', outsideClickListener, true);
        outsideClickListener = null;
    }

    function attachOutsideClickListener() {
        detachOutsideClickListener();
        outsideClickListener = (event) => {
            if (event.target.closest('[data-lpf-main-theme-switcher]')) {
                return;
            }
            closePanel();
        };
        // Après le clic d’ouverture : évite la fermeture immédiate sur le même événement.
        window.setTimeout(() => {
            if (isPanelOpen() && outsideClickListener) {
                document.addEventListener('click', outsideClickListener, true);
            }
        }, 0);
    }

    function closePanel() {
        const panel = getPanel();
        const trigger = getTrigger();
        if (!panel || !trigger) {
            detachOutsideClickListener();
            return;
        }
        panel.hidden = true;
        trigger.setAttribute('aria-expanded', 'false');
        detachOutsideClickListener();
    }

    function openPanel() {
        const panel = getPanel();
        const trigger = getTrigger();
        if (!panel || !trigger) {
            return;
        }
        panel.hidden = false;
        trigger.setAttribute('aria-expanded', 'true');
        attachOutsideClickListener();
    }

    function togglePanel() {
        if (isPanelOpen()) {
            closePanel();
        } else {
            openPanel();
        }
    }

    function setTheme(theme) {
        const config = readConfig();
        if (!config.codes.includes(theme)) {
            return;
        }
        persistTheme(theme);
        applyTheme(theme);
        closePanel();
    }

    function onDocumentClick(event) {
        const toggle = event.target.closest('[data-lpf-main-theme-toggle]');
        if (toggle) {
            event.preventDefault();
            event.stopPropagation();
            event.stopImmediatePropagation();
            togglePanel();
            return;
        }

        const option = event.target.closest('[data-lpf-main-theme-option]');
        if (option) {
            event.preventDefault();
            event.stopPropagation();
            event.stopImmediatePropagation();
            setTheme(option.getAttribute('data-lpf-main-theme-option') || '');
        }
    }

    function onKeydown(event) {
        if (event.key === 'Escape' && isPanelOpen()) {
            event.stopPropagation();
            closePanel();
        }
    }

    function syncFromStorage() {
        const config = readConfig();
        applyTheme(readStoredTheme(config));
        closePanel();
    }

    function bindEventsOnce() {
        if (document.documentElement.getAttribute(BOUND_FLAG) === '1') {
            return;
        }
        document.documentElement.setAttribute(BOUND_FLAG, '1');
        document.addEventListener('click', onDocumentClick, true);
        document.addEventListener('keydown', onKeydown, true);
    }

    function boot() {
        bindEventsOnce();
        syncFromStorage();
    }

    document.addEventListener('turbo:load', boot);
    document.addEventListener('DOMContentLoaded', boot);

    if (document.readyState !== 'loading') {
        boot();
    }

    window.lpfMainTheme = {
        getTheme: () => readStoredTheme(readConfig()),
        setTheme: (theme) => setTheme(theme),
        getConfig: readConfig,
        closePanel,
    };
})();

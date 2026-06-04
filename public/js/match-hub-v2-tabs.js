(() => {
    const root = document.querySelector('[data-match-hub-v2]');
    if (!root) {
        return;
    }

    const tabs = root.querySelectorAll('[data-match-hub-tab]');
    const panels = root.querySelectorAll('[data-match-hub-panel]');

    const activate = (tabId) => {
        tabs.forEach((tab) => {
            const selected = tab.getAttribute('data-match-hub-tab') === tabId;
            tab.setAttribute('aria-selected', selected ? 'true' : 'false');
        });
        panels.forEach((panel) => {
            const show = panel.getAttribute('data-match-hub-panel') === tabId;
            panel.hidden = !show;
        });
    };

    tabs.forEach((tab) => {
        tab.addEventListener('click', () => {
            const tabId = tab.getAttribute('data-match-hub-tab');
            if (tabId) {
                activate(tabId);
            }
        });
    });
})();

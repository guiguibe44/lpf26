(function () {
    const DIALOG_ID = 'joker-guide-detail-dialog';

    const getDialog = () => document.getElementById(DIALOG_ID);

    const openDetail = (index) => {
        const dialog = getDialog();
        if (!dialog) {
            return;
        }

        const template = document.getElementById('joker-guide-detail-' + index);
        const titleEl = dialog.querySelector('.joker-guide-detail-dialog__title');
        const heroEl = dialog.querySelector('[data-joker-detail-hero]');
        const badgesEl = dialog.querySelector('[data-joker-detail-badges]');
        const bodyEl = dialog.querySelector('[data-joker-detail-body]');

        if (!template || !titleEl || !bodyEl) {
            return;
        }

        const root =
            template.content.querySelector('.joker-guide-detail-template') ||
            template.content.firstElementChild;

        if (!root) {
            return;
        }

        titleEl.textContent = root.dataset.jokerTitle || '';

        if (heroEl) {
            heroEl.innerHTML = '';
            const heroSource = root.querySelector('.joker-guide-detail-template__hero');
            if (heroSource) {
                heroEl.appendChild(heroSource.cloneNode(true));
            }
        }

        if (badgesEl) {
            badgesEl.innerHTML = '';
            const badgesSource = root.querySelector('.joker-guide-detail-template__badges');
            if (badgesSource) {
                badgesEl.appendChild(badgesSource.cloneNode(true));
            }
        }

        bodyEl.innerHTML = '';
        const contentSource = root.querySelector('.joker-guide-detail-template__content');
        if (contentSource) {
            bodyEl.appendChild(contentSource.cloneNode(true));
        }

        if (typeof dialog.showModal === 'function') {
            dialog.showModal();
        }
    };

    const closeDialog = () => {
        const dialog = getDialog();
        if (dialog?.open) {
            dialog.close();
        }
    };

    const bindDialogChrome = () => {
        const dialog = getDialog();
        if (!dialog || dialog.dataset.jokerCatalogBound === '1') {
            return;
        }

        dialog.dataset.jokerCatalogBound = '1';

        dialog.querySelector('[data-joker-detail-close]')?.addEventListener('click', closeDialog);

        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) {
                closeDialog();
            }
        });

        dialog.addEventListener('cancel', (event) => {
            event.preventDefault();
            closeDialog();
        });
    };

    const onDocumentClick = (event) => {
        const btn = event.target.closest('[data-joker-open]');
        if (!btn) {
            return;
        }

        event.preventDefault();
        openDetail(btn.getAttribute('data-joker-open') || '0');
    };

    const init = () => {
        if (!document.querySelector('[data-joker-catalog]')) {
            return;
        }

        bindDialogChrome();
    };

    document.addEventListener('click', onDocumentClick);
    document.addEventListener('DOMContentLoaded', init);
    document.addEventListener('turbo:load', init);
    document.addEventListener('turbo:render', init);

    if (document.readyState !== 'loading') {
        init();
    }
})();

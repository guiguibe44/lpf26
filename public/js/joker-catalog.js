(function () {
    const DIALOG_ID = 'joker-catalog-lightbox';

    const getDialog = () => document.getElementById(DIALOG_ID);

    const closeLightbox = () => {
        const dialog = getDialog();
        if (dialog?.open) {
            dialog.close();
        }
    };

    const openLightbox = (src, title) => {
        const dialog = getDialog();
        if (!dialog || !src) {
            return;
        }

        const img = dialog.querySelector('[data-joker-lightbox-image]');
        const caption = dialog.querySelector('[data-joker-lightbox-caption]');

        if (img) {
            img.src = src;
            img.alt = title || '';
        }

        if (caption) {
            caption.textContent = title || '';
            caption.hidden = !title;
        }

        if (typeof dialog.showModal === 'function') {
            dialog.showModal();
        }
    };

    const bindLightbox = () => {
        const dialog = getDialog();
        if (!dialog || dialog.dataset.jokerLightboxBound === '1') {
            return;
        }

        dialog.dataset.jokerLightboxBound = '1';

        dialog.querySelector('[data-joker-lightbox-close]')?.addEventListener('click', closeLightbox);

        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) {
                closeLightbox();
            }
        });

        dialog.addEventListener('cancel', (event) => {
            event.preventDefault();
            closeLightbox();
        });
    };

    const onDocumentClick = (event) => {
        const trigger = event.target.closest('[data-joker-lightbox-open]');
        if (!trigger) {
            return;
        }

        event.preventDefault();
        openLightbox(
            trigger.getAttribute('data-joker-lightbox-src') || '',
            trigger.getAttribute('data-joker-lightbox-title') || '',
        );
    };

    const init = () => {
        if (!getDialog()) {
            return;
        }

        bindLightbox();
    };

    document.addEventListener('click', onDocumentClick);
    document.addEventListener('DOMContentLoaded', init);
    document.addEventListener('turbo:load', init);
    document.addEventListener('turbo:render', init);

    if (document.readyState !== 'loading') {
        init();
    }
})();

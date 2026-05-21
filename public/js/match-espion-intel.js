(function () {
    const DIALOG_ID = 'match-espion-intel-dialog';

    const getDialog = () => document.getElementById(DIALOG_ID);

    const closeDialog = () => {
        const dialog = getDialog();
        if (dialog?.open) {
            dialog.close();
        }
    };

    const openFromTrigger = (button) => {
        const wrap = button.closest('[data-match-espion]');
        const template = wrap?.querySelector('[data-espion-intel-template]');
        const dialog = getDialog();
        const body = dialog?.querySelector('[data-espion-dialog-body]');

        if (!wrap || !template || !dialog || !body) {
            return;
        }

        body.replaceChildren(template.content.cloneNode(true));
        if (typeof dialog.showModal === 'function') {
            dialog.showModal();
        }
    };

    document.addEventListener('click', (event) => {
        const openBtn = event.target.closest('[data-espion-open]');
        if (openBtn) {
            event.preventDefault();
            openFromTrigger(openBtn);
            return;
        }

        const closeBtn = event.target.closest('[data-espion-dialog-close]');
        if (closeBtn) {
            event.preventDefault();
            closeDialog();
            return;
        }

        const dialog = getDialog();
        if (dialog?.open && event.target === dialog) {
            closeDialog();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeDialog();
        }
    });
})();

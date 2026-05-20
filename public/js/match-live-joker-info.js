(function () {
    const DIALOG_ID = 'match-live-joker-info-dialog';

    const getDialog = () => document.getElementById(DIALOG_ID);

    const parseInfo = (raw) => {
        if (!raw) {
            return null;
        }

        try {
            return JSON.parse(raw);
        } catch (e) {
            return null;
        }
    };

    const jokerImageUrl = (image) => {
        if (!image) {
            return null;
        }
        const path = String(image);
        if (path.startsWith('http://') || path.startsWith('https://')) {
            return path;
        }

        return '/' + path.replace(/^\//, '');
    };

    const closeDialog = () => {
        const dialog = getDialog();
        if (dialog?.open) {
            dialog.close();
        }
    };

    const openDialog = (info) => {
        const dialog = getDialog();
        if (!dialog || !info) {
            return;
        }

        const panel = dialog.querySelector('.match-live-joker-info-dialog__panel');
        const title = dialog.querySelector('[data-match-joker-info-title]');
        const context = dialog.querySelector('[data-match-joker-info-context]');
        const desc = dialog.querySelector('[data-match-joker-info-desc]');
        const technicalWrap = dialog.querySelector('[data-match-joker-info-technical-wrap]');
        const technicalList = dialog.querySelector('[data-match-joker-info-technical]');
        const visualWrap = dialog.querySelector('[data-match-joker-info-visual]');
        const imageEl = dialog.querySelector('[data-match-joker-info-image]');
        const placeholderEl = dialog.querySelector('[data-match-joker-info-placeholder]');

        const imageSrc = jokerImageUrl(info.image);
        if (visualWrap) {
            if (imageSrc && imageEl) {
                imageEl.src = imageSrc;
                imageEl.hidden = false;
                if (placeholderEl) {
                    placeholderEl.hidden = true;
                }
                visualWrap.hidden = false;
            } else if (placeholderEl) {
                if (imageEl) {
                    imageEl.removeAttribute('src');
                    imageEl.hidden = true;
                }
                placeholderEl.hidden = false;
                visualWrap.hidden = false;
            } else {
                visualWrap.hidden = true;
            }
        }

        if (title) {
            title.textContent = String(info.name || 'Joker');
        }

        if (context) {
            const label = String(info.label || '').trim();
            if (label) {
                context.textContent = label;
                context.hidden = false;
            } else {
                context.hidden = true;
                context.textContent = '';
            }
        }

        if (desc) {
            const description = String(info.description || '').trim();
            if (description) {
                desc.textContent = description;
                desc.hidden = false;
            } else {
                desc.hidden = true;
                desc.textContent = '';
            }
        }

        if (technicalList && technicalWrap) {
            technicalList.replaceChildren();
            const lines = Array.isArray(info.technical_lines) ? info.technical_lines : [];
            if (lines.length > 0) {
                lines.forEach((line) => {
                    const text = String(line || '').trim();
                    if ('' === text) {
                        return;
                    }
                    const li = document.createElement('li');
                    li.textContent = text;
                    technicalList.appendChild(li);
                });
                technicalWrap.hidden = false;
            } else {
                technicalWrap.hidden = true;
            }
        }

        if (typeof dialog.showModal === 'function') {
            dialog.showModal();
        }

        if (panel) {
            panel.focus({ preventScroll: true });
        }
    };

    const bindDialog = () => {
        const dialog = getDialog();
        if (!dialog) {
            return;
        }

        const panel = dialog.querySelector('.match-live-joker-info-dialog__panel');
        const closeBtn = dialog.querySelector('[data-match-joker-info-close]');

        if (dialog.dataset.matchJokerInfoBound !== '1') {
            dialog.dataset.matchJokerInfoBound = '1';

            closeBtn?.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                closeDialog();
            });

            dialog.addEventListener('click', (event) => {
                if (event.target === dialog) {
                    closeDialog();
                }
            });

            dialog.addEventListener('cancel', (event) => {
                event.preventDefault();
                closeDialog();
            });

            panel?.addEventListener('click', (event) => {
                event.stopPropagation();
            });
        }
    };

    const handleDocumentClick = (event) => {
        if (event.target.closest('[data-match-joker-info-close]')) {
            event.preventDefault();
            event.stopPropagation();
            closeDialog();

            return;
        }

        const dialog = getDialog();
        if (dialog?.open) {
            if (event.target === dialog) {
                event.preventDefault();
                closeDialog();
            }

            return;
        }

        const trigger = event.target.closest('[data-match-joker-info-open]');
        if (!trigger || trigger.disabled) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();

        const info = parseInfo(trigger.getAttribute('data-joker-info'));
        if (info) {
            openDialog(info);
        }
    };

    const init = () => {
        bindDialog();
    };

    document.addEventListener('click', handleDocumentClick, true);
    document.addEventListener('DOMContentLoaded', init);
    document.addEventListener('turbo:load', init);
})();

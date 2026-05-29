(function () {
    'use strict';

    const init = () => {
        const root = document.querySelector('[data-buteur-pick-root]');
        if (!root || root.dataset.buteurPickEnabled !== 'true') {
            return;
        }

        const dialog = document.getElementById('buteur-pick-dialog');
        if (!dialog || typeof dialog.showModal !== 'function') {
            return;
        }

        if (root.dataset.buteurPickBound === '1') {
            return;
        }
        root.dataset.buteurPickBound = '1';

        const titleEl = document.getElementById('buteur-pick-dialog-title');
        const messageEl = document.getElementById('buteur-pick-dialog-message');
        const playerNameEl = document.getElementById('buteur-pick-dialog-player-name');
        const photoEl = document.getElementById('buteur-pick-dialog-photo');
        const photoFallbackEl = document.getElementById('buteur-pick-dialog-photo-fallback');
        const inputIdEl = document.getElementById('buteur-pick-dialog-input-id');
        const confirmBtn = document.getElementById('buteur-pick-dialog-confirm');
        const formEl = document.getElementById('buteur-pick-dialog-form');

        const currentId = parseInt(root.dataset.buteurPickCurrentId ?? '0', 10) || 0;
        const currentName = (root.dataset.buteurPickCurrentName ?? '').trim();

        const updatePhoto = (url, prenom, nom) => {
            const initials = `${(prenom || '').slice(0, 1)}${(nom || '').slice(0, 1)}`.toUpperCase() || '?';
            if (url && photoEl) {
                photoEl.src = url;
                photoEl.hidden = false;
                if (photoFallbackEl) {
                    photoFallbackEl.hidden = true;
                }
                return;
            }
            if (photoEl) {
                photoEl.hidden = true;
                photoEl.removeAttribute('src');
            }
            if (photoFallbackEl) {
                photoFallbackEl.textContent = initials;
                photoFallbackEl.hidden = false;
            }
        };

        const closeDialog = () => {
            if (dialog.open) {
                dialog.close();
            }
        };

        const openPicker = (el) => {
            const id = parseInt(el.dataset.buteurId ?? '', 10);
            if (!id || !inputIdEl) {
                return;
            }

            const prenom = (el.dataset.buteurPrenom ?? '').trim();
            const nom = (el.dataset.buteurNom ?? '').trim();
            const fullName = `${prenom} ${nom}`.trim() || nom || 'Ce joueur';
            const photoUrl = (el.dataset.buteurPhoto ?? '').trim();

            inputIdEl.value = String(id);
            if (playerNameEl) {
                playerNameEl.textContent = fullName;
            }
            updatePhoto(photoUrl, prenom, nom);

            const isSame = currentId > 0 && currentId === id;
            if (isSame) {
                if (titleEl) {
                    titleEl.textContent = 'Buteur actuel';
                }
                if (messageEl) {
                    messageEl.textContent = `${fullName} est déjà votre buteur.`;
                }
                if (confirmBtn) {
                    confirmBtn.disabled = true;
                    confirmBtn.textContent = 'Déjà sélectionné';
                }
            } else if (currentId > 0 && currentName) {
                if (titleEl) {
                    titleEl.textContent = 'Changer de buteur';
                }
                if (messageEl) {
                    messageEl.textContent = `Remplacer ${currentName} par ${fullName} ?`;
                }
                if (confirmBtn) {
                    confirmBtn.disabled = false;
                    confirmBtn.textContent = 'Confirmer le changement';
                }
            } else {
                if (titleEl) {
                    titleEl.textContent = 'Choisir mon buteur';
                }
                if (messageEl) {
                    messageEl.textContent = `Sélectionner ${fullName} comme buteur ?`;
                }
                if (confirmBtn) {
                    confirmBtn.disabled = false;
                    confirmBtn.textContent = 'Confirmer';
                }
            }

            dialog.showModal();
        };

        root.addEventListener('click', (event) => {
            const el = event.target.closest('[data-buteur-id]');
            if (!el || !root.contains(el)) {
                return;
            }
            event.preventDefault();
            openPicker(el);
        });

        root.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter' && event.key !== ' ') {
                return;
            }
            const el = event.target.closest('[data-buteur-id]');
            if (!el || !root.contains(el)) {
                return;
            }
            event.preventDefault();
            openPicker(el);
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

        document.querySelectorAll('[data-buteur-pick-close]').forEach((btn) => {
            btn.addEventListener('click', (event) => {
                event.preventDefault();
                closeDialog();
            });
        });

        if (confirmBtn && formEl) {
            confirmBtn.addEventListener('click', (event) => {
                event.preventDefault();
                if (!confirmBtn.disabled) {
                    formEl.requestSubmit();
                }
            });
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    document.addEventListener('turbo:load', init);
})();

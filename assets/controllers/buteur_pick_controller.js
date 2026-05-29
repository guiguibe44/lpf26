/* stimulusFetch: 'eager' */
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        enabled: { type: Boolean, default: false },
        competitionStarted: { type: Boolean, default: false },
        currentId: { type: Number, default: 0 },
        currentName: { type: String, default: '' },
    };

    static targets = [
        'dialog',
        'form',
        'inputId',
        'title',
        'message',
        'playerName',
        'photo',
        'photoFallback',
        'confirmBtn',
    ];

    connect() {
        this._onDialogClick = this.onDialogClick.bind(this);
        if (this.hasDialogTarget) {
            this.dialogTarget.addEventListener('click', this._onDialogClick);
        }
    }

    disconnect() {
        if (this.hasDialogTarget && this._onDialogClick) {
            this.dialogTarget.removeEventListener('click', this._onDialogClick);
        }
    }

    /** Fermeture au clic sur le fond (backdrop) du &lt;dialog&gt;. */
    onDialogClick(event) {
        if (event.target === this.dialogTarget) {
            this.close();
        }
    }

    open(event) {
        if (!this.enabledValue || this.competitionStartedValue) {
            return;
        }

        const el = event.target.closest('[data-buteur-id]');
        if (!el || !this.element.contains(el)) {
            return;
        }

        event.preventDefault();

        const id = parseInt(el.dataset.buteurId ?? '', 10);
        if (!id) {
            return;
        }

        if (!this.hasDialogTarget || !this.hasInputIdTarget || !this.hasConfirmBtnTarget) {
            console.error('[buteur-pick] Dialog targets missing');

            return;
        }

        const prenom = (el.dataset.buteurPrenom ?? '').trim();
        const nom = (el.dataset.buteurNom ?? '').trim();
        const fullName = `${prenom} ${nom}`.trim() || nom || 'Ce joueur';
        const photoUrl = (el.dataset.buteurPhoto ?? '').trim();

        this.inputIdTarget.value = String(id);
        if (this.hasPlayerNameTarget) {
            this.playerNameTarget.textContent = fullName;
        }
        this.updatePhoto(photoUrl, prenom, nom);

        const isSame = this.currentIdValue > 0 && this.currentIdValue === id;
        if (isSame) {
            if (this.hasTitleTarget) {
                this.titleTarget.textContent = 'Buteur actuel';
            }
            if (this.hasMessageTarget) {
                this.messageTarget.textContent = `${fullName} est déjà votre buteur.`;
            }
            this.confirmBtnTarget.disabled = true;
            this.confirmBtnTarget.textContent = 'Déjà sélectionné';
        } else if (this.currentIdValue > 0 && this.currentNameValue) {
            if (this.hasTitleTarget) {
                this.titleTarget.textContent = 'Changer de buteur';
            }
            if (this.hasMessageTarget) {
                this.messageTarget.textContent = `Remplacer ${this.currentNameValue} par ${fullName} ?`;
            }
            this.confirmBtnTarget.disabled = false;
            this.confirmBtnTarget.textContent = 'Confirmer le changement';
        } else {
            if (this.hasTitleTarget) {
                this.titleTarget.textContent = 'Choisir mon buteur';
            }
            if (this.hasMessageTarget) {
                this.messageTarget.textContent = `Sélectionner ${fullName} comme buteur ?`;
            }
            this.confirmBtnTarget.disabled = false;
            this.confirmBtnTarget.textContent = 'Confirmer';
        }

        this.dialogTarget.showModal();
    }

    openKey(event) {
        if (event.key !== 'Enter' && event.key !== ' ') {
            return;
        }
        const el = event.target.closest('[data-buteur-id]');
        if (!el) {
            return;
        }
        event.preventDefault();
        this.open(event);
    }

    close(event) {
        if (event) {
            event.preventDefault();
        }
        if (this.hasDialogTarget && this.dialogTarget.open) {
            this.dialogTarget.close();
        }
    }

    confirm(event) {
        event.preventDefault();
        if (!this.hasConfirmBtnTarget || this.confirmBtnTarget.disabled || !this.hasFormTarget) {
            return;
        }
        this.formTarget.requestSubmit();
    }

    updatePhoto(url, prenom, nom) {
        const initials = `${(prenom || '').slice(0, 1)}${(nom || '').slice(0, 1)}`.toUpperCase() || '?';

        if (url && this.hasPhotoTarget) {
            this.photoTarget.src = url;
            this.photoTarget.hidden = false;
            if (this.hasPhotoFallbackTarget) {
                this.photoFallbackTarget.hidden = true;
            }

            return;
        }

        if (this.hasPhotoTarget) {
            this.photoTarget.hidden = true;
            this.photoTarget.removeAttribute('src');
        }
        if (this.hasPhotoFallbackTarget) {
            this.photoFallbackTarget.textContent = initials;
            this.photoFallbackTarget.hidden = false;
        }
    }
}

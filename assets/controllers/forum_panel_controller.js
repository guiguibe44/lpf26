/* stimulusFetch: 'eager' */
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['frame'];

    static values = {
        panelUrl: String,
    };

    connect() {
        this.dialog = this.element.querySelector('#forum-panel-dialog');
        this.boundBackdropClick = (event) => {
            if (event.target === this.dialog) {
                this.close();
            }
        };
        if (window.location.hash.startsWith('#forum-open')) {
            const hash = window.location.hash;
            history.replaceState(null, '', window.location.pathname + window.location.search);
            this.open();
            if (hash.startsWith('#forum-open-forum-post-')) {
                const postId = hash.replace('#forum-open-forum-post-', '');
                this.scrollToPostWhenReady(postId);
            }
        }
    }

    open(event) {
        event?.preventDefault();
        if (!this.dialog) {
            return;
        }
        this.dialog.showModal();
        this.dialog.addEventListener('click', this.boundBackdropClick);
        this.loadPanel();
    }

    scrollToPostWhenReady(postId) {
        const tryScroll = () => {
            const el = this.frameTarget?.querySelector(`#forum-post-${postId}`);
            if (el) {
                el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                return;
            }
            setTimeout(tryScroll, 150);
        };
        setTimeout(tryScroll, 400);
    }

    close(event) {
        event?.preventDefault();
        if (!this.dialog) {
            return;
        }
        this.dialog.close();
        this.dialog.removeEventListener('click', this.boundBackdropClick);
    }

    loadPanel() {
        if (!this.hasFrameTarget || !this.panelUrlValue) {
            return;
        }

        if (this.frameTarget.getAttribute('src')) {
            if (typeof this.frameTarget.reload === 'function') {
                this.frameTarget.reload();
            } else {
                this.frameTarget.src = this.panelUrlValue;
            }
            return;
        }

        this.frameTarget.innerHTML = '<p class="forum-panel-dialog__loading ta-card-text">Chargement du forum…</p>';
        this.frameTarget.src = this.panelUrlValue;
    }

    onFrameMissing() {
        if (!this.hasFrameTarget) {
            return;
        }
        this.frameTarget.innerHTML = '<p class="ta-card-text">Impossible de charger le forum. <a href="' + this.panelUrlValue + '">Ouvrir la page forum</a>.</p>';
    }
}

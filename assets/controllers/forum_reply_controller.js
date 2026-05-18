/* stimulusFetch: 'eager' */
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['panel'];

    toggle(event) {
        const button = event.currentTarget;
        const controlsId = button.getAttribute('aria-controls');
        if (!controlsId) {
            return;
        }
        const panel = document.getElementById(controlsId);
        if (!panel) {
            return;
        }

        const willOpen = panel.hasAttribute('hidden');
        if (willOpen) {
            panel.removeAttribute('hidden');
            button.setAttribute('aria-expanded', 'true');
            const editor = panel.querySelector('[contenteditable]');
            editor?.focus();
        } else {
            panel.setAttribute('hidden', 'hidden');
            button.setAttribute('aria-expanded', 'false');
        }
    }

    confirmDelete(event) {
        if (!window.confirm('Supprimer ce message ? Cette action est définitive.')) {
            event.preventDefault();
        }
    }

    cancel(event) {
        event.preventDefault();
        const wrap = event.target.closest('.forum-collapsible-form-wrap');
        if (!wrap) {
            return;
        }
        wrap.setAttribute('hidden', 'hidden');
        const article = wrap.closest('.forum-post, .forum-reply');
        const controlsId = wrap.id;
        const toggle = article?.querySelector(`[aria-controls="${controlsId}"]`);
        toggle?.setAttribute('aria-expanded', 'false');
        const editor = wrap.querySelector('[contenteditable]');
        const input = wrap.querySelector('.forum-editor__hidden');
        if (editor) {
            editor.innerHTML = '';
        }
        if (input) {
            input.value = '';
        }
    }
}

/* stimulusFetch: 'eager' */
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['editor', 'input'];

    connect() {
        if (this.hasInputTarget && this.hasEditorTarget) {
            const initial = this.inputTarget.value;
            if (initial && !this.editorTarget.innerHTML.trim()) {
                this.editorTarget.innerHTML = initial;
            }
        }
        this.sync();
    }

    format(event) {
        event.preventDefault();
        const button = event.currentTarget;
        const command = button.dataset.command;
        const value = button.dataset.value || null;
        this.editorTarget.focus();
        document.execCommand(command, false, value);
        this.sync();
    }

    link(event) {
        event.preventDefault();
        const url = window.prompt('Adresse du lien (https://…)');
        if (!url || !url.trim()) {
            return;
        }
        let href = url.trim();
        if (!/^https?:\/\//i.test(href)) {
            href = `https://${href}`;
        }
        this.editorTarget.focus();
        document.execCommand('createLink', false, href);
        this.sync();
    }

    sync() {
        if (!this.hasInputTarget || !this.hasEditorTarget) {
            return;
        }
        this.normalizeEmojiImages();
        this.inputTarget.value = this.editorTarget.innerHTML;
    }

    onSubmit(event) {
        this.sync();
        if (!this.hasMeaningfulContent()) {
            event.preventDefault();
            this.editorTarget.focus();
            this.editorTarget.classList.add('forum-editor__surface--error');
        }
    }

    normalizeEmojiImages() {
        if (!this.hasEditorTarget) {
            return;
        }
        this.editorTarget.querySelectorAll('img[alt]').forEach((img) => {
            const alt = img.getAttribute('alt') ?? '';
            if (alt && /\p{Extended_Pictographic}/u.test(alt)) {
                img.replaceWith(document.createTextNode(alt));
            }
        });
    }

    hasMeaningfulContent() {
        const text = (this.editorTarget.textContent || '').replace(/\u00a0/g, ' ').trim();
        if (text) {
            return true;
        }

        return Array.from(this.editorTarget.querySelectorAll('img[alt]')).some((img) => {
            const alt = img.getAttribute('alt') ?? '';

            return alt && /\p{Extended_Pictographic}/u.test(alt);
        });
    }
}

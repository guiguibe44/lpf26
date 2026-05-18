/* stimulusFetch: 'eager' */
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['editor', 'input', 'list'];

    static values = {
        suggestionsUrl: String,
    };

    connect() {
        this.activeIndex = -1;
        this.suggestions = [];
        this.debounceTimer = null;
        this.mentionRange = null;
    }

    disconnect() {
        clearTimeout(this.debounceTimer);
    }

    keydown(event) {
        if (this.hasListTarget && !this.listTarget.hasAttribute('hidden')) {
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                this.activeIndex = Math.min(this.activeIndex + 1, this.suggestions.length - 1);
                this.highlightActive();
                return;
            }
            if (event.key === 'ArrowUp') {
                event.preventDefault();
                this.activeIndex = Math.max(this.activeIndex - 1, 0);
                this.highlightActive();
                return;
            }
            if (event.key === 'Enter' && this.activeIndex >= 0) {
                event.preventDefault();
                this.pick(this.suggestions[this.activeIndex]);
                return;
            }
            if (event.key === 'Escape') {
                event.preventDefault();
                this.hideList();
                return;
            }
        }

        if (event.key === '@') {
            setTimeout(() => this.detectMention(), 0);
        }
    }

    onInput() {
        clearTimeout(this.debounceTimer);
        this.debounceTimer = setTimeout(() => this.detectMention(), 100);
    }

    detectMention() {
        if (!this.hasEditorTarget || !this.suggestionsUrlValue) {
            return;
        }

        const selection = window.getSelection();
        if (!selection || selection.rangeCount === 0) {
            this.hideList();
            return;
        }

        const range = selection.getRangeAt(0);
        if (!this.editorTarget.contains(range.startContainer)) {
            this.hideList();
            return;
        }

        const textBefore = this.textBeforeCaret(range);
        const match = textBefore.match(/@([\p{L}\p{N}_-]*)$/u);
        if (!match) {
            this.hideList();
            return;
        }

        const query = match[1] ?? '';
        if (!this.saveMentionRange(range, query)) {
            this.hideList();
            return;
        }

        this.fetchSuggestions(query);
    }

    saveMentionRange(range, query) {
        try {
            this.mentionRange = range.cloneRange();
            const atLength = query.length + 1;
            const container = range.startContainer;
            const offset = range.startOffset;

            if (container.nodeType === Node.TEXT_NODE) {
                this.mentionRange.setStart(container, Math.max(0, offset - atLength));
                return true;
            }

            if (container.nodeType === Node.ELEMENT_NODE && container === this.editorTarget) {
                const walker = document.createTreeWalker(container, NodeFilter.SHOW_TEXT);
                let remaining = offset;
                let textNode = walker.nextNode();
                while (textNode) {
                    const len = textNode.textContent?.length ?? 0;
                    if (remaining <= len) {
                        this.mentionRange.setStart(textNode, Math.max(0, remaining - atLength));
                        return true;
                    }
                    remaining -= len;
                    textNode = walker.nextNode();
                }
            }
        } catch (error) {
            console.warn('[forum-mention] range', error);
        }

        return false;
    }

    async fetchSuggestions(query) {
        try {
            const url = new URL(this.suggestionsUrlValue, window.location.origin);
            url.searchParams.set('q', query);
            const response = await fetch(url.toString(), {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            if (!response.ok) {
                console.warn('[forum-mention] API', response.status);
                return;
            }
            const data = await response.json();
            this.suggestions = Array.isArray(data.suggestions) ? data.suggestions : [];
            this.renderList();
        } catch (error) {
            console.error('[forum-mention]', error);
        }
    }

    renderList() {
        if (!this.hasListTarget) {
            return;
        }
        this.listTarget.innerHTML = '';
        if (this.suggestions.length === 0) {
            this.hideList();
            return;
        }

        this.suggestions.forEach((item, index) => {
            const li = document.createElement('li');
            li.className = 'forum-mention-suggestions__item';
            li.setAttribute('role', 'option');
            li.dataset.index = String(index);
            li.innerHTML = `<strong>@${this.escape(item.nickname)}</strong> <span>${this.escape(item.team_name)}</span>`;
            li.addEventListener('mousedown', (event) => {
                event.preventDefault();
                this.pick(item);
            });
            this.listTarget.appendChild(li);
        });

        this.activeIndex = 0;
        this.highlightActive();
        this.listTarget.removeAttribute('hidden');
    }

    pick(item) {
        if (!this.mentionRange || !this.hasEditorTarget) {
            return;
        }

        const span = document.createElement('span');
        span.className = 'forum-mention';
        span.setAttribute('contenteditable', 'false');
        span.setAttribute('data-user-id', String(item.id));
        span.textContent = `@${item.nickname}`;

        const space = document.createTextNode('\u00a0');

        this.mentionRange.deleteContents();
        this.mentionRange.insertNode(space);
        this.mentionRange.insertNode(span);

        const selection = window.getSelection();
        if (selection) {
            const after = document.createRange();
            after.setStartAfter(space);
            after.collapse(true);
            selection.removeAllRanges();
            selection.addRange(after);
        }

        this.editorTarget.dispatchEvent(new Event('input', { bubbles: true }));
        this.hideList();
    }

    highlightActive() {
        if (!this.hasListTarget) {
            return;
        }
        this.listTarget.querySelectorAll('.forum-mention-suggestions__item').forEach((el, i) => {
            el.classList.toggle('is-active', i === this.activeIndex);
        });
    }

    hideList() {
        if (this.hasListTarget) {
            this.listTarget.setAttribute('hidden', 'hidden');
            this.listTarget.innerHTML = '';
        }
        this.suggestions = [];
        this.activeIndex = -1;
        this.mentionRange = null;
    }

    textBeforeCaret(range) {
        const pre = range.cloneRange();
        pre.selectNodeContents(this.editorTarget);
        pre.setEnd(range.startContainer, range.startOffset);
        return pre.toString();
    }

    escape(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }
}

import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        searchUrl: String,
        listboxId: { type: String, default: 'dashboard-buteur-listbox' },
    };
    static targets = ['query', 'results', 'hiddenId', 'preview', 'previewPhotoImg', 'previewPhotoPlaceholder', 'combobox'];

    connect() {
        this._timer = null;
        this._open = false;
        this.boundSearch = this.scheduleSearch.bind(this);
        this.boundDocClick = this.onDocumentClick.bind(this);
        this.boundKeydown = this.onKeydown.bind(this);
        this.boundQueryFocus = this.onQueryFocus.bind(this);
        if (this.hasQueryTarget) {
            this.queryTarget.addEventListener('input', this.boundSearch);
            this.queryTarget.addEventListener('focus', this.boundQueryFocus);
        }
        document.addEventListener('click', this.boundDocClick);
        if (this.hasComboboxTarget) {
            this.comboboxTarget.addEventListener('keydown', this.boundKeydown);
        }
    }

    disconnect() {
        if (this.hasQueryTarget) {
            this.queryTarget.removeEventListener('input', this.boundSearch);
            this.queryTarget.removeEventListener('focus', this.boundQueryFocus);
        }
        document.removeEventListener('click', this.boundDocClick);
        if (this.hasComboboxTarget) {
            this.comboboxTarget.removeEventListener('keydown', this.boundKeydown);
        }
        if (this._timer) {
            clearTimeout(this._timer);
        }
    }

    onDocumentClick(event) {
        if (!this._open || !this.hasComboboxTarget) {
            return;
        }
        if (!this.comboboxTarget.contains(event.target)) {
            this.closePanel();
        }
    }

    onKeydown(event) {
        if (event.key === 'Escape') {
            this.closePanel();
            this.queryTarget.blur();
        }
    }

    onQueryFocus() {
        const q = this.hasQueryTarget ? this.queryTarget.value.trim() : '';
        if (q.length >= 2) {
            this.search();
        }
    }

    setPanelOpen(open) {
        this._open = open;
        if (this.hasQueryTarget) {
            this.queryTarget.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
    }

    closePanel() {
        this.setPanelOpen(false);
        if (this.hasResultsTarget) {
            this.resultsTarget.innerHTML = '';
        }
    }

    scheduleSearch() {
        if (this._timer) {
            clearTimeout(this._timer);
        }
        this._timer = window.setTimeout(() => this.search(), 320);
    }

    initialsFromRow(row) {
        const p = (row.prenom || '').trim();
        const n = (row.nom || '').trim();
        const s = `${p.slice(0, 1)}${n.slice(0, 1)}`.toUpperCase();
        return s || '?';
    }

    groupLabel(row) {
        if (row.pays && row.pays.nom) {
            return row.pays.nom;
        }
        return 'Sans pays';
    }

    groupRows(list) {
        /** @type {Map<string, object[]>} */
        const map = new Map();
        for (const row of list) {
            const key = this.groupLabel(row);
            if (!map.has(key)) {
                map.set(key, []);
            }
            map.get(key).push(row);
        }
        const keys = [...map.keys()].sort((a, b) => {
            if (a === 'Sans pays') {
                return 1;
            }
            if (b === 'Sans pays') {
                return -1;
            }
            return a.localeCompare(b, 'fr');
        });
        return keys.map((k) => ({ label: k, rows: map.get(k) }));
    }

    async search() {
        if (!this.hasResultsTarget) {
            return;
        }
        const q = this.hasQueryTarget ? this.queryTarget.value.trim() : '';

        if (q.length < 2) {
            this.resultsTarget.innerHTML = '';
            this.setPanelOpen(false);
            if (q.length === 0) {
                return;
            }
            this.resultsTarget.innerHTML = '<p class="dashboard-buteur-hint">Saisissez au moins 2 caractères (nom, prénom ou pays).</p>';
            this.setPanelOpen(true);
            return;
        }

        const params = new URLSearchParams();
        params.set('q', q);

        this.resultsTarget.innerHTML = '<p class="dashboard-buteur-hint">Recherche…</p>';
        this.setPanelOpen(true);

        try {
            const res = await fetch(`${this.searchUrlValue}?${params.toString()}`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            if (!res.ok) {
                this.resultsTarget.innerHTML = '<p class="dashboard-buteur-error">Erreur réseau.</p>';
                return;
            }
            const data = await res.json();
            const list = Array.isArray(data.buteurs) ? data.buteurs : [];
            if (list.length === 0) {
                this.resultsTarget.innerHTML = '<p class="dashboard-buteur-hint">Aucun buteur trouvé.</p>';
                return;
            }

            const panel = document.createElement('div');
            panel.className = 'dashboard-buteur-result-panel';
            panel.setAttribute('role', 'listbox');
            panel.id = this.listboxIdValue;

            for (const { label, rows } of this.groupRows(list)) {
                const groupEl = document.createElement('div');
                groupEl.className = 'dashboard-buteur-result-group';
                const gl = document.createElement('div');
                gl.className = 'dashboard-buteur-result-group-label';
                gl.textContent = label;
                groupEl.appendChild(gl);

                const ul = document.createElement('ul');
                ul.className = 'dashboard-buteur-result-list dashboard-buteur-result-list--in-panel';
                for (const row of rows) {
                    const li = document.createElement('li');
                    li.setAttribute('role', 'none');
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'dashboard-buteur-result-item dashboard-buteur-result-item--with-photo';
                    btn.setAttribute('role', 'option');

                    const photoWrap = document.createElement('span');
                    photoWrap.className = 'dashboard-buteur-result-item-photo-wrap';
                    if (row.photo) {
                        const img = document.createElement('img');
                        img.className = 'dashboard-buteur-result-item-photo';
                        img.src = row.photo;
                        img.alt = '';
                        img.width = 36;
                        img.height = 36;
                        img.loading = 'lazy';
                        img.decoding = 'async';
                        photoWrap.appendChild(img);
                    } else {
                        const ph = document.createElement('span');
                        ph.className = 'dashboard-buteur-result-item-photo dashboard-buteur-result-item-photo--placeholder';
                        ph.setAttribute('aria-hidden', 'true');
                        ph.textContent = this.initialsFromRow(row);
                        photoWrap.appendChild(ph);
                    }

                    const text = document.createElement('span');
                    text.className = 'dashboard-buteur-result-item-text';
                    text.textContent = `${row.prenom} ${row.nom}`;

                    btn.appendChild(photoWrap);
                    btn.appendChild(text);
                    btn.addEventListener('click', () => this.pick(row));
                    li.appendChild(btn);
                    ul.appendChild(li);
                }
                groupEl.appendChild(ul);
                panel.appendChild(groupEl);
            }

            this.resultsTarget.innerHTML = '';
            this.resultsTarget.appendChild(panel);
        } catch (e) {
            this.resultsTarget.innerHTML = '<p class="dashboard-buteur-error">Impossible de charger les résultats.</p>';
        }
    }

    pick(row) {
        if (this.hasHiddenIdTarget) {
            this.hiddenIdTarget.value = String(row.id);
        }
        if (this.hasPreviewTarget) {
            const paysNom = row.pays && row.pays.nom ? ` — ${row.pays.nom}` : '';
            this.previewTarget.textContent = `${row.prenom} ${row.nom}${paysNom}`;
        }
        if (this.hasPreviewPhotoImgTarget && this.hasPreviewPhotoPlaceholderTarget) {
            if (row.photo) {
                this.previewPhotoImgTarget.src = row.photo;
                this.previewPhotoImgTarget.removeAttribute('hidden');
                this.previewPhotoPlaceholderTarget.setAttribute('hidden', 'hidden');
                this.previewPhotoPlaceholderTarget.textContent = '';
            } else {
                this.previewPhotoImgTarget.removeAttribute('src');
                this.previewPhotoImgTarget.setAttribute('hidden', 'hidden');
                this.previewPhotoPlaceholderTarget.textContent = this.initialsFromRow(row);
                this.previewPhotoPlaceholderTarget.removeAttribute('hidden');
            }
        }
        if (this.hasQueryTarget) {
            this.queryTarget.value = `${row.prenom} ${row.nom}`;
        }
        this.closePanel();
    }
}

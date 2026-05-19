/**
 * Checklist QA admin — cases (localStorage) + notes partagées (API).
 */
(function () {
    'use strict';

    var STORAGE_KEY = 'lpf26_admin_qa_checklist_v1';

    function loadState() {
        try {
            var raw = localStorage.getItem(STORAGE_KEY);
            if (!raw) {
                return {};
            }
            var parsed = JSON.parse(raw);
            return parsed && typeof parsed === 'object' ? parsed : {};
        } catch (e) {
            return {};
        }
    }

    function saveState(state) {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
        } catch (e) {
            /* quota ou mode privé */
        }
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function formatDate(iso) {
        if (!iso) {
            return '';
        }
        try {
            var d = new Date(iso);
            var pad = function (n) {
                return n < 10 ? '0' + n : String(n);
            };
            return pad(d.getDate()) + '/' + pad(d.getMonth() + 1) + '/' + d.getFullYear()
                + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
        } catch (e) {
            return '';
        }
    }

    function renderNotesList(notesContainer, notes) {
        var list = notesContainer.querySelector('[data-admin-qa-notes-list]');
        var empty = notesContainer.querySelector('[data-admin-qa-notes-empty]');
        if (!list) {
            return;
        }

        list.innerHTML = '';

        if (!notes || notes.length === 0) {
            if (empty) {
                empty.classList.remove('admin-qa-notes__empty--hidden');
            }
            return;
        }

        if (empty) {
            empty.classList.add('admin-qa-notes__empty--hidden');
        }

        notes.forEach(function (note) {
            var li = document.createElement('li');
            li.className = 'admin-qa-notes__entry' + (note.is_mine ? ' admin-qa-notes__entry--mine' : '');
            if (note.id) {
                li.setAttribute('data-note-id', String(note.id));
            }

            var meta = document.createElement('div');
            meta.className = 'admin-qa-notes__meta';

            var author = document.createElement('strong');
            author.className = 'admin-qa-notes__author';
            author.textContent = note.author_label || 'Administrateur';
            meta.appendChild(author);

            if (note.is_mine) {
                var you = document.createElement('span');
                you.className = 'admin-qa-notes__you';
                you.textContent = ' (vous)';
                meta.appendChild(you);
            }

            var time = document.createElement('time');
            time.className = 'admin-qa-notes__time';
            var updated = note.updated_at || '';
            time.setAttribute('datetime', updated);
            time.textContent = formatDate(updated);
            meta.appendChild(time);

            var body = document.createElement('p');
            body.className = 'admin-qa-notes__body';
            body.innerHTML = escapeHtml(note.content || '').replace(/\n/g, '<br>');

            li.appendChild(meta);
            li.appendChild(body);
            list.appendChild(li);
        });
    }

    function initNotes(root, csrfToken, urlTemplate) {
        root.querySelectorAll('[data-admin-qa-note-form]').forEach(function (form) {
            var itemId = form.getAttribute('data-admin-qa-note-form');
            var notesContainer = form.closest('[data-admin-qa-notes]');
            var statusEl = form.querySelector('[data-admin-qa-note-status]');

            form.addEventListener('submit', function (event) {
                event.preventDefault();
                if (!itemId || !urlTemplate) {
                    return;
                }

                var textarea = form.querySelector('textarea[name="content"]');
                var content = textarea ? textarea.value : '';
                var url = urlTemplate.replace('__ITEM__', encodeURIComponent(itemId));

                if (statusEl) {
                    statusEl.textContent = 'Enregistrement…';
                    statusEl.classList.remove('admin-qa-notes__status--error', 'admin-qa-notes__status--ok');
                }

                fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken,
                    },
                    body: JSON.stringify({ content: content }),
                    credentials: 'same-origin',
                })
                    .then(function (response) {
                        return response.json().then(function (data) {
                            return { ok: response.ok, data: data };
                        });
                    })
                    .then(function (result) {
                        if (!result.ok) {
                            throw new Error(result.data.error || 'Erreur lors de l’enregistrement.');
                        }

                        var notes = result.data.all_notes || [];
                        if (notesContainer) {
                            renderNotesList(notesContainer, notes);
                        }

                        if (statusEl) {
                            statusEl.textContent = result.data.removed
                                ? 'Note supprimée.'
                                : 'Note enregistrée.';
                            statusEl.classList.add('admin-qa-notes__status--ok');
                            statusEl.classList.remove('admin-qa-notes__status--error');
                        }
                    })
                    .catch(function (err) {
                        if (statusEl) {
                            statusEl.textContent = err.message || 'Erreur réseau.';
                            statusEl.classList.add('admin-qa-notes__status--error');
                            statusEl.classList.remove('admin-qa-notes__status--ok');
                        }
                    });
            });
        });
    }

    function init() {
        var root = document.querySelector('[data-admin-qa-root]');
        if (!root) {
            return;
        }

        var progressRoot = document.querySelector('[data-admin-qa-progress]');
        var csrfToken = progressRoot ? progressRoot.getAttribute('data-csrf-token') || '' : '';
        var urlTemplate = root.getAttribute('data-note-url-template') || '';

        initNotes(root, csrfToken, urlTemplate);

        var state = loadState();
        var inputs = root.querySelectorAll('[data-admin-qa-item]');
        var total = progressRoot
            ? parseInt(progressRoot.getAttribute('data-total') || '0', 10)
            : inputs.length;

        inputs.forEach(function (input) {
            var id = input.getAttribute('data-admin-qa-item');
            if (id && state[id]) {
                input.checked = true;
            }

            input.addEventListener('change', function () {
                if (input.checked) {
                    state[id] = true;
                } else {
                    delete state[id];
                }
                saveState(state);
                updateProgress();
            });
        });

        var resetBtn = document.querySelector('[data-admin-qa-action="reset-all"]');
        if (resetBtn) {
            resetBtn.addEventListener('click', function () {
                if (!window.confirm('Réinitialiser uniquement les cases cochées sur cet appareil ? Les notes en base ne seront pas supprimées.')) {
                    return;
                }
                state = {};
                saveState(state);
                inputs.forEach(function (input) {
                    input.checked = false;
                });
                updateProgress();
            });
        }

        function updateProgress() {
            var checked = root.querySelectorAll('[data-admin-qa-item]:checked').length;

            if (progressRoot) {
                var countEl = progressRoot.querySelector('[data-admin-qa-progress-count]');
                var fillEl = progressRoot.querySelector('[data-admin-qa-progress-fill]');
                var barEl = progressRoot.querySelector('[data-admin-qa-progress-bar]');
                var pct = total > 0 ? Math.round((checked / total) * 100) : 0;

                if (countEl) {
                    countEl.textContent = checked + ' / ' + total;
                }
                if (fillEl) {
                    fillEl.style.width = pct + '%';
                }
                if (barEl) {
                    barEl.setAttribute('aria-valuenow', String(checked));
                    barEl.setAttribute('aria-valuemax', String(total));
                }
            }

            root.querySelectorAll('[data-admin-qa-section]').forEach(function (section) {
                var sectionInputs = section.querySelectorAll('[data-admin-qa-item]');
                var sectionChecked = section.querySelectorAll('[data-admin-qa-item]:checked').length;
                var badge = section.querySelector('[data-admin-qa-section-badge]');

                if (badge) {
                    badge.textContent = sectionChecked + ' / ' + sectionInputs.length;
                }

                if (sectionInputs.length > 0 && sectionChecked === sectionInputs.length) {
                    section.classList.add('admin-qa-section--complete');
                } else {
                    section.classList.remove('admin-qa-section--complete');
                }
            });
        }

        updateProgress();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

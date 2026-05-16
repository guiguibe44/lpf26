(function () {
    const dialog = document.getElementById('joker-match-dialog');
    if (!dialog || typeof dialog.showModal !== 'function') {
        return;
    }

    const titleEl = document.getElementById('joker-dialog-title');
    const matchLabelEl = document.getElementById('joker-dialog-match-label');
    const messageEl = document.getElementById('joker-dialog-message');
    const activeEl = document.getElementById('joker-dialog-active');
    const listEl = document.getElementById('joker-dialog-list');
    const loadingEl = document.getElementById('joker-dialog-loading');

    let currentMatchId = null;
    let currentStateUrl = null;
    let currentPlaceUrl = null;
    let currentRemoveUrl = null;

    const assetUrl = (path) => {
        if (!path) {
            return null;
        }
        if (path.startsWith('http://') || path.startsWith('https://')) {
            return path;
        }

        return '/' + String(path).replace(/^\//, '');
    };

    const escapeHtml = (value) => {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    };

    const readUrlsFromElement = (el) => {
        const card = el.closest('.match-card');
        const actions = el.closest('[data-joker-actions]') || (card ? card.querySelector('[data-joker-actions]') : null);
        const source = actions || el;

        return {
            matchId: source.dataset.matchId || card?.dataset.matchId || null,
            stateUrl: source.dataset.jokerStateUrl || null,
            placeUrl: source.dataset.jokerPlaceUrl || null,
            removeUrl: source.dataset.jokerRemoveUrl || null,
            matchLabel: source.dataset.matchLabel || 'Match',
        };
    };

    const closeJokerPopover = (card) => {
        if (!card) {
            document.querySelectorAll('[data-joker-popover]').forEach((popover) => {
                popover.hidden = true;
            });
            document.querySelectorAll('[data-joker-badge-toggle]').forEach((badge) => {
                badge.setAttribute('aria-expanded', 'false');
            });

            return;
        }

        const popover = card.querySelector('[data-joker-popover]');
        const badge = card.querySelector('[data-joker-badge-toggle]');
        if (popover) {
            popover.hidden = true;
        }
        if (badge) {
            badge.setAttribute('aria-expanded', 'false');
        }
    };

    const closeAllJokerPopovers = (exceptCard) => {
        document.querySelectorAll('.match-card').forEach((card) => {
            if (exceptCard && card === exceptCard) {
                return;
            }
            closeJokerPopover(card);
        });
    };

    const toggleJokerPopover = (badge) => {
        const card = badge.closest('.match-card');
        if (!card) {
            return;
        }

        const popover = card.querySelector('[data-joker-popover]');
        if (!popover) {
            return;
        }

        const willOpen = popover.hidden;
        closeAllJokerPopovers(willOpen ? card : null);

        if (willOpen) {
            popover.hidden = false;
            badge.setAttribute('aria-expanded', 'true');
        }
    };

    const closeDialog = () => {
        if (dialog.open) {
            dialog.close();
        }
    };

    const setLoading = (loading) => {
        if (loadingEl) {
            loadingEl.hidden = !loading;
        }
        if (listEl) {
            listEl.hidden = loading;
        }
    };

    const refreshCardBadge = (matchId, active) => {
        const card = document.querySelector('.match-card[data-match-id="' + matchId + '"]');
        if (!card) {
            return;
        }

        closeJokerPopover(card);

        let badge = card.querySelector('[data-joker-badge]');
        if (active) {
            const head = card.querySelector('.match-card-head');
            if (!badge && head) {
                badge = document.createElement('button');
                badge.type = 'button';
                badge.className = 'match-card-joker-badge';
                badge.dataset.jokerBadge = '';
                badge.dataset.jokerBadgeToggle = '';
                badge.setAttribute('aria-expanded', 'false');
                badge.setAttribute('aria-haspopup', 'true');
                badge.title = 'Afficher les actions joker';
                head.insertBefore(badge, head.firstChild);
            }
            if (badge) {
                badge.textContent = 'Joker : ' + active.name;
                badge.hidden = false;
            }
        } else if (badge) {
            badge.remove();
        }
    };

    const syncMatchCardActions = (matchId, state) => {
        const card = document.querySelector('.match-card[data-match-id="' + matchId + '"]');
        const actions = card?.querySelector('[data-joker-actions]');
        if (!card || !actions) {
            return;
        }

        const openInitial = actions.querySelector('[data-joker-open-initial]');
        const popover = actions.querySelector('[data-joker-popover]');
        const removeBtn = actions.querySelector('[data-joker-remove]');
        const active = state && state.active_on_match;

        closeJokerPopover(card);

        if (active) {
            if (openInitial) {
                openInitial.hidden = true;
            }
            if (popover) {
                popover.hidden = true;
            }
            if (removeBtn) {
                removeBtn.hidden = !active.can_remove;
            }
        } else {
            if (openInitial) {
                openInitial.hidden = false;
            }
            if (popover) {
                popover.hidden = true;
            }
        }
    };

    const applyState = (state) => {
        if (!state) {
            return;
        }

        renderList(state);

        if (currentMatchId) {
            refreshCardBadge(currentMatchId, state.active_on_match || null);
            syncMatchCardActions(currentMatchId, state);
        }
    };

    const renderActive = (active) => {
        if (!activeEl) {
            return;
        }
        if (!active) {
            activeEl.hidden = true;
            activeEl.replaceChildren();

            return;
        }

        activeEl.hidden = false;
        activeEl.replaceChildren();

        const wrap = document.createElement('div');
        wrap.className = 'joker-dialog-active-card';

        const imgUrl = assetUrl(active.image);
        if (imgUrl) {
            const img = document.createElement('img');
            img.src = imgUrl;
            img.alt = '';
            img.className = 'joker-dialog-active-image';
            img.width = 40;
            img.height = 40;
            wrap.appendChild(img);
        }

        const text = document.createElement('p');
        text.className = 'joker-dialog-active-text';
        text.innerHTML = '<strong>Joker actif :</strong> ' + escapeHtml(active.name);
        wrap.appendChild(text);

        if (active.can_remove) {
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'btn btn-outline btn-sm joker-dialog-remove-btn';
            removeBtn.dataset.jokerRemoveDialog = '1';
            removeBtn.innerHTML = '<i class="ti ti-x" aria-hidden="true"></i> Retirer ce joker';
            wrap.appendChild(removeBtn);
        }

        activeEl.appendChild(wrap);
    };

    const renderList = (state) => {
        if (!listEl) {
            return;
        }

        listEl.replaceChildren();

        if (!state.can_manage) {
            if (messageEl) {
                messageEl.hidden = false;
                messageEl.textContent = state.reason || 'Jokers indisponibles.';
            }

            return;
        }

        if (messageEl) {
            messageEl.hidden = true;
        }

        renderActive(state.active_on_match);

        if (state.pending_elsewhere && !state.active_on_match) {
            const note = document.createElement('p');
            note.className = 'joker-dialog-note';
            note.textContent = 'Joker en cours sur ' + state.pending_elsewhere.match_label + ' (« ' + state.pending_elsewhere.joker_name + ' »).';
            listEl.appendChild(note);
        }

        (state.jokers || []).forEach((joker) => {
            const item = document.createElement('li');
            item.className = 'joker-dialog-item';

            const media = document.createElement('div');
            media.className = 'joker-dialog-item-media';
            const imgUrl = assetUrl(joker.image);
            if (imgUrl) {
                const img = document.createElement('img');
                img.src = imgUrl;
                img.alt = '';
                img.className = 'joker-dialog-item-image';
                img.width = 48;
                img.height = 48;
                media.appendChild(img);
            } else {
                const ph = document.createElement('span');
                ph.className = 'joker-dialog-item-placeholder';
                ph.innerHTML = '<i class="ti ti-wand" aria-hidden="true"></i>';
                media.appendChild(ph);
            }

            const body = document.createElement('div');
            body.className = 'joker-dialog-item-body';
            const name = document.createElement('h3');
            name.className = 'joker-dialog-item-name';
            name.textContent = joker.name;
            body.appendChild(name);

            if (joker.description) {
                const desc = document.createElement('p');
                desc.className = 'joker-dialog-item-desc';
                desc.textContent = joker.description;
                body.appendChild(desc);
            }

            if (joker.disabled_reason) {
                const reason = document.createElement('p');
                reason.className = 'joker-dialog-item-reason';
                reason.textContent = joker.disabled_reason;
                body.appendChild(reason);
            }

            const itemActions = document.createElement('div');
            itemActions.className = 'joker-dialog-item-actions';
            if (joker.can_play) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'btn btn-primary btn-sm';
                btn.textContent = 'Jouer ce joker';
                btn.dataset.jokerPlay = String(joker.id);
                itemActions.appendChild(btn);
            } else if (joker.already_used) {
                const badge = document.createElement('span');
                badge.className = 'ta-badge';
                badge.textContent = 'Déjà utilisé';
                itemActions.appendChild(badge);
            }

            item.appendChild(media);
            item.appendChild(body);
            item.appendChild(itemActions);
            listEl.appendChild(item);
        });
    };

    const loadState = () => {
        if (!currentStateUrl) {
            return;
        }

        setLoading(true);
        fetch(currentStateUrl, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error('Chargement impossible');
                }

                return response.json();
            })
            .then((state) => {
                setLoading(false);
                applyState(state);
            })
            .catch(() => {
                setLoading(false);
                if (messageEl) {
                    messageEl.hidden = false;
                    messageEl.textContent = 'Impossible de charger les jokers.';
                }
            });
    };

    const showFeedback = (message, isError) => {
        if (!messageEl) {
            return;
        }
        messageEl.hidden = false;
        messageEl.className = 'joker-dialog-message ta-card-text' + (isError ? ' joker-dialog-message--error' : ' joker-dialog-message--success');
        messageEl.textContent = message;
    };

    const placeJoker = (jokerId) => {
        if (!currentPlaceUrl) {
            return;
        }

        const body = new FormData();
        body.set('joker_id', String(jokerId));

        fetch(currentPlaceUrl, {
            method: 'POST',
            body,
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        })
            .then((response) => response.json().then((data) => ({ ok: response.ok, data })))
            .then(({ ok, data }) => {
                if (!ok) {
                    throw new Error(data.error || 'Erreur');
                }

                showFeedback(data.message || 'Joker posé.', false);
                applyState(data.state);
            })
            .catch((err) => {
                showFeedback(err.message || 'Impossible de poser le joker.', true);
            });
    };

    const removeJoker = () => {
        if (!currentRemoveUrl) {
            return;
        }

        if (!window.confirm('Retirer le joker de ce match ? Vous pourrez le rejouer sur un autre match (une seule fois par type de joker).')) {
            return;
        }

        fetch(currentRemoveUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        })
            .then((response) => response.json().then((data) => ({ ok: response.ok, data })))
            .then(({ ok, data }) => {
                if (!ok) {
                    throw new Error(data.error || 'Erreur');
                }

                showFeedback(data.message || 'Joker retiré.', false);
                applyState(data.state);
            })
            .catch((err) => {
                showFeedback(err.message || 'Impossible de retirer le joker.', true);
            });
    };

    const openDialog = (el) => {
        const urls = readUrlsFromElement(el);
        currentMatchId = urls.matchId;
        currentStateUrl = urls.stateUrl;
        currentPlaceUrl = urls.placeUrl;
        currentRemoveUrl = urls.removeUrl;

        if (titleEl) {
            titleEl.textContent = 'Jokers — ' + urls.matchLabel;
        }
        if (matchLabelEl) {
            matchLabelEl.hidden = true;
        }
        if (messageEl) {
            messageEl.hidden = true;
            messageEl.className = 'joker-dialog-message ta-card-text';
        }

        dialog.showModal();
        loadState();
    };

    document.addEventListener('click', (event) => {
        const badgeToggle = event.target.closest('[data-joker-badge-toggle]');
        if (badgeToggle) {
            event.preventDefault();
            event.stopPropagation();
            toggleJokerPopover(badgeToggle);

            return;
        }

        if (!event.target.closest('[data-joker-popover]')) {
            closeAllJokerPopovers();
        }

        const openBtn = event.target.closest('[data-joker-open]');
        if (openBtn) {
            closeAllJokerPopovers();
            openDialog(openBtn);

            return;
        }

        const removeBtn = event.target.closest('[data-joker-remove], [data-joker-remove-dialog]');
        if (removeBtn) {
            const urls = readUrlsFromElement(removeBtn);
            currentMatchId = urls.matchId || currentMatchId;
            currentRemoveUrl = urls.removeUrl || currentRemoveUrl;
            currentStateUrl = urls.stateUrl || currentStateUrl;
            removeJoker();

            return;
        }

        const playBtn = event.target.closest('[data-joker-play]');
        if (playBtn && dialog.contains(playBtn)) {
            const jokerId = playBtn.dataset.jokerPlay;
            if (jokerId) {
                placeJoker(jokerId);
            }

            return;
        }

        const closeBtn = event.target.closest('[data-joker-close]');
        if (closeBtn || event.target === dialog) {
            closeDialog();
        }
    });

    dialog.addEventListener('cancel', (event) => {
        event.preventDefault();
        closeDialog();
    });
})();

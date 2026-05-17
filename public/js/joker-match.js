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
    const targetPickerEl = document.getElementById('joker-dialog-target-picker');
    const targetSelectEl = document.getElementById('joker-dialog-target-select');
    const targetConfirmEl = document.getElementById('joker-dialog-target-confirm');
    const targetCancelEl = document.getElementById('joker-dialog-target-cancel');
    const espionEl = document.getElementById('joker-dialog-espion');

    let currentMatchId = null;
    let currentStateUrl = null;
    let currentPlaceUrl = null;
    let currentRemoveUrl = null;
    let currentPickerState = null;
    let pendingJokerId = null;

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

    const formatJokerBadgeLabel = (active) => {
        if (!active) {
            return '';
        }

        let label = 'Joker : ' + active.name;
        if (active.target_team_name) {
            label += ' → ' + active.target_team_name;
        }

        return label;
    };

    const hideTargetPicker = () => {
        pendingJokerId = null;
        if (targetPickerEl) {
            targetPickerEl.hidden = true;
            const targetLabel = targetPickerEl.querySelector('.joker-dialog-target-label');
            if (targetLabel) {
                targetLabel.textContent = 'Équipe adverse à cibler';
            }
        }
        if (listEl) {
            listEl.hidden = false;
        }
    };

    const showTargetPicker = (jokerId, state) => {
        if (!targetPickerEl || !targetSelectEl) {
            placeJoker(jokerId, null);

            return;
        }

        const joker = findJokerInState(state, jokerId);
        let opponents = state.opponent_teams || [];
        if (joker && joker.code === 'inverse_buteur') {
            opponents = opponents.filter((team) => team.match_eligible_inverse_buteur);
        }

        const targetLabel = targetPickerEl.querySelector('.joker-dialog-target-label');
        if (targetLabel) {
            if (joker && joker.code === 'inverse_score') {
                targetLabel.textContent = 'Équipe adverse : ses pronostics seront notés avec le score inversé';
            } else if (joker && joker.code === 'inverse_buteur') {
                targetLabel.textContent = 'Équipe adverse à cibler (un de ses buteurs doit jouer ce match)';
            } else {
                targetLabel.textContent = 'Équipe adverse à cibler';
            }
        }

        if (!opponents.length) {
            showFeedback(
                joker && joker.code === 'inverse_buteur'
                    ? 'Aucune équipe adverse n\'a un buteur dont le pays joue ce match.'
                    : 'Aucune équipe adverse disponible.',
                true,
            );

            return;
        }

        pendingJokerId = jokerId;
        targetSelectEl.replaceChildren();
        opponents.forEach((team) => {
            const option = document.createElement('option');
            option.value = String(team.id);
            let label = team.name;
            if (team.buteur_countries && team.buteur_countries.length > 0) {
                label += ' (' + team.buteur_countries.join(', ') + ')';
            }
            option.textContent = label;
            targetSelectEl.appendChild(option);
        });

        if (listEl) {
            listEl.hidden = true;
        }
        targetPickerEl.hidden = false;
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
                badge.textContent = formatJokerBadgeLabel(active);
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

    const renderEspionIntel = (intel) => {
        if (!espionEl) {
            return;
        }

        if (!intel) {
            espionEl.hidden = true;
            espionEl.replaceChildren();

            return;
        }

        espionEl.hidden = false;
        espionEl.className = 'joker-dialog-espion match-espion-panel';
        espionEl.replaceChildren();

        const title = document.createElement('p');
        title.className = 'match-espion-title';
        title.innerHTML = '<i class="ti ti-eye" aria-hidden="true"></i> Renseignements espion';
        espionEl.appendChild(title);

        const cotesSection = document.createElement('div');
        cotesSection.className = 'match-espion-section';
        const cotesTitle = document.createElement('h4');
        cotesTitle.className = 'match-espion-section-title';
        cotesTitle.textContent = 'Cotes du match';
        cotesSection.appendChild(cotesTitle);

        const c = intel.cotes || {};
        const cotesP = document.createElement('p');
        cotesP.className = intel.cotes && intel.cotes.moyenne != null ? 'match-espion-cotes' : 'match-espion-empty';
        if (c.moyenne != null) {
            let text = 'Moy. ' + Number(c.moyenne).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            if (c.min != null && c.max != null) {
                text += ' · min ' + Number(c.min).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                text += ' · max ' + Number(c.max).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
            text += ' (' + (c.pronostics_count || 0) + ' pronostic' + ((c.pronostics_count || 0) > 1 ? 's' : '') + ')';
            cotesP.textContent = text;
        } else {
            cotesP.textContent = 'Pas encore assez de pronostics pour estimer les cotes.';
        }
        cotesSection.appendChild(cotesP);
        espionEl.appendChild(cotesSection);

        const jokersSection = document.createElement('div');
        jokersSection.className = 'match-espion-section';
        const jokersTitle = document.createElement('h4');
        jokersTitle.className = 'match-espion-section-title';
        jokersTitle.textContent = 'Jokers posés';
        jokersSection.appendChild(jokersTitle);

        const jokers = intel.jokers || [];
        if (jokers.length === 0) {
            const empty = document.createElement('p');
            empty.className = 'match-espion-empty';
            empty.textContent = 'Aucun joker posé sur ce match pour le moment.';
            jokersSection.appendChild(empty);
        } else {
            const ul = document.createElement('ul');
            ul.className = 'match-espion-jokers-list';
            jokers.forEach((row) => {
                const li = document.createElement('li');
                li.className = 'match-espion-joker-item';
                li.textContent = row.team_name + ' — ' + row.joker_name;
                if (row.target_team_name) {
                    li.textContent += ' → ' + row.target_team_name;
                }
                ul.appendChild(li);
            });
            jokersSection.appendChild(ul);
        }
        espionEl.appendChild(jokersSection);
    };

    const applyState = (state) => {
        if (!state) {
            return;
        }

        currentPickerState = state;
        hideTargetPicker();
        renderList(state);
        renderEspionIntel(state.espion_intel || null);

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
        let activeText = escapeHtml(active.name);
        if (active.target_team_name) {
            activeText += ' <span class="joker-dialog-active-target">→ ' + escapeHtml(active.target_team_name) + '</span>';
        }
        text.innerHTML = '<strong>Joker actif :</strong> ' + activeText;
        wrap.appendChild(text);

        if (active.code === 'espion') {
            const irreversible = document.createElement('p');
            irreversible.className = 'joker-dialog-irreversible-note';
            irreversible.textContent = 'Ce joker est définitif : il ne peut pas être retiré.';
            wrap.appendChild(irreversible);
        }

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

        if (state.team_buteur_countries && state.team_buteur_countries.length > 0) {
            const buteurNote = document.createElement('p');
            buteurNote.className = 'joker-dialog-note joker-dialog-buteur-note';
            buteurNote.textContent =
                'Pays de vos buteurs : ' +
                state.team_buteur_countries.join(', ') +
                ' (Double buteur : match concerné · Inversion buteur : pays d\'un buteur de l\'équipe ciblée).';
            listEl.appendChild(buteurNote);
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
                if (joker.requires_target_team) {
                    btn.dataset.jokerRequiresTarget = '1';
                }
                if (joker.requires_confirmation) {
                    btn.dataset.jokerRequiresConfirm = '1';
                }
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

    const confirmPlaceJoker = (joker) => {
        if (!joker) {
            return true;
        }

        if (joker.requires_confirmation && joker.confirmation_message) {
            return window.confirm(joker.confirmation_message);
        }

        return true;
    };

    const findJokerInState = (state, jokerId) => {
        if (!state || !state.jokers) {
            return null;
        }

        return state.jokers.find((j) => String(j.id) === String(jokerId)) || null;
    };

    const placeJoker = (jokerId, targetTeamId) => {
        if (!currentPlaceUrl) {
            return;
        }

        const body = new FormData();
        body.set('joker_id', String(jokerId));
        if (targetTeamId) {
            body.set('target_team_id', String(targetTeamId));
        }

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
                if (data.state && data.state.espion_intel) {
                    window.location.reload();

                    return;
                }
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
            const requiresTarget = playBtn.dataset.jokerRequiresTarget === '1';
            const joker = findJokerInState(currentPickerState, jokerId);
            if (jokerId && requiresTarget && currentPickerState) {
                if (!confirmPlaceJoker(joker)) {
                    return;
                }
                showTargetPicker(jokerId, currentPickerState);
            } else if (jokerId) {
                if (!confirmPlaceJoker(joker)) {
                    return;
                }
                placeJoker(jokerId, null);
            }

            return;
        }

        if (event.target === targetConfirmEl && pendingJokerId && targetSelectEl) {
            const joker = findJokerInState(currentPickerState, pendingJokerId);
            if (!confirmPlaceJoker(joker)) {
                return;
            }
            placeJoker(pendingJokerId, targetSelectEl.value);

            return;
        }

        if (event.target === targetCancelEl) {
            hideTargetPicker();

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

(function () {
    let dialog = null;
    let titleEl = null;
    let matchLabelEl = null;
    let messageEl = null;
    let activeEl = null;
    let listEl = null;
    let loadingEl = null;
    let targetPickerEl = null;
    let targetSelectEl = null;
    let targetConfirmEl = null;
    let targetCancelEl = null;
    let espionEl = null;
    let heroEl = null;
    let listLabelEl = null;

    const bindElements = () => {
        dialog = document.getElementById('joker-match-dialog');
        if (!dialog || typeof dialog.showModal !== 'function') {
            return false;
        }

        titleEl = document.getElementById('joker-drawer-title');
        matchLabelEl = document.getElementById('joker-dialog-match-label');
        messageEl = document.getElementById('joker-dialog-message');
        activeEl = document.getElementById('joker-dialog-active');
        listEl = document.getElementById('joker-dialog-list');
        loadingEl = document.getElementById('joker-dialog-loading');
        targetPickerEl = document.getElementById('joker-dialog-target-picker');
        targetSelectEl = document.getElementById('joker-dialog-target-select');
        targetConfirmEl = document.getElementById('joker-dialog-target-confirm');
        targetCancelEl = document.getElementById('joker-dialog-target-cancel');
        espionEl = document.getElementById('joker-dialog-espion');
        heroEl = document.getElementById('joker-dialog-hero');
        listLabelEl = document.getElementById('joker-dialog-list-label');

        return true;
    };

    const bindDialogEvents = () => {
        if (!dialog || dialog.dataset.jokerMatchBound === '1') {
            return;
        }

        dialog.dataset.jokerMatchBound = '1';
        dialog.addEventListener('cancel', (event) => {
            event.preventDefault();
            closeDialog();
        });
    };

    const init = () => {
        if (!bindElements()) {
            return false;
        }

        bindDialogEvents();

        return true;
    };

    let currentMatchId = null;
    let currentStateUrl = null;
    let currentPlaceUrl = null;
    let currentRemoveUrl = null;
    let currentPickerState = null;
    let pendingJokerId = null;
    let pendingPickerMode = null;

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
        if (active.favorite_country_name) {
            label += ' · ' + active.favorite_country_name;
        } else if (active.target_team_name) {
            label += ' → ' + active.target_team_name;
        }
        if (active.effect_blocked) {
            label += ' (sans effet)';
        }

        return label;
    };

    const hideTargetPicker = () => {
        pendingJokerId = null;
        pendingPickerMode = null;
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
            if (team.shield_protected) {
                label += ' — protégée (bouclier)';
            }
            option.textContent = label;
            targetSelectEl.appendChild(option);
        });

        if (listEl) {
            listEl.hidden = true;
        }
        targetPickerEl.hidden = false;
        pendingPickerMode = 'target';
    };

    const showFavoriteCountryPicker = (jokerId, state) => {
        if (!targetPickerEl || !targetSelectEl) {
            return;
        }

        const countries = state.favorite_countries || [];
        if (!countries.length) {
            showFeedback('Aucun pays disponible.', true);

            return;
        }

        const targetLabel = targetPickerEl.querySelector('.joker-dialog-target-label');
        if (targetLabel) {
            targetLabel.textContent =
                'Sélection nationale favorite (choix secret, définitif sauf retrait avant le coup d\'envoi)';
        }

        pendingJokerId = jokerId;
        pendingPickerMode = 'favorite';
        targetSelectEl.replaceChildren();
        countries.forEach((country) => {
            const option = document.createElement('option');
            option.value = String(country.id);
            option.textContent = country.name;
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
        espionEl.className = 'joker-drawer__espion joker-dialog-espion match-espion-panel';
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
                if (row.effect_blocked) {
                    li.textContent += ' (sans effet — cible protégée)';
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

    const createJokerCardShell = () => {
        const card = document.createElement('article');
        card.className = 'joker-drawer__card';

        const top = document.createElement('div');
        top.className = 'joker-drawer__card-top';

        const visual = document.createElement('div');
        visual.className = 'joker-drawer__card-visual';

        const copy = document.createElement('div');
        copy.className = 'joker-drawer__card-copy';

        const actions = document.createElement('div');
        actions.className = 'joker-drawer__card-actions';
        actions.hidden = true;

        top.appendChild(visual);
        top.appendChild(copy);
        copy.appendChild(actions);
        card.appendChild(top);

        return { card, visual, copy, actions };
    };

    const fillJokerCardVisual = (visual, imagePath) => {
        visual.replaceChildren();
        const imgUrl = assetUrl(imagePath);
        if (imgUrl) {
            const img = document.createElement('img');
            img.src = imgUrl;
            img.alt = '';
            img.className = 'joker-drawer__card-image';
            visual.appendChild(img);

            return;
        }

        const ph = document.createElement('span');
        ph.className = 'joker-drawer__card-placeholder';
        ph.innerHTML = '<i class="ti ti-wand" aria-hidden="true"></i>';
        visual.appendChild(ph);
    };

    const fillJokerCardCopy = (copy, name, description, metaText) => {
        const actions = copy.querySelector('.joker-drawer__card-actions');
        copy.replaceChildren();

        const title = document.createElement('h3');
        title.className = 'joker-drawer__card-title';
        title.textContent = name;
        copy.appendChild(title);

        if (description) {
            const desc = document.createElement('p');
            desc.className = 'joker-drawer__card-desc';
            desc.textContent = description;
            copy.appendChild(desc);
        }

        if (metaText) {
            const meta = document.createElement('p');
            meta.className = 'joker-drawer__card-meta';
            meta.textContent = metaText;
            copy.appendChild(meta);
        }

        const actionsEl = actions || document.createElement('div');
        if (!actions) {
            actionsEl.className = 'joker-drawer__card-actions';
            actionsEl.hidden = true;
        } else {
            actionsEl.replaceChildren();
            actionsEl.hidden = true;
        }
        copy.appendChild(actionsEl);
    };

    const appendActiveFootContent = (actions, active) => {
        actions.replaceChildren();

        const hasNotes =
            active.code === 'espion' ||
            active.code === 'bouclier' ||
            (active.code === 'equipe_favorite' && active.favorite_country_name) ||
            active.effect_blocked;

        if (hasNotes) {
            const notesWrap = document.createElement('div');
            notesWrap.className = 'joker-drawer__active-notes';

            if (active.code === 'espion') {
                const irreversible = document.createElement('p');
                irreversible.className = 'joker-drawer__note joker-drawer__note--warn';
                irreversible.textContent = 'Ce joker est définitif : il ne peut pas être retiré.';
                notesWrap.appendChild(irreversible);
            }

            if (active.code === 'bouclier') {
                const shieldNote = document.createElement('p');
                shieldNote.className = 'joker-drawer__note joker-drawer__note--shield';
                shieldNote.textContent =
                    'Votre équipe est protégée pour toute la journée de ce match contre les jokers adverses qui vous ciblent.';
                notesWrap.appendChild(shieldNote);
            }

            if (active.code === 'equipe_favorite' && active.favorite_country_name) {
                const favoriteNote = document.createElement('p');
                favoriteNote.className = 'joker-drawer__note';
                favoriteNote.textContent =
                    'Équipe favorite : ' +
                    active.favorite_country_name +
                    '. Choix secret — protection sur les matchs de poule où cette sélection joue.';
                notesWrap.appendChild(favoriteNote);
            }

            if (active.effect_blocked) {
                const blockedNote = document.createElement('p');
                blockedNote.className = 'joker-drawer__note joker-drawer__note--blocked';
                blockedNote.textContent = 'Sans effet : la cible est protégée sur ce match (joker consommé).';
                notesWrap.appendChild(blockedNote);
            }

            actions.appendChild(notesWrap);
        }

        if (active.can_remove) {
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'btn btn-outline btn-sm joker-drawer__remove-btn';
            removeBtn.dataset.jokerRemoveDialog = '1';
            removeBtn.innerHTML = '<i class="ti ti-x" aria-hidden="true"></i> Retirer ce joker';
            actions.appendChild(removeBtn);
        }

        actions.hidden = actions.childElementCount === 0;
    };

    const renderActiveCard = (active) => {
        if (!heroEl) {
            return;
        }

        if (activeEl) {
            activeEl.hidden = true;
            activeEl.replaceChildren();
        }

        if (!active) {
            heroEl.hidden = true;
            heroEl.replaceChildren();

            return;
        }

        heroEl.hidden = false;
        heroEl.replaceChildren();

        const shell = createJokerCardShell();
        fillJokerCardVisual(shell.visual, active.image);

        let metaText = null;
        if (active.target_team_name) {
            metaText = 'Cible : ' + active.target_team_name;
        }

        fillJokerCardCopy(shell.copy, active.name, active.description || null, metaText);
        appendActiveFootContent(shell.actions, active);
        heroEl.appendChild(shell.card);
    };

    const appendJokerUseAction = (actions, joker) => {
        if (joker.can_play) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-primary btn-sm joker-drawer__use-btn';
            btn.textContent = 'Utiliser ce joker';
            btn.dataset.jokerPlay = String(joker.id);
            if (joker.requires_target_team) {
                btn.dataset.jokerRequiresTarget = '1';
            }
            if (joker.requires_favorite_country) {
                btn.dataset.jokerRequiresFavorite = '1';
            }
            if (joker.requires_confirmation) {
                btn.dataset.jokerRequiresConfirm = '1';
            }
            actions.appendChild(btn);

            actions.hidden = false;

            return;
        }

        if (joker.already_used) {
            const status = document.createElement('div');
            status.className = 'joker-drawer__item-status joker-drawer__item-status--used';
            const badge = document.createElement('span');
            badge.className = 'ta-badge joker-drawer__status-badge';
            badge.textContent = 'Déjà utilisé';
            status.appendChild(badge);
            actions.appendChild(status);

            if (joker.disabled_reason) {
                const hint = document.createElement('p');
                hint.className = 'joker-drawer__unavailable-reason';
                hint.textContent = joker.disabled_reason;
                actions.appendChild(hint);
            }

            actions.hidden = false;

            return;
        }

        const blocked = document.createElement('div');
        blocked.className = 'joker-drawer__unavailable';

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-outline btn-sm joker-drawer__use-btn joker-drawer__use-btn--disabled';
        btn.disabled = true;
        btn.textContent = 'Utiliser ce joker';
        blocked.appendChild(btn);

        const reason = document.createElement('p');
        reason.className = 'joker-drawer__unavailable-reason';
        reason.innerHTML =
            '<i class="ti ti-info-circle" aria-hidden="true"></i><span>' +
            escapeHtml(joker.disabled_reason || 'Ce joker n\'est pas disponible pour ce match.') +
            '</span>';
        blocked.appendChild(reason);
        actions.appendChild(blocked);

        actions.hidden = actions.childElementCount === 0;
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

        renderActiveCard(state.active_on_match);

        if (titleEl) {
            if (state.active_on_match?.name) {
                titleEl.textContent = state.active_on_match.name;
            } else if (state.match_label) {
                titleEl.textContent = 'Jokers — ' + state.match_label;
            }
        }

        if (listLabelEl) {
            const showList =
                !state.espion_intel && state.jokers && state.jokers.length > 0 && !state.active_on_match;
            listLabelEl.hidden = !showList;
        }

        const plannedElsewhere = state.planned_on_other_matches || [];
        if (plannedElsewhere.length > 0 && !state.active_on_match) {
            const note = document.createElement('p');
            note.className = 'joker-dialog-note joker-dialog-note--planned';
            const parts = plannedElsewhere.map(
                (row) => '« ' + row.joker_name + ' » sur ' + row.match_label,
            );
            note.textContent =
                'Jokers déjà planifiés sur d\'autres matchs : ' + parts.join(' · ') + '.';
            listEl.appendChild(note);
        }

        if (state.team_shield_active) {
            const shieldActive = document.createElement('p');
            shieldActive.className = 'joker-dialog-note joker-dialog-shield-active';
            shieldActive.textContent =
                'Bouclier actif pour votre équipe' +
                (state.matchday_label ? ' (journée du ' + state.matchday_label + ')' : '') +
                '.';
            listEl.appendChild(shieldActive);
        }

        if (state.team_favorite_country_name) {
            const favoriteChosen = document.createElement('p');
            favoriteChosen.className = 'joker-dialog-note joker-dialog-favorite-chosen';
            favoriteChosen.textContent =
                'Équipe favorite : ' +
                state.team_favorite_country_name +
                ' (choix secret).';
            listEl.appendChild(favoriteChosen);
        }

        if (state.team_favorite_protection_on_match) {
            const favoriteProtect = document.createElement('p');
            favoriteProtect.className = 'joker-dialog-note joker-dialog-favorite-protect';
            favoriteProtect.textContent =
                'Sur ce match de poule, votre équipe est protégée des jokers adverses qui vous ciblent (équipe favorite).';
            listEl.appendChild(favoriteProtect);
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

        if (state.espion_intel) {
            if (listLabelEl) {
                listLabelEl.hidden = true;
            }

            return;
        }

        (state.jokers || []).forEach((joker) => {
            const item = document.createElement('li');
            item.className = 'joker-drawer__list-item joker-dialog-item';

            const shell = createJokerCardShell();
            fillJokerCardVisual(shell.visual, joker.image);
            fillJokerCardCopy(shell.copy, joker.name, joker.description || null, null);
            appendJokerUseAction(shell.actions, joker);

            item.appendChild(shell.card);
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
        messageEl.className =
            'joker-drawer__message joker-dialog-message ta-card-text' +
            (isError ? ' joker-drawer__message--error joker-dialog-message--error' : ' joker-drawer__message--success joker-dialog-message--success');
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

    const placeJoker = (jokerId, targetTeamId, favoriteCountryId) => {
        if (!currentPlaceUrl) {
            return;
        }

        const body = new FormData();
        body.set('joker_id', String(jokerId));
        if (targetTeamId) {
            body.set('target_team_id', String(targetTeamId));
        }
        if (favoriteCountryId) {
            body.set('favorite_country_id', String(favoriteCountryId));
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
            messageEl.className = 'joker-drawer__message joker-dialog-message ta-card-text';
        }
        if (heroEl) {
            heroEl.hidden = true;
            heroEl.replaceChildren();
        }
        if (activeEl) {
            activeEl.hidden = true;
            activeEl.replaceChildren();
        }
        if (espionEl) {
            espionEl.hidden = true;
            espionEl.replaceChildren();
        }
        if (listLabelEl) {
            listLabelEl.hidden = true;
        }

        dialog.showModal();
        loadState();
    };

    const onDocumentClick = (event) => {
        if (!bindElements()) {
            return;
        }

        if (event.target.closest('[data-joker-catalog]')) {
            return;
        }

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
            const requiresFavorite = playBtn.dataset.jokerRequiresFavorite === '1';
            const joker = findJokerInState(currentPickerState, jokerId);
            if (jokerId && requiresTarget && currentPickerState) {
                if (!confirmPlaceJoker(joker)) {
                    return;
                }
                showTargetPicker(jokerId, currentPickerState);
            } else if (jokerId && requiresFavorite && currentPickerState) {
                if (!confirmPlaceJoker(joker)) {
                    return;
                }
                showFavoriteCountryPicker(jokerId, currentPickerState);
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
            if (pendingPickerMode === 'favorite') {
                placeJoker(pendingJokerId, null, targetSelectEl.value);
            } else {
                placeJoker(pendingJokerId, targetSelectEl.value, null);
            }
            hideTargetPicker();

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
    };

    document.addEventListener('click', onDocumentClick);
    document.addEventListener('DOMContentLoaded', init);
    document.addEventListener('turbo:load', init);
    document.addEventListener('turbo:render', init);

    if (document.readyState !== 'loading') {
        init();
    }
})();

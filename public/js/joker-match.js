(function () {
    const JOKER_TABLER_ICON_BY_CODE = {
        double_equipe: 'ti-users-group',
        pique_points: 'ti-hand-grab',
        espion: 'ti-eye',
        double_buteur: 'ti-ball-football',
        inverse_buteur: 'ti-arrow-back-up',
        inverse_score: 'ti-arrows-left-right',
        bouclier: 'ti-shield',
        collecte_points: 'ti-coins',
        equipe_favorite: 'ti-shield-star',
    };

    const tablerIconForJokerCode = (code) => JOKER_TABLER_ICON_BY_CODE[code] || 'ti-wand';

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
    let jokerDetailOnly = false;

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

    const formatCoteDisplay = (value) => {
        if (value === null || value === undefined) {
            return '—';
        }

        return Number(value).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };

    const jokerTablerIcon = (code) => {
        const icons = {
            double_equipe: 'ti-users-group',
            pique_points: 'ti-hand-grab',
            espion: 'ti-eye',
            double_buteur: 'ti-ball-football',
            inverse_buteur: 'ti-arrow-back-up',
            inverse_score: 'ti-arrows-left-right',
            bouclier: 'ti-shield',
            collecte_points: 'ti-coins',
            equipe_favorite: 'ti-shield-star',
        };

        return icons[code] || 'ti-wand';
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
        jokerDetailOnly = false;
        if (dialog.open) {
            dialog.close();
        }
    };

    const copyJokerActionUrls = (from, to) => {
        if (!from || !to) {
            return;
        }

        to.dataset.jokerActions = '';
        if (from.dataset.matchId) {
            to.dataset.matchId = from.dataset.matchId;
        }
        if (from.dataset.matchLabel) {
            to.dataset.matchLabel = from.dataset.matchLabel;
        }
        if (from.dataset.jokerStateUrl) {
            to.dataset.jokerStateUrl = from.dataset.jokerStateUrl;
        }
        if (from.dataset.jokerPlaceUrl) {
            to.dataset.jokerPlaceUrl = from.dataset.jokerPlaceUrl;
        }
        if (from.dataset.jokerRemoveUrl) {
            to.dataset.jokerRemoveUrl = from.dataset.jokerRemoveUrl;
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

    const buildJokerBadgeTitle = (active) => {
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

    const fillJokerBadge = (badge, active) => {
        if (!badge || !active) {
            return;
        }

        const title = buildJokerBadgeTitle(active);
        const hint = title + ' — Voir le détail';
        badge.title = hint;
        badge.setAttribute('aria-label', hint);
        badge.dataset.jokerBadgeDetail = '';
        badge.setAttribute('aria-haspopup', 'dialog');
        badge.replaceChildren();

        const iconWrap = document.createElement('span');
        iconWrap.className = 'match-card-joker-mark__icon';
        iconWrap.setAttribute('aria-hidden', 'true');
        iconWrap.innerHTML =
            '<i class="ti ' +
            tablerIconForJokerCode(active.code) +
            '" aria-hidden="true"></i>';

        badge.appendChild(iconWrap);

        const sr = document.createElement('span');
        sr.className = 'sr-only';
        sr.textContent = title;
        badge.appendChild(sr);
    };

    const createJokerBadgeElement = () => {
        const badge = document.createElement('button');
        badge.type = 'button';
        badge.className = 'match-card-joker-mark__btn';
        badge.dataset.jokerBadge = '';

        return badge;
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

    const ensureCardMarks = (card) => {
        let marks = card.querySelector('.match-card-marks');
        if (!marks) {
            marks = document.createElement('div');
            marks.className = 'match-card-marks';
            card.prepend(marks);
        }

        return marks;
    };

    const syncMarksMultiClass = (card) => {
        const marks = card.querySelector('.match-card-marks');
        if (!marks) {
            return;
        }

        const count = marks.children.length;
        marks.classList.toggle('match-card-marks--multi', count > 1);
        marks.classList.toggle('match-card-marks--dual', count > 1);
    };

    const ensureJokerMark = (card) => {
        let mark = card.querySelector('.match-card-joker-mark');
        if (!mark) {
            const urlSource =
                card.querySelector('.match-joker-actions[data-joker-actions]') ||
                card.querySelector('[data-joker-actions]:not(.match-card-joker-mark)');
            mark = document.createElement('div');
            mark.className = 'match-card-joker-mark';
            copyJokerActionUrls(urlSource, mark);
            ensureCardMarks(card).appendChild(mark);
        }

        return mark;
    };

    const refreshCardBadge = (matchId, active) => {
        const card = document.querySelector('.match-card[data-match-id="' + matchId + '"]');
        if (!card) {
            return;
        }

        closeJokerPopover(card);

        let badge = card.querySelector('[data-joker-badge]');
        if (active) {
            card.classList.add('match-card--has-joker');
            const mark = ensureJokerMark(card);
            if (!badge) {
                badge = createJokerBadgeElement();
                mark.appendChild(badge);
            } else if (badge.parentElement !== mark) {
                mark.appendChild(badge);
            }
            fillJokerBadge(badge, active);
            syncMarksMultiClass(card);
        } else {
            card.classList.remove('match-card--has-joker');
            const mark = card.querySelector('.match-card-joker-mark');
            if (mark) {
                mark.remove();
            } else if (badge) {
                badge.remove();
            }
            const marks = card.querySelector('.match-card-marks');
            if (marks && marks.children.length === 0) {
                marks.remove();
            } else {
                syncMarksMultiClass(card);
            }
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
        const cotesWrap = document.createElement('div');
        cotesWrap.className = 'match-cotes-overview';
        const count = Number(c.pronostics_count) || 0;
        if (count > 0 && c.mode === 'one_n_two' && c.home != null) {
            cotesWrap.innerHTML =
                '<span class="match-cotes-overview__explain">Cotes 1 / N / 2 sur l’ensemble des pronos.</span>' +
                '<span class="match-cotes-overview__grid">' +
                '<span class="match-cotes-overview__item"><span class="match-cotes-overview__issue">1</span> <strong>×' +
                formatCoteDisplay(c.home) +
                '</strong></span>' +
                '<span class="match-cotes-overview__item"><span class="match-cotes-overview__issue">N</span> <strong>×' +
                formatCoteDisplay(c.draw) +
                '</strong></span>' +
                '<span class="match-cotes-overview__item"><span class="match-cotes-overview__issue">2</span> <strong>×' +
                formatCoteDisplay(c.away) +
                '</strong></span></span>' +
                '<span class="match-espion-meta">(' +
                count +
                ' pronostic' +
                (count > 1 ? 's' : '') +
                ')</span>';
        } else if (c.moyenne != null) {
            const cotesP = document.createElement('p');
            cotesP.className = 'match-espion-cotes';
            let text = 'Moy. ' + formatCoteDisplay(c.moyenne);
            if (c.min != null && c.max != null) {
                text += ' · min ' + formatCoteDisplay(c.min) + ' · max ' + formatCoteDisplay(c.max);
            }
            text += ' (' + count + ' pronostic' + (count > 1 ? 's' : '') + ')';
            cotesP.textContent = text;
            cotesWrap.appendChild(cotesP);
        } else {
            const empty = document.createElement('p');
            empty.className = 'match-espion-empty';
            empty.textContent = 'Pas encore assez de pronostics pour estimer les cotes.';
            cotesWrap.appendChild(empty);
        }
        cotesSection.appendChild(cotesWrap);
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
            const wrap = document.createElement('div');
            wrap.className = 'match-espion-table-wrap';
            const table = document.createElement('table');
            table.className = 'match-espion-jokers-table';
            table.innerHTML =
                '<thead><tr><th scope="col">Équipe</th><th scope="col">Joker</th><th scope="col">Cible / effet</th></tr></thead>';
            const tbody = document.createElement('tbody');
            jokers.forEach((row) => {
                const tr = document.createElement('tr');

                const teamTd = document.createElement('td');
                teamTd.className = 'match-espion-jokers-table__team';
                if (row.team_logo) {
                    const logo = document.createElement('img');
                    logo.src = assetUrl(row.team_logo);
                    logo.alt = '';
                    logo.width = 24;
                    logo.height = 24;
                    logo.loading = 'lazy';
                    logo.className = 'match-espion-team-logo';
                    teamTd.appendChild(logo);
                }
                const teamName = document.createElement('span');
                teamName.className = 'match-espion-team-name';
                teamName.textContent = row.team_name || '';
                teamTd.appendChild(teamName);
                tr.appendChild(teamTd);

                const jokerTd = document.createElement('td');
                jokerTd.className = 'match-espion-jokers-table__joker';
                const iconWrap = document.createElement('span');
                iconWrap.className = 'match-espion-joker-icon';
                iconWrap.setAttribute('aria-hidden', 'true');
                if (row.joker_image) {
                    const img = document.createElement('img');
                    img.src = assetUrl(row.joker_image);
                    img.alt = '';
                    img.width = 28;
                    img.height = 36;
                    img.loading = 'lazy';
                    iconWrap.appendChild(img);
                } else {
                    iconWrap.innerHTML =
                        '<i class="' + escapeHtml(jokerTablerIcon(row.joker_code)) + '" aria-hidden="true"></i>';
                }
                jokerTd.appendChild(iconWrap);
                const jokerLabel = document.createElement('span');
                jokerLabel.className = 'match-espion-joker-label';
                jokerLabel.textContent = row.joker_name || '';
                jokerTd.appendChild(jokerLabel);
                tr.appendChild(jokerTd);

                const targetTd = document.createElement('td');
                targetTd.className = 'match-espion-jokers-table__target';
                if (row.target_team_name) {
                    const target = document.createElement('span');
                    target.className = 'match-espion-target';
                    target.textContent = '→ ' + row.target_team_name;
                    targetTd.appendChild(target);
                } else {
                    const dash = document.createElement('span');
                    dash.className = 'match-espion-meta';
                    dash.textContent = '—';
                    targetTd.appendChild(dash);
                }
                if (row.effect_blocked) {
                    const blocked = document.createElement('span');
                    blocked.className = 'match-espion-blocked';
                    blocked.textContent = ' (sans effet — cible protégée)';
                    targetTd.appendChild(blocked);
                }
                tr.appendChild(targetTd);

                tbody.appendChild(tr);
            });
            table.appendChild(tbody);
            wrap.appendChild(table);
            jokersSection.appendChild(wrap);
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

        const hideChooserExtras = jokerDetailOnly && state.active_on_match;

        const plannedElsewhere = state.planned_on_other_matches || [];
        if (!hideChooserExtras && plannedElsewhere.length > 0 && !state.active_on_match) {
            const note = document.createElement('p');
            note.className = 'joker-dialog-note joker-dialog-note--planned';
            const parts = plannedElsewhere.map(
                (row) => '« ' + row.joker_name + ' » sur ' + row.match_label,
            );
            note.textContent =
                'Jokers déjà planifiés sur d\'autres matchs : ' + parts.join(' · ') + '.';
            listEl.appendChild(note);
        }

        if (!hideChooserExtras && state.team_shield_active) {
            const shieldActive = document.createElement('p');
            shieldActive.className = 'joker-dialog-note joker-dialog-shield-active';
            shieldActive.textContent =
                'Bouclier actif pour votre équipe' +
                (state.matchday_label ? ' (journée du ' + state.matchday_label + ')' : '') +
                '.';
            listEl.appendChild(shieldActive);
        }

        if (!hideChooserExtras && state.team_favorite_country_name) {
            const favoriteChosen = document.createElement('p');
            favoriteChosen.className = 'joker-dialog-note joker-dialog-favorite-chosen';
            favoriteChosen.textContent =
                'Équipe favorite : ' +
                state.team_favorite_country_name +
                ' (choix secret).';
            listEl.appendChild(favoriteChosen);
        }

        if (!hideChooserExtras && state.team_favorite_protection_on_match) {
            const favoriteProtect = document.createElement('p');
            favoriteProtect.className = 'joker-dialog-note joker-dialog-favorite-protect';
            favoriteProtect.textContent =
                'Sur ce match de poule, votre équipe est protégée des jokers adverses qui vous ciblent (équipe favorite).';
            listEl.appendChild(favoriteProtect);
        }

        if (!hideChooserExtras && state.team_buteur_countries && state.team_buteur_countries.length > 0) {
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

        if (hideChooserExtras) {
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

    const openDialog = (el, options) => {
        const opts = options || {};
        jokerDetailOnly = Boolean(opts.detailOnly);
        const urls = readUrlsFromElement(el);
        currentMatchId = urls.matchId;
        currentStateUrl = urls.stateUrl;
        currentPlaceUrl = urls.placeUrl;
        currentRemoveUrl = urls.removeUrl;

        if (titleEl) {
            titleEl.textContent = opts.detailOnly
                ? 'Joker posé'
                : 'Jokers — ' + urls.matchLabel;
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

        const badgeDetail = event.target.closest('[data-joker-badge-detail]');
        if (badgeDetail) {
            event.preventDefault();
            event.stopPropagation();
            const source =
                badgeDetail.closest('.match-card-joker-mark') ||
                badgeDetail.closest('.match-card')?.querySelector('[data-joker-actions]');
            if (!source) {
                return;
            }
            closeAllJokerPopovers();
            openDialog(source, { detailOnly: true });

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

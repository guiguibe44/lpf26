(function () {
    const teamsRoot = document.getElementById('match-live-teams');

    const initExpandRows = (root) => {
        if (!root) {
            return;
        }

        root.querySelectorAll('[data-match-live-expand]').forEach((button) => {
            button.addEventListener('click', (event) => {
                if (event.target.closest('[data-match-joker-info-open]')) {
                    return;
                }

                const detailId = button.getAttribute('aria-controls');
                if (!detailId) {
                    return;
                }

                const detailRow = document.getElementById(detailId);
                if (!detailRow) {
                    return;
                }

                const willOpen = detailRow.hidden;
                detailRow.hidden = !willOpen;
                button.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
                button.classList.toggle('match-live-expand-btn--open', willOpen);
                button.title = willOpen ? 'Masquer le détail' : 'Afficher le détail';
            });
        });
    };

    initExpandRows(teamsRoot);

    const config = window.matchLiveConfig;
    if (!config || !config.simulateUrl) {
        return;
    }

    const form = document.getElementById('match-live-simulator');
    const homeInput = document.getElementById('sim-score-home');
    const awayInput = document.getElementById('sim-score-away');
    const resetBtn = document.getElementById('match-live-reset-score');
    const scoreHomeEl = document.getElementById('match-live-score-home');
    const scoreAwayEl = document.getElementById('match-live-score-away');

    if (!form || !homeInput || !awayInput || !teamsRoot) {
        return;
    }

    const cotesVisible = config.cotesVisible !== false && teamsRoot.dataset.cotesVisible !== '0';

    let debounceTimer = null;

    const formatPoints = (value) => {
        const n = Math.round(Number(value) || 0);
        return n.toLocaleString('fr-FR') + ' pts';
    };

    const formatCellNumber = (value) => {
        return Math.round(Number(value) || 0).toLocaleString('fr-FR');
    };

    const formatCote = (value) => {
        return Number(value).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };

    const formatCoteCoef = (value) => {
        if (value === null || value === undefined) {
            return '—';
        }

        return '×' + formatCote(value);
    };

    const formatCoteCoefShort = (value) => {
        if (value === null || value === undefined) {
            return '—';
        }

        const num = Number(value);
        const digits = Number.isInteger(num) || num % 0.5 === 0 ? 1 : 2;

        return '×' + num.toLocaleString('fr-FR', { minimumFractionDigits: digits, maximumFractionDigits: digits });
    };

    const setBlockValue = (blockEl, value) => {
        if (!blockEl) {
            return;
        }

        const valueEl = blockEl.querySelector('.match-cotes-1n2-blocks__value');
        if (valueEl) {
            valueEl.textContent = formatCoteCoefShort(value);
        }
    };

    const setActiveCoteOutcome = (banner, outcome) => {
        if (!banner) {
            return;
        }

        banner.querySelectorAll('[data-cotes-outcome]').forEach((block) => {
            const isActive = Boolean(outcome) && block.dataset.cotesOutcome === outcome;
            block.classList.toggle('match-cotes-1n2-blocks__item--active', isActive);
        });
    };

    const updateCotesBanner = (cotes) => {
        const banner = document.getElementById('match-live-cotes-banner');
        if (!banner || !cotes) {
            return;
        }

        const count = Number(cotes.pronostics_count) || 0;
        banner.hidden = count === 0;

        const forScoreEl = document.getElementById('match-live-cotes-for-score');
        const minEl = document.getElementById('match-live-cotes-min');
        const avgEl = document.getElementById('match-live-cotes-avg');
        const maxEl = document.getElementById('match-live-cotes-max');

        if (forScoreEl) {
            forScoreEl.textContent = formatCoteCoef(cotes.for_score);
        }

        setBlockValue(banner.querySelector('[data-cotes-outcome="HOME"]'), cotes.home);
        setBlockValue(banner.querySelector('[data-cotes-outcome="DRAW"]'), cotes.draw);
        setBlockValue(banner.querySelector('[data-cotes-outcome="AWAY"]'), cotes.away);
        setActiveCoteOutcome(banner, cotes.for_outcome ?? null);

        if (minEl) {
            minEl.textContent = formatCoteCoef(cotes.min);
        }
        if (avgEl) {
            avgEl.textContent = formatCoteCoef(cotes.moyenne);
        }
        if (maxEl) {
            maxEl.textContent = formatCoteCoef(cotes.max);
        }
    };

    const teamLogoUrl = (logo) => {
        if (!logo) {
            return null;
        }
        if (logo.startsWith('http://') || logo.startsWith('https://')) {
            return logo;
        }

        return '/' + String(logo).replace(/^\//, '');
    };

    const teamShowUrl = (teamId) => {
        const base = config.teamShowUrlBase || '/equipes/';
        return base + String(teamId);
    };

    const jokerImageUrl = (image) => {
        if (!image) {
            return null;
        }
        if (image.startsWith('http://') || image.startsWith('https://')) {
            return image;
        }

        return '/' + String(image).replace(/^\//, '');
    };

    const renderJokerBadges = (container, badges) => {
        if (!container) {
            return;
        }

        container.replaceChildren();

        if (!Array.isArray(badges) || badges.length === 0) {
            container.hidden = true;

            return;
        }

        container.hidden = false;
        const isRow = container.hasAttribute('data-team-joker-badges-inline');
        const list = document.createElement('ul');
        list.className = 'match-live-team-jokers' + (isRow ? ' match-live-team-jokers--row' : '');
        list.setAttribute('aria-label', 'Jokers actifs sur ce match');

        badges.forEach((badge) => {
            const li = document.createElement('li');
            li.className = 'match-live-joker-badge match-live-joker-badge--' + String(badge.kind || 'own');

            const trigger = document.createElement('button');
            trigger.type = 'button';
            trigger.className = 'match-live-joker-badge__trigger';
            trigger.setAttribute('data-match-joker-info-open', '');
            trigger.setAttribute(
                'data-joker-info',
                JSON.stringify({
                    name: badge.name || '',
                    label: badge.label || '',
                    kind: badge.kind || '',
                    image: badge.image || '',
                    description: badge.description || '',
                    technical_lines: badge.technical_lines || [],
                }),
            );
            trigger.setAttribute('aria-label', 'Détails du joker ' + String(badge.name || ''));
            trigger.title = String(badge.name || '');

            const chip = document.createElement('span');
            chip.className = 'match-live-joker-chip match-live-joker-chip--' + String(badge.kind || 'own');
            chip.setAttribute('aria-hidden', 'true');
            const icon = document.createElement('i');
            icon.className = 'ti ' + String(badge.icon || 'ti-wand');
            icon.setAttribute('aria-hidden', 'true');
            chip.appendChild(icon);
            trigger.appendChild(chip);

            if (!isRow) {
                const name = document.createElement('span');
                name.className = 'match-live-joker-badge__name';
                name.textContent = String(badge.name || '');
                trigger.appendChild(name);
            }

            li.appendChild(trigger);
            list.appendChild(li);
        });

        container.appendChild(list);
    };

    const updateKdoOutlook = (kdo) => {
        const tbody = document.getElementById('match-live-kdo-tbody');
        const scoreLabel = document.getElementById('match-live-kdo-score-label');
        if (!tbody) {
            return;
        }

        if (scoreLabel && kdo) {
            scoreLabel.textContent = String(kdo.scoreDomicile) + ' - ' + String(kdo.scoreExterieur);
        }

        tbody.replaceChildren();

        if (!kdo || !Array.isArray(kdo.potentialWinners) || kdo.potentialWinners.length === 0) {
            const row = document.createElement('tr');
            row.id = 'match-live-kdo-empty-row';
            const cell = document.createElement('td');
            cell.colSpan = 4;
            cell.className = 'ta-card-text';
            cell.textContent = 'Aucune équipe avec le score exact pour ce résultat.';
            row.appendChild(cell);
            tbody.appendChild(row);

            return;
        }

        kdo.potentialWinners.forEach((row) => {
            const tr = document.createElement('tr');
            tr.dataset.teamId = String(row.teamId);
            if (row.isWinner) {
                tr.classList.add('match-live-kdo-row--winner');
            }

            const teamCell = document.createElement('td');
            const teamWrap = document.createElement('div');
            teamWrap.className = 'match-live-kdo-team-cell';

            const logoUrl = teamLogoUrl(row.teamLogo);
            if (logoUrl) {
                const img = document.createElement('img');
                img.src = logoUrl;
                img.alt = '';
                img.className = 'match-live-kdo-team-logo';
                img.width = 28;
                img.height = 28;
                img.loading = 'lazy';
                teamWrap.appendChild(img);
            }

            const link = document.createElement('a');
            link.href = teamShowUrl(row.teamId);
            link.textContent = row.teamName;
            teamWrap.appendChild(link);
            teamCell.appendChild(teamWrap);
            tr.appendChild(teamCell);

            const exactCell = document.createElement('td');
            exactCell.className = 'ta-num';
            exactCell.dataset.kdoExact = '';
            exactCell.textContent = String(row.exactScoresCount);
            tr.appendChild(exactCell);

            const rankCell = document.createElement('td');
            rankCell.className = 'ta-num';
            rankCell.dataset.kdoRank = '';
            rankCell.textContent = row.rankingPositionBefore ? '#' + row.rankingPositionBefore : '—';
            tr.appendChild(rankCell);

            const statusCell = document.createElement('td');
            statusCell.dataset.kdoStatus = '';
            const badge = document.createElement('span');
            badge.className = row.isWinner ? 'ta-badge ta-badge-success' : 'ta-badge';
            badge.textContent = row.isWinner ? 'Gagnant du cadeau' : 'En lice';
            statusCell.appendChild(badge);
            tr.appendChild(statusCell);

            tbody.appendChild(tr);
        });
    };

    const updatePronoCalcMeta = (calcMeta, prono, showCotes) => {
        if (!calcMeta) {
            return;
        }

        calcMeta.querySelectorAll('[data-prono-joker-sep], [data-prono-joker-factor], [data-prono-calc-note]').forEach((el) => {
            el.remove();
        });

        const baseEl = calcMeta.querySelector('[data-prono-base]');
        const coteEl = calcMeta.querySelector('[data-prono-cote]');
        const coteSepEl = calcMeta.querySelector('[data-prono-cote-sep]');

        if (baseEl) {
            baseEl.textContent = String(prono.basePoints);
        }
        if (coteSepEl) {
            coteSepEl.hidden = !showCotes;
        }
        if (coteEl) {
            if (showCotes) {
                coteEl.hidden = false;
                coteEl.textContent = formatCote(prono.coefficient);
            } else {
                coteEl.hidden = true;
                coteEl.textContent = '';
            }
        }

        (prono.calcMultipliers || []).forEach((mult) => {
            const sep = document.createElement('span');
            sep.className = 'match-live-prono-cote-sep';
            sep.setAttribute('data-prono-joker-sep', '');
            sep.textContent = '×';
            calcMeta.appendChild(sep);

            const factor = document.createElement('span');
            factor.className = 'match-live-prono-joker-factor';
            factor.setAttribute('data-prono-joker-factor', '');
            if (mult.label) {
                factor.title = String(mult.label);
            }
            factor.textContent = String(mult.factor);
            calcMeta.appendChild(factor);
        });

        (prono.calcNotes || []).forEach((note) => {
            const noteEl = document.createElement('span');
            noteEl.className = 'match-live-prono-calc-note';
            noteEl.setAttribute('data-prono-calc-note', '');
            noteEl.title = String(note);
            noteEl.textContent = String(note);
            calcMeta.appendChild(noteEl);
        });
    };

    const updateViewerPronostic = (viewer) => {
        const block = document.querySelector('[data-match-viewer-prono]');
        if (!block) {
            return;
        }

        if (!viewer) {
            block.hidden = true;

            return;
        }

        block.hidden = false;

        const scoreEl = block.querySelector('[data-viewer-prono-score]');
        if (scoreEl) {
            scoreEl.textContent = String(viewer.pred_home) + ' - ' + String(viewer.pred_away);
        }

        const invertEl = block.querySelector('[data-viewer-prono-invert]');
        if (invertEl) {
            invertEl.hidden = !viewer.score_inverted;
        }

        const pointsEl = block.querySelector('[data-viewer-prono-points]');
        if (pointsEl) {
            pointsEl.textContent = formatCellNumber(viewer.points) + ' pts';
        }
    };

    const updateTeams = (data) => {
        if (!data || !Array.isArray(data.teams)) {
            return;
        }

        if (scoreHomeEl) {
            scoreHomeEl.textContent = String(data.scoreDomicile);
        }
        if (scoreAwayEl) {
            scoreAwayEl.textContent = String(data.scoreExterieur);
        }

        data.teams.forEach((team) => {
            const teamSection = teamsRoot.querySelector('tbody[data-team-id="' + team.teamId + '"]');
            if (!teamSection) {
                return;
            }

            const simulatedPosEl = teamSection.querySelector('[data-simulated-position]');
            if (simulatedPosEl) {
                simulatedPosEl.textContent = '#' + team.simulatedRankingPosition;
            }

            teamSection.querySelectorAll('[data-team-total-general]').forEach((el) => {
                el.textContent = formatCellNumber(team.simulatedTotalPoints);
            });

            const matchPts =
                Number(team.matchPoints) ||
                Number(team.pronosticMatchPoints || 0) + Number(team.buteurMatchPoints || 0);
            teamSection.querySelectorAll('[data-team-match-total]').forEach((el) => {
                el.textContent = formatCellNumber(matchPts);
            });

            teamSection.querySelectorAll('[data-team-prono-points]').forEach((el) => {
                el.textContent = formatCellNumber(team.pronosticMatchPoints);
            });

            teamSection.querySelectorAll('[data-team-buteur-points]').forEach((el) => {
                el.textContent = formatCellNumber(team.buteurMatchPoints);
            });

            const mainRow = teamSection.querySelector('.match-live-row');
            const hasJokerBadges = Array.isArray(team.jokerBadges) && team.jokerBadges.length > 0;
            if (mainRow) {
                mainRow.classList.toggle('match-live-row--has-joker', hasJokerBadges);
            }

            const inlineBadgesHost = teamSection.querySelector('[data-team-joker-badges-inline]');
            if (inlineBadgesHost) {
                renderJokerBadges(inlineBadgesHost, team.jokerBadges || []);
            }

            const detailBadgesHost = teamSection.querySelector('[data-team-joker-badges]');
            if (detailBadgesHost) {
                renderJokerBadges(detailBadgesHost, team.jokerBadges || []);
                detailBadgesHost.hidden = !hasJokerBadges;
            }

            const detailPanelRow = teamSection.querySelector('.match-live-detail-panel__row');
            if (detailPanelRow) {
                detailPanelRow.classList.toggle('match-live-detail-panel__row--has-jokers', hasJokerBadges);
            }

            (team.pronostics || []).forEach((prono) => {
                const blocks = teamSection.querySelectorAll('[data-pronostic-id="' + prono.pronosticId + '"]');
                if (blocks.length === 0) {
                    return;
                }

                const scoreText = String(prono.predHome) + '-' + String(prono.predAway);

                blocks.forEach((block) => {
                    block.querySelectorAll('[data-prono-score-value]').forEach((scoreEl) => {
                        scoreEl.textContent = scoreText;
                    });
                    const invert = block.querySelector('.match-live-prono-invert');
                    if (invert) {
                        invert.hidden = !prono.scoreInverted;
                    }

                    const calcMeta = block.querySelector('[data-prono-meta]');
                    if (calcMeta) {
                        updatePronoCalcMeta(calcMeta, prono, cotesVisible);
                    }

                    const pointsEl = block.querySelector('[data-prono-points]');
                    if (pointsEl) {
                        const displayPts =
                            prono.teamPoints !== undefined && prono.teamPoints !== null
                                ? prono.teamPoints
                                : prono.points;
                        pointsEl.textContent = formatCellNumber(displayPts);
                    }
                });
            });
        });

        if (data.cotes) {
            updateCotesBanner(data.cotes);
        }

        if (data.kdoOutlook) {
            updateKdoOutlook(data.kdoOutlook);
        }

        if (data.viewerPronostic) {
            updateViewerPronostic(data.viewerPronostic);
        }
    };

    const fetchSimulation = () => {
        const home = Math.max(0, Math.min(30, parseInt(homeInput.value, 10) || 0));
        const away = Math.max(0, Math.min(30, parseInt(awayInput.value, 10) || 0));
        homeInput.value = String(home);
        awayInput.value = String(away);

        const url = new URL(config.simulateUrl, window.location.origin);
        url.searchParams.set('domicile', String(home));
        url.searchParams.set('exterieur', String(away));

        fetch(url.toString(), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error('Simulation impossible');
                }
                return response.json();
            })
            .then(updateTeams)
            .catch(() => {
                /* silencieux : l’affichage initial reste */
            });
    };

    const scheduleSimulation = () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(fetchSimulation, 280);
    };

    form.addEventListener('input', scheduleSimulation);
    form.addEventListener('change', scheduleSimulation);

    if (resetBtn) {
        resetBtn.addEventListener('click', () => {
            homeInput.value = String(config.defaultHome ?? 0);
            awayInput.value = String(config.defaultAway ?? 0);
            scheduleSimulation();
        });
    }
})();

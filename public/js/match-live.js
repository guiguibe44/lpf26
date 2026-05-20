(function () {
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
    const teamsRoot = document.getElementById('match-live-teams');

    if (!form || !homeInput || !awayInput || !teamsRoot) {
        return;
    }

    const cotesVisible = config.cotesVisible !== false && teamsRoot.dataset.cotesVisible !== '0';

    let debounceTimer = null;

    const formatPoints = (value) => {
        const n = Math.round(Number(value) || 0);
        return n.toLocaleString('fr-FR') + ' pts';
    };

    const formatGeneralPoints = (value) => {
        const n = Math.round(Number(value) || 0);
        return n.toLocaleString('fr-FR') + ' pts gen.';
    };

    const formatCote = (value) => {
        return Number(value).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
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

            const matchTotalEl = teamSection.querySelector('[data-team-total]');
            if (matchTotalEl) {
                matchTotalEl.textContent = formatPoints(team.matchPoints);
            }

            const generalTotalEl = teamSection.querySelector('[data-team-total-general]');
            if (generalTotalEl) {
                generalTotalEl.textContent = formatGeneralPoints(team.simulatedTotalPoints);
            }

            const jokerEl = teamSection.querySelector('[data-team-joker]');
            if (jokerEl) {
                if (team.activeJoker && team.activeJoker.name) {
                    jokerEl.hidden = false;
                    let jokerLabel = team.activeJoker.name;
                    if (team.activeJoker.target_team_name) {
                        jokerLabel += ' → ' + team.activeJoker.target_team_name;
                    }
                    jokerEl.textContent = jokerLabel;
                } else {
                    jokerEl.hidden = true;
                }
            }

            (team.pronostics || []).forEach((prono) => {
                const row = teamSection.querySelector('[data-pronostic-id="' + prono.pronosticId + '"]');
                if (!row) {
                    return;
                }

                const baseEl = row.querySelector('[data-prono-base]');
                const coteEl = row.querySelector('[data-prono-cote]');
                const coteSepEl = row.querySelector('[data-prono-cote-sep]');
                const pointsEl = row.querySelector('[data-prono-points]');
                const riskEl = row.querySelector('[data-prono-risk]');

                if (baseEl) {
                    baseEl.textContent = String(prono.basePoints);
                }
                if (coteSepEl) {
                    coteSepEl.hidden = !cotesVisible;
                }
                if (coteEl) {
                    if (cotesVisible) {
                        coteEl.hidden = false;
                        coteEl.textContent = formatCote(prono.coefficient);
                    } else {
                        coteEl.hidden = true;
                        coteEl.textContent = '';
                    }
                }
                if (pointsEl) {
                    pointsEl.textContent = formatPoints(prono.points);
                }
                if (riskEl) {
                    if (prono.priseRisque) {
                        riskEl.hidden = false;
                        riskEl.textContent = 'Risque';
                    } else {
                        riskEl.hidden = true;
                    }
                }
            });
        });

        if (data.kdoOutlook) {
            updateKdoOutlook(data.kdoOutlook);
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

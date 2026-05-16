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
            const card = teamsRoot.querySelector('[data-team-id="' + team.teamId + '"]');
            if (!card) {
                return;
            }

            const simulatedPosEl = card.querySelector('[data-simulated-position]');
            if (simulatedPosEl) {
                simulatedPosEl.textContent = '#' + team.simulatedRankingPosition;
            }

            const matchTotalEl = card.querySelector('[data-team-total]');
            if (matchTotalEl) {
                matchTotalEl.textContent = formatPoints(team.matchPoints);
            }

            const generalTotalEl = card.querySelector('[data-team-total-general]');
            if (generalTotalEl) {
                generalTotalEl.textContent = formatGeneralPoints(team.simulatedTotalPoints);
            }

            (team.pronostics || []).forEach((prono) => {
                const row = card.querySelector('[data-pronostic-id="' + prono.pronosticId + '"]');
                if (!row) {
                    return;
                }

                const baseEl = row.querySelector('[data-prono-base]');
                const coteEl = row.querySelector('[data-prono-cote]');
                const pointsEl = row.querySelector('[data-prono-points]');
                const riskEl = row.querySelector('[data-prono-risk]');

                if (baseEl) {
                    baseEl.textContent = String(prono.basePoints);
                }
                if (coteEl) {
                    coteEl.textContent = formatCote(prono.coefficient);
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

            teamsRoot.appendChild(card);
        });
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

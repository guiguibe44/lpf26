/**
 * Rafraîchit scores et buteurs marqueurs sur les cartes match en direct.
 */
(function () {
    const POLL_MS = 60000;

    function updateGoalsList(card, goals) {
        let list = card.querySelector('[data-match-goals]');
        const wrap = card.querySelector('.match-goals-wrap');

        if (!goals || goals.length === 0) {
            if (list) {
                list.innerHTML = '';
                list.hidden = true;
            }
            return;
        }

        if (!list && wrap) {
            list = document.createElement('ul');
            list.className = 'match-goals-list';
            list.setAttribute('data-match-goals', '');
            wrap.appendChild(list);
        }

        if (!list) {
            return;
        }

        list.hidden = false;
        list.innerHTML = goals
            .map((goal) => {
                const minute = goal.minute ? `<span class="match-goals-minute">${goal.minute}'</span>` : '';
                return `<li class="match-goals-item"><span class="match-goals-scorer">${escapeHtml(goal.name)}</span>${minute}</li>`;
            })
            .join('');
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = String(text);
        return div.innerHTML;
    }

    function applyFeed(data) {
        if (!data || !Array.isArray(data.matches)) {
            return;
        }

        data.matches.forEach((match) => {
            const card = document.querySelector(`.match-card[data-match-id="${match.id}"]`);
            if (!card) {
                return;
            }

            const homeEl = card.querySelector('[data-match-score-home]');
            const awayEl = card.querySelector('[data-match-score-away]');
            if (homeEl) {
                homeEl.textContent = String(match.score_home ?? 0);
            }
            if (awayEl) {
                awayEl.textContent = String(match.score_away ?? 0);
            }

            const elapsedEl = card.querySelector('[data-match-elapsed]');
            if (elapsedEl) {
                if (match.elapsed_minute) {
                    elapsedEl.textContent = `${match.elapsed_minute}'`;
                    elapsedEl.hidden = false;
                } else {
                    elapsedEl.hidden = true;
                }
            }

            updateGoalsList(card, match.goals);
        });
    }

    function poll() {
        fetch('/api/matchs/live-feed', {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }
                return response.json();
            })
            .then(applyFeed)
            .catch(() => {
                /* silencieux : prochain poll */
            });
    }

    if (document.querySelector('.match-day-section--live')) {
        poll();
        window.setInterval(poll, POLL_MS);
    }
})();

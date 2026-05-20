/**
 * Rafraîchit scores et buteurs marqueurs sur les cartes match en direct.
 */
(function () {
    const POLL_MS = 60000;

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = String(text);
        return div.innerHTML;
    }

    function escapeAttr(text) {
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;');
    }

    function goalPhotoUrl(photo) {
        if (!photo) {
            return null;
        }
        if (photo.startsWith('http://') || photo.startsWith('https://')) {
            return photo;
        }

        return '/' + String(photo).replace(/^\//, '');
    }

    function renderGoalItem(goal) {
        const photoUrl = goalPhotoUrl(goal.photo);
        const initial = escapeHtml((goal.name || '?').charAt(0).toUpperCase());
        const photoMarkup = photoUrl
            ? `<img class="match-goals-photo" src="${escapeAttr(photoUrl)}" alt="" width="28" height="28" loading="lazy" decoding="async">`
            : `<span class="match-goals-photo match-goals-photo--placeholder" aria-hidden="true">${initial}</span>`;

        const minuteMarkup =
            goal.minute != null && goal.minute !== ''
                ? `<span class="match-goals-minute">${escapeHtml(goal.minute)}'</span>`
                : '';

        return `<li class="match-goals-item">${photoMarkup}<span class="match-goals-scorer"><span class="match-goals-name">${escapeHtml(goal.name)}</span>${minuteMarkup}</span></li>`;
    }

    function renderGoalsSide(goals, side) {
        const filtered = (goals || []).filter((goal) => (goal.side || 'home') === side);
        if (filtered.length === 0) {
            return '';
        }

        const sideClass = side === 'away' ? 'match-goals-side--away' : 'match-goals-side--home';
        const label = side === 'away' ? 'Buteurs extérieur' : 'Buteurs domicile';

        return `<ul class="match-goals-side ${sideClass}" aria-label="${label}">${filtered.map(renderGoalItem).join('')}</ul>`;
    }

    function updateGoalsList(card, goals) {
        const wrap = card.querySelector('.match-goals-wrap');
        if (!wrap) {
            return;
        }

        let sides = wrap.querySelector('[data-match-goals]');

        if (!goals || goals.length === 0) {
            if (sides) {
                sides.hidden = true;
                sides.innerHTML = '';
            }
            return;
        }

        if (!sides) {
            sides = document.createElement('div');
            sides.className = 'match-goals-sides';
            sides.setAttribute('data-match-goals', '');
            wrap.appendChild(sides);
        }

        sides.hidden = false;
        sides.innerHTML = renderGoalsSide(goals, 'home') + renderGoalsSide(goals, 'away');
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

            const clockEl = card.querySelector('[data-match-live-clock]');
            if (clockEl && match.live_clock) {
                clockEl.textContent = String(match.live_clock);
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

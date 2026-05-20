(function () {
    const DEBOUNCE_MS = 1000;
    const TOAST_HIDE_MS = 2200;

    let toastEl = null;
    let toastHideTimer = null;

    const parseScore = (value) => {
        const raw = String(value ?? '').trim();
        if ('' === raw) {
            return null;
        }
        if (!/^\d+$/.test(raw)) {
            return null;
        }
        const n = Number.parseInt(raw, 10);

        return Number.isFinite(n) && n >= 0 ? n : null;
    };

    const getToast = () => {
        if (!toastEl) {
            toastEl = document.createElement('div');
            toastEl.className = 'match-pronostic-toast';
            toastEl.setAttribute('role', 'status');
            toastEl.setAttribute('aria-live', 'polite');
            toastEl.hidden = true;
            document.body.appendChild(toastEl);
        }

        return toastEl;
    };

    const setToast = (state, message) => {
        const el = getToast();
        window.clearTimeout(toastHideTimer);

        el.classList.remove(
            'match-pronostic-toast--idle',
            'match-pronostic-toast--saving',
            'match-pronostic-toast--saved',
            'match-pronostic-toast--error',
        );

        if ('idle' === state) {
            el.textContent = '';
            el.hidden = true;

            return;
        }

        el.hidden = false;
        el.classList.add('match-pronostic-toast--' + state);
        el.textContent = message || '';

        if ('saved' === state) {
            toastHideTimer = window.setTimeout(() => {
                setToast('idle');
            }, TOAST_HIDE_MS);
        }
    };

    const readScores = (form) => {
        const homeInput = form.querySelector('[name="score_domicile"]');
        const awayInput = form.querySelector('[name="score_exterieur"]');
        if (!homeInput || !awayInput) {
            return null;
        }

        const home = parseScore(homeInput.value);
        const away = parseScore(awayInput.value);
        if (null === home || null === away) {
            return null;
        }

        return { home, away, homeInput, awayInput };
    };

    const updatePointsCell = (form, payload) => {
        if (!payload || typeof payload.points !== 'number') {
            return;
        }

        const matchId = form.getAttribute('data-match-id');
        if (!matchId) {
            return;
        }

        const cell = document.querySelector('[data-match-pronostic-points][data-match-id="' + matchId + '"]');
        if (!cell) {
            return;
        }

        const badge = cell.querySelector('[data-match-pronostic-points-value]');
        if (badge) {
            badge.textContent = new Intl.NumberFormat('fr-FR').format(payload.points);
        }
    };

    const bindForm = (form) => {
        if (form.dataset.matchPronosticAutosaveBound === '1') {
            return;
        }

        form.dataset.matchPronosticAutosaveBound = '1';

        const saveUrl = form.getAttribute('action') || form.dataset.saveUrl;
        if (!saveUrl) {
            return;
        }

        let debounceTimer = null;
        let inFlight = false;
        let queued = false;
        let lastSaved = readScores(form);

        const persist = () => {
            const scores = readScores(form);
            if (!scores) {
                setToast('error', 'Scores invalides');

                return;
            }

            if (
                lastSaved
                && lastSaved.home === scores.home
                && lastSaved.away === scores.away
            ) {
                return;
            }

            if (inFlight) {
                queued = true;

                return;
            }

            inFlight = true;
            setToast('saving', 'Enregistrement…');

            const body = new FormData();
            body.set('score_domicile', String(scores.home));
            body.set('score_exterieur', String(scores.away));

            fetch(saveUrl, {
                method: 'POST',
                body,
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            })
                .then((response) => response.json().then((data) => ({ response, data })))
                .then(({ response, data }) => {
                    if (!response.ok || !data.ok) {
                        const msg = data && data.message ? data.message : 'Enregistrement impossible.';

                        throw new Error(msg);
                    }

                    lastSaved = { home: scores.home, away: scores.away };
                    setToast('saved', 'Enregistré');
                    updatePointsCell(form, data);
                })
                .catch((error) => {
                    setToast(
                        'error',
                        error && error.message ? error.message : 'Erreur réseau',
                    );
                })
                .finally(() => {
                    inFlight = false;
                    if (queued) {
                        queued = false;
                        persist();
                    }
                });
        };

        const scheduleSave = () => {
            window.clearTimeout(debounceTimer);
            debounceTimer = window.setTimeout(persist, DEBOUNCE_MS);
        };

        form.addEventListener('submit', (event) => {
            event.preventDefault();
            window.clearTimeout(debounceTimer);
            persist();
        });

        form.querySelectorAll('[name="score_domicile"], [name="score_exterieur"]').forEach((input) => {
            input.addEventListener('input', scheduleSave);
            input.addEventListener('change', scheduleSave);
            input.addEventListener('blur', () => {
                window.clearTimeout(debounceTimer);
                debounceTimer = window.setTimeout(persist, DEBOUNCE_MS);
            });
        });
    };

    const init = () => {
        document.querySelectorAll('[data-match-pronostic-autosave]').forEach(bindForm);
    };

    document.addEventListener('DOMContentLoaded', init);
    document.addEventListener('turbo:load', init);
})();

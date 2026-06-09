/**
 * Animation de déblocage badge → menu Compte.
 */
(function initBadgeUnlock() {
    const handledThisPage = new Set();
    let pendingQueue = [];
    let isRunning = false;

    const updateMenuDot = (visible) => {
        const dot = document.querySelector('[data-badge-menu-dot]');
        if (dot) {
            dot.hidden = !visible;
        }
    };

    const markSeen = async (ids) => {
        if (!ids.length) {
            return;
        }
        try {
            await fetch('/api/badges/mark-seen', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                },
                body: JSON.stringify({ ids }),
                credentials: 'same-origin',
            });
        } catch (e) {
            /* ignore */
        }
    };

    const DISPLAY_MS = 20000;
    const FLY_MS = 900;

    const buildFlyerHtml = (badge) => {
        const media = badge.image
            ? `<img src="${badge.image}" alt="" class="badge-unlock-flyer__image">`
            : `<span class="badge-unlock-flyer__icon"><i class="ti ${badge.icon || 'ti-medal'}"></i></span>`;

        return `
            <div class="badge-unlock-overlay" data-badge-unlock-overlay>
                <div class="badge-unlock-stage">
                    <div class="badge-unlock-card" data-badge-unlock-card>
                        <button type="button" class="badge-unlock-card__close" data-badge-unlock-close aria-label="Fermer et envoyer vers Mon compte">
                            <i class="ti ti-x" aria-hidden="true"></i>
                        </button>
                        ${media}
                        <p class="badge-unlock-card__label">Nouveau badge</p>
                        <p class="badge-unlock-card__name">${badge.name}</p>
                        <p class="badge-unlock-card__category">${badge.category}</p>
                    </div>
                    <div class="badge-unlock-flyer" data-badge-unlock-flyer hidden>
                        ${media}
                    </div>
                </div>
            </div>
        `;
    };

    const animateOne = (badge) => new Promise((resolve) => {
        const target = document.querySelector('[data-badge-account-target]');
        if (!target) {
            markSeen([badge.id]).then(() => resolve());
            return;
        }

        document.body.insertAdjacentHTML('beforeend', buildFlyerHtml(badge));
        const overlay = document.querySelector('[data-badge-unlock-overlay]');
        const card = overlay?.querySelector('[data-badge-unlock-card]');
        const flyer = overlay?.querySelector('[data-badge-unlock-flyer]');
        const closeBtn = overlay?.querySelector('[data-badge-unlock-close]');
        if (!overlay || !card || !flyer) {
            overlay?.remove();
            markSeen([badge.id]).then(() => resolve());
            return;
        }

        let finished = false;
        let displayTimer = null;
        let flyStarted = false;

        const finish = async () => {
            if (finished) {
                return;
            }
            finished = true;
            if (displayTimer) {
                window.clearTimeout(displayTimer);
            }
            overlay.remove();
            handledThisPage.add(String(badge.id));
            await markSeen([badge.id]);
            resolve();
        };

        const runFly = () => {
            if (flyStarted || finished) {
                return;
            }
            flyStarted = true;
            if (displayTimer) {
                window.clearTimeout(displayTimer);
            }

            const startRect = card.getBoundingClientRect();
            const targetRect = target.getBoundingClientRect();
            const startX = startRect.left + startRect.width / 2;
            const startY = startRect.top + startRect.height / 2;
            const destX = targetRect.left + targetRect.width / 2;
            const destY = targetRect.top + targetRect.height / 2;

            card.hidden = true;
            overlay.classList.add('badge-unlock-overlay--flying');

            flyer.hidden = false;
            flyer.style.left = `${startX}px`;
            flyer.style.top = `${startY}px`;
            flyer.style.transform = 'translate(-50%, -50%) scale(1)';
            flyer.style.opacity = '1';

            window.requestAnimationFrame(() => {
                window.requestAnimationFrame(() => {
                    flyer.style.left = `${destX}px`;
                    flyer.style.top = `${destY}px`;
                    flyer.style.transform = 'translate(-50%, -50%) scale(0.35)';
                    flyer.style.opacity = '0.2';
                });
            });

            target.classList.add('ta-menu-link--badge-landed');
            window.setTimeout(() => target.classList.remove('ta-menu-link--badge-landed'), FLY_MS);

            window.setTimeout(() => {
                finish();
            }, FLY_MS + 80);
        };

        closeBtn?.addEventListener('click', runFly);
        displayTimer = window.setTimeout(runFly, DISPLAY_MS);
    });

    const runQueue = async () => {
        if (isRunning) {
            return;
        }
        isRunning = true;

        while (pendingQueue.length > 0) {
            const next = pendingQueue.shift();
            if (next) {
                await animateOne(next);
            }
        }

        updateMenuDot(false);
        isRunning = false;
    };

    const enqueueBadges = (badges) => {
        const fresh = badges.filter(
            (b) => b && b.id && !handledThisPage.has(String(b.id)),
        );
        if (0 === fresh.length) {
            return false;
        }

        updateMenuDot(true);
        pendingQueue.push(...fresh);
        runQueue();

        return true;
    };

    const fetchUnseen = async () => {
        try {
            const response = await fetch('/api/badges/unseen', {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            if (!response.ok) {
                return;
            }
            const data = await response.json();
            const badges = Array.isArray(data.badges) ? data.badges : [];
            const pending = badges.filter(
                (b) => b && b.id && !handledThisPage.has(String(b.id)),
            );
            if (0 === pending.length) {
                updateMenuDot(false);
                return;
            }
            enqueueBadges(pending);
        } catch (e) {
            /* ignore */
        }
    };

    const markAllSeen = async () => {
        try {
            const response = await fetch('/api/badges/unseen', {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            if (!response.ok) {
                return;
            }
            const data = await response.json();
            const ids = (Array.isArray(data.badges) ? data.badges : [])
                .map((b) => b.id)
                .filter(Boolean);
            if (ids.length) {
                await markSeen(ids);
                ids.forEach((id) => handledThisPage.add(String(id)));
            }
            updateMenuDot(false);
        } catch (e) {
            /* ignore */
        }
    };

    const playBadge = (badge) => {
        if (!badge || !badge.id) {
            return;
        }
        handledThisPage.delete(String(badge.id));
        enqueueBadges([badge]);
    };

    const parseJsonResponse = async (response) => {
        const text = await response.text();
        if (!text) {
            return {};
        }
        try {
            return JSON.parse(text);
        } catch (e) {
            throw new Error(`Réponse serveur invalide (${response.status}).`);
        }
    };

    const simulateUnlock = async (button) => {
        if (button) {
            button.disabled = true;
        }
        try {
            const response = await fetch('/api/badges/simulate-unlock', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
                body: '{}',
            });
            const data = await parseJsonResponse(response);
            if (!response.ok) {
                window.alert(data.error || `Simulation impossible (${response.status}).`);
                return;
            }
            if (data.badge) {
                playBadge(data.badge);
            }
        } catch (e) {
            const message = e instanceof Error && e.message
                ? e.message
                : 'Erreur réseau lors de la simulation badge.';
            window.alert(message);
        } finally {
            if (button) {
                button.disabled = false;
            }
        }
    };

    const bindSimulateButtons = () => {
        document.querySelectorAll('[data-badge-simulate-unlock]').forEach((button) => {
            if (button.dataset.badgeSimulateBound === '1') {
                return;
            }
            button.dataset.badgeSimulateBound = '1';
            button.addEventListener('click', () => simulateUnlock(button));
        });
    };

    window.LpfBadgeUnlock = {
        refresh: fetchUnseen,
        markAllSeen,
        playBadge,
        simulateUnlock,
    };

    const boot = () => {
        bindSimulateButtons();
        if (!document.querySelector('[data-badge-account-target]')) {
            return;
        }
        window.setTimeout(fetchUnseen, 600);
    };

    if (!window.__lpfBadgeUnlockListen) {
        window.__lpfBadgeUnlockListen = true;
        document.addEventListener('DOMContentLoaded', boot);
        document.addEventListener('turbo:load', boot);
    }
})();

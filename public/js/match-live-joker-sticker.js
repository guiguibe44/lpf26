(function () {
    const LAYER_ID = 'match-live-joker-sticker-layer';
    const STORAGE_PREFIX = 'lpf26-joker-sticker-dismissed';
    const SHOW_DELAY_MS = 1000;

    const storageKey = (matchId, stickerId) => `${STORAGE_PREFIX}:${matchId}:${stickerId}`;

    const isDismissed = (matchId, stickerId) => {
        try {
            return sessionStorage.getItem(storageKey(matchId, stickerId)) === '1';
        } catch (e) {
            return false;
        }
    };

    const markDismissed = (matchId, stickerId) => {
        try {
            sessionStorage.setItem(storageKey(matchId, stickerId), '1');
        } catch (e) {
            /* ignore */
        }
    };

    const hideLayer = (layer) => {
        layer.classList.remove('is-visible');
        window.setTimeout(() => {
            layer.hidden = true;
            layer.querySelector('[data-joker-sticker-dismiss]')?.setAttribute('hidden', 'hidden');
        }, 280);
    };

    const init = () => {
        const layer = document.getElementById(LAYER_ID);
        if (!layer || layer.dataset.jokerStickerReady === '1') {
            return;
        }

        const matchId = layer.dataset.matchId || '';
        const stickers = [...layer.querySelectorAll('[data-joker-sticker]')].filter((sticker) => {
            const stickerId = sticker.dataset.stickerId || '';
            if ('' === stickerId) {
                return true;
            }

            if (isDismissed(matchId, stickerId)) {
                sticker.remove();

                return false;
            }

            return true;
        });

        if (stickers.length === 0) {
            layer.remove();

            return;
        }

        const dismissAll = () => {
            stickers.forEach((sticker) => {
                const stickerId = sticker.dataset.stickerId || '';
                if ('' !== stickerId) {
                    markDismissed(matchId, stickerId);
                }
            });
            hideLayer(layer);
        };

        const dismissOne = (sticker) => {
            const stickerId = sticker.dataset.stickerId || '';
            if ('' !== stickerId) {
                markDismissed(matchId, stickerId);
            }

            sticker.remove();

            if (!layer.querySelector('[data-joker-sticker]')) {
                hideLayer(layer);
            }
        };

        layer.dataset.jokerStickerReady = '1';

        layer.querySelector('[data-joker-sticker-dismiss]')?.addEventListener('click', dismissAll);

        layer.querySelectorAll('[data-joker-sticker-close]').forEach((button) => {
            button.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();

                const sticker = button.closest('[data-joker-sticker]');
                if (sticker) {
                    dismissOne(sticker);
                }
            });
        });

        window.setTimeout(() => {
            if (!document.body.contains(layer)) {
                return;
            }

            layer.hidden = false;
            layer.querySelector('[data-joker-sticker-dismiss]')?.removeAttribute('hidden');
            requestAnimationFrame(() => {
                layer.classList.add('is-visible');
            });
        }, SHOW_DELAY_MS);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

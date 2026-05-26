/**
 * Bannière pages compétition : tirage aléatoire parmi les paires + parallax léger.
 */
(function () {
    const banners = document.querySelectorAll('[data-page-hero-banner]');
    if (!banners.length) {
        return;
    }

    banners.forEach((banner) => {
        const raw = banner.getAttribute('data-page-banner-pairs');
        if (!raw) {
            return;
        }

        try {
            const pairs = JSON.parse(raw);
            if (!Array.isArray(pairs) || pairs.length === 0) {
                return;
            }

            const pair = pairs[Math.floor(Math.random() * pairs.length)];
            const lightEl = banner.querySelector('[data-page-banner-bg="light"]');
            const darkEl = banner.querySelector('[data-page-banner-bg="dark"]');

            if (lightEl instanceof HTMLElement && pair.light) {
                lightEl.style.backgroundImage = "url('".concat(String(pair.light), "')");
            }
            if (darkEl instanceof HTMLElement && pair.dark) {
                darkEl.style.backgroundImage = "url('".concat(String(pair.dark), "')");
            }
        } catch (e) {
            /* JSON invalide : conserver l’image choisie côté serveur */
        }
    });

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    const layers = [];
    banners.forEach((banner) => {
        const layer = banner.querySelector('[data-page-hero-banner-layer]');
        if (layer instanceof HTMLElement) {
            layers.push({ banner, layer });
        }
    });

    if (!layers.length) {
        return;
    }

    let ticking = false;

    function update() {
        ticking = false;
        const scrollY = window.scrollY;

        layers.forEach(({ banner, layer }) => {
            const rect = banner.getBoundingClientRect();
            const bannerTop = rect.top + scrollY;
            const offset = (scrollY - bannerTop) * 0.4;
            layer.style.transform = 'translate3d(0, '.concat(String(offset), 'px, 0)');
        });
    }

    function requestTick() {
        if (!ticking) {
            ticking = true;
            window.requestAnimationFrame(update);
        }
    }

    window.addEventListener('scroll', requestTick, { passive: true });
    window.addEventListener('resize', requestTick);
    requestTick();
})();

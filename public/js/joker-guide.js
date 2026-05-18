(function () {
    const root = document.querySelector('[data-joker-guide]');
    if (!root) {
        return;
    }

    const STORAGE_KEY = 'lpf26-joker-guide-view';
    const viewButtons = root.querySelectorAll('[data-joker-view]');
    const panels = document.querySelectorAll('[data-joker-panel]');

    const setView = (view) => {
        viewButtons.forEach((btn) => {
            const active = btn.dataset.jokerView === view;
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-selected', active ? 'true' : 'false');
        });

        panels.forEach((panel) => {
            const show = panel.dataset.jokerPanel === view;
            panel.hidden = !show;
        });

        try {
            sessionStorage.setItem(STORAGE_KEY, view);
        } catch (e) {
            /* ignore */
        }

        if (view === 'carousel' && carouselApi && typeof carouselApi.layout === 'function') {
            requestAnimationFrame(() => carouselApi.layout());
        }
    };

    viewButtons.forEach((btn) => {
        btn.addEventListener('click', () => {
            setView(btn.dataset.jokerView || 'carousel');
        });
    });

    /** @type {{ layout?: () => void } | null} */
    let carouselApi = null;

    const carouselPanel = document.querySelector('[data-joker-panel="carousel"]');
    if (!carouselPanel) {
        return;
    }

    const viewport = carouselPanel.querySelector('[data-carousel-viewport]');
    const track = carouselPanel.querySelector('[data-carousel-track]');
    const slides = carouselPanel.querySelectorAll('[data-carousel-slide]');
    const dots = carouselPanel.querySelectorAll('[data-carousel-dot]');
    const prevBtn = carouselPanel.querySelector('[data-carousel-prev]');
    const nextBtn = carouselPanel.querySelector('[data-carousel-next]');

    if (!viewport || !track || slides.length === 0) {
        return;
    }

    let index = 0;
    let slideWidth = 0;

    const measureSlideWidth = () => {
        return Math.round(viewport.getBoundingClientRect().width) || 0;
    };

    const layout = () => {
        slideWidth = measureSlideWidth();
        if (slideWidth <= 0) {
            return;
        }

        slides.forEach((slide) => {
            slide.style.flexBasis = slideWidth + 'px';
            slide.style.width = slideWidth + 'px';
            slide.style.maxWidth = slideWidth + 'px';
        });

        track.style.width = slideWidth * slides.length + 'px';
        goTo(index, false);
    };

    const goTo = (nextIndex, animate = true) => {
        index = (nextIndex + slides.length) % slides.length;

        if (slideWidth > 0) {
            track.style.transform = 'translate3d(-' + index * slideWidth + 'px, 0, 0)';
        }

        if (!animate) {
            track.style.transition = 'none';
            requestAnimationFrame(() => {
                track.style.transition = '';
            });
        }

        slides.forEach((slide, i) => {
            slide.setAttribute('aria-hidden', i === index ? 'false' : 'true');
        });

        dots.forEach((dot, i) => {
            const active = i === index;
            dot.classList.toggle('is-active', active);
            dot.setAttribute('aria-selected', active ? 'true' : 'false');
        });

        const disabled = slides.length <= 1;
        if (prevBtn) {
            prevBtn.disabled = disabled;
        }
        if (nextBtn) {
            nextBtn.disabled = disabled;
        }
    };

    carouselApi = { layout, goTo };

    prevBtn?.addEventListener('click', () => goTo(index - 1));
    nextBtn?.addEventListener('click', () => goTo(index + 1));

    dots.forEach((dot) => {
        dot.addEventListener('click', () => {
            const i = parseInt(dot.dataset.carouselDot || '0', 10);
            if (!Number.isNaN(i)) {
                goTo(i);
            }
        });
    });

    document.addEventListener('keydown', (event) => {
        if (carouselPanel.hidden) {
            return;
        }

        if (event.key === 'ArrowLeft') {
            event.preventDefault();
            goTo(index - 1);
        } else if (event.key === 'ArrowRight') {
            event.preventDefault();
            goTo(index + 1);
        }
    });

    let touchStartX = 0;

    viewport.addEventListener(
        'touchstart',
        (event) => {
            touchStartX = event.changedTouches[0]?.clientX ?? 0;
        },
        { passive: true },
    );

    viewport.addEventListener(
        'touchend',
        (event) => {
            const touchEndX = event.changedTouches[0]?.clientX ?? 0;
            const delta = touchEndX - touchStartX;
            if (Math.abs(delta) < 40) {
                return;
            }
            if (delta < 0) {
                goTo(index + 1);
            } else {
                goTo(index - 1);
            }
        },
        { passive: true },
    );

    if (typeof ResizeObserver !== 'undefined') {
        const resizeObserver = new ResizeObserver(() => layout());
        resizeObserver.observe(viewport);
    } else {
        window.addEventListener('resize', layout);
    }

    const accordion = document.querySelector('[data-joker-accordion]');
    if (accordion) {
        accordion.querySelectorAll('.joker-guide-accordion-item').forEach((item) => {
            item.addEventListener('toggle', () => {
                if (!item.open) {
                    return;
                }
                accordion.querySelectorAll('.joker-guide-accordion-item').forEach((other) => {
                    if (other !== item) {
                        other.open = false;
                    }
                });
            });
        });
    }

    layout();

    try {
        const saved = sessionStorage.getItem(STORAGE_KEY);
        if (saved === 'accordion' || saved === 'carousel') {
            setView(saved);
        }
    } catch (e) {
        /* ignore */
    }
})();

(function () {
    const DIALOG_ID = 'site-intro-dialog';
    const STORAGE_KEY = 'lpf26-site-intro-dismissed';
    const AUTO_OPEN_DELAY_MS = 600;

    const getDialog = () => document.getElementById(DIALOG_ID);

    const isDismissed = () => {
        try {
            return localStorage.getItem(STORAGE_KEY) === '1';
        } catch (e) {
            return false;
        }
    };

    const markDismissed = () => {
        try {
            localStorage.setItem(STORAGE_KEY, '1');
        } catch (e) {
            /* ignore */
        }
    };

    const openDialog = () => {
        const dialog = getDialog();
        if (!dialog || typeof dialog.showModal !== 'function') {
            return;
        }
        if (!dialog.open) {
            dialog.showModal();
        }
        requestAnimationFrame(() => {
            window.dispatchEvent(new CustomEvent('lpf:site-intro-open'));
        });
    };

    const closeDialog = () => {
        const dialog = getDialog();
        if (!dialog?.open) {
            return;
        }
        markDismissed();
        dialog.close();
    };

    /** @type {{ layout?: () => void } | null} */
    let carouselApi = null;

    const initCarousel = () => {
        const root = getDialog()?.querySelector('[data-site-intro-carousel]');
        if (!root || root.dataset.siteIntroReady === '1') {
            return carouselApi;
        }

        const viewport = root.querySelector('[data-site-intro-viewport]');
        const track = root.querySelector('[data-site-intro-track]');
        const slides = root.querySelectorAll('[data-site-intro-slide]');
        const prevBtn = root.querySelector('[data-site-intro-prev]');
        const nextBtn = root.querySelector('[data-site-intro-next]');

        if (!viewport || !track || slides.length === 0) {
            return null;
        }

        let index = 0;
        let slideWidth = 0;

        const getMaxViewportHeight = () => Math.max(0, Math.round(root.getBoundingClientRect().height));

        const syncActiveSlideHeight = () => {
            const slide = slides[index];
            if (!slide) {
                return;
            }

            viewport.style.height = 'auto';
            viewport.style.overflowY = 'hidden';

            const contentHeight = Math.ceil(slide.getBoundingClientRect().height);
            const maxHeight = getMaxViewportHeight();

            if (contentHeight <= 0) {
                viewport.style.height = '';
                viewport.style.maxHeight = maxHeight > 0 ? maxHeight + 'px' : '';
                return;
            }

            if (maxHeight > 0 && contentHeight > maxHeight) {
                viewport.style.maxHeight = maxHeight + 'px';
                viewport.style.height = maxHeight + 'px';
                viewport.style.overflowY = 'auto';
                return;
            }

            viewport.style.maxHeight = maxHeight > 0 ? maxHeight + 'px' : '';
            viewport.style.height = contentHeight + 'px';
            viewport.style.overflowY = 'hidden';
        };

        const updateNav = () => {
            const single = slides.length <= 1;
            if (prevBtn) {
                prevBtn.disabled = single || index <= 0;
            }
            if (nextBtn) {
                nextBtn.disabled = single || index >= slides.length - 1;
            }
        };

        const goTo = (nextIndex) => {
            index = Math.max(0, Math.min(nextIndex, slides.length - 1));

            if (slideWidth > 0) {
                track.style.transform = 'translate3d(-' + index * slideWidth + 'px, 0, 0)';
            }

            slides.forEach((slide, i) => {
                slide.setAttribute('aria-hidden', i === index ? 'false' : 'true');
            });

            viewport.scrollTop = 0;
            updateNav();
            requestAnimationFrame(() => {
                syncActiveSlideHeight();
            });
        };

        const layout = () => {
            slideWidth = Math.round(viewport.getBoundingClientRect().width) || 0;
            if (slideWidth <= 0) {
                return;
            }

            slides.forEach((slide) => {
                slide.style.flexBasis = slideWidth + 'px';
                slide.style.width = slideWidth + 'px';
                slide.style.maxWidth = slideWidth + 'px';
            });

            track.style.width = slideWidth * slides.length + 'px';
            track.style.transition = 'none';
            goTo(index);
            requestAnimationFrame(() => {
                track.style.transition = '';
                syncActiveSlideHeight();
            });
        };

        slides.forEach((slide) => {
            slide.querySelectorAll('img').forEach((img) => {
                if (!img.complete) {
                    img.addEventListener('load', () => {
                        if (slide === slides[index]) {
                            syncActiveSlideHeight();
                        }
                    });
                }
            });
        });

        carouselApi = { layout };

        prevBtn?.addEventListener('click', () => goTo(index - 1));
        nextBtn?.addEventListener('click', () => goTo(index + 1));

        document.addEventListener('keydown', (event) => {
            const dialog = getDialog();
            if (!dialog?.open) {
                return;
            }

            if (event.key === 'ArrowLeft' && index > 0) {
                event.preventDefault();
                goTo(index - 1);
            } else if (event.key === 'ArrowRight' && index < slides.length - 1) {
                event.preventDefault();
                goTo(index + 1);
            } else if (event.key === 'Escape') {
                closeDialog();
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
                goTo(delta < 0 ? index + 1 : index - 1);
            },
            { passive: true },
        );

        if (typeof ResizeObserver !== 'undefined') {
            const layoutObserver = new ResizeObserver(() => layout());
            layoutObserver.observe(root);

            const slideObserver = new ResizeObserver(() => syncActiveSlideHeight());
            slides.forEach((slide) => slideObserver.observe(slide));
        } else {
            window.addEventListener('resize', () => {
                layout();
                syncActiveSlideHeight();
            });
        }

        window.addEventListener('lpf:site-intro-open', () => layout());

        root.dataset.siteIntroReady = '1';
        layout();

        return carouselApi;
    };

    document.addEventListener('click', (event) => {
        const openBtn = event.target.closest('[data-site-intro-open]');
        if (openBtn) {
            event.preventDefault();
            initCarousel();
            openDialog();
            return;
        }

        const closeBtn = event.target.closest('[data-site-intro-close]');
        if (closeBtn) {
            event.preventDefault();
            closeDialog();
        }
    });

    const boot = () => {
        if (!getDialog()) {
            return;
        }

        initCarousel();

        if (!isDismissed()) {
            window.setTimeout(() => {
                if (!isDismissed() && getDialog() && !getDialog()?.open) {
                    openDialog();
                }
            }, AUTO_OPEN_DELAY_MS);
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();

(function () {
    const AUTO_DISMISS_MS = 5000;
    const AUTO_DISMISS_PRONOSTIC_MS = 3500;
    const LEAVE_MS = 280;

    const VARIANT_CLASSES = {
        success: 'lpf-alert--success',
        danger: 'lpf-alert--danger',
        warning: 'lpf-alert--warning',
        info: 'lpf-alert--info',
    };

    const getStack = () => {
        let stack = document.getElementById('lpf-alerts');
        if (!stack) {
            stack = document.createElement('div');
            stack.id = 'lpf-alerts';
            stack.className = 'lpf-alerts';
            stack.setAttribute('aria-live', 'polite');
            stack.setAttribute('aria-relevant', 'additions removals');
            document.body.prepend(stack);
        }

        return stack;
    };

    const inferVariant = (el) => {
        for (const [name, className] of Object.entries(VARIANT_CLASSES)) {
            if (el.classList.contains(className) || el.classList.contains('flash-' + name)) {
                return name;
            }
        }
        if (el.classList.contains('alert-warning')) {
            return 'warning';
        }

        return 'info';
    };

    const normalizeAlert = (el) => {
        if (!el || el.closest('#lpf-alerts')) {
            return el;
        }

        const variant = inferVariant(el);
        el.classList.add('lpf-alert', VARIANT_CLASSES[variant]);
        if (!el.classList.contains('flash')) {
            el.classList.add('flash', 'flash-' + variant);
        }

        if (!el.hasAttribute('data-lpf-alert-dismiss')) {
            const isFormError = el.querySelector('.ta-form-errors, .ta-form-errors--alert') !== null;
            const isInlineWarning = el.classList.contains('alert-warning') || el.classList.contains('alert');
            el.setAttribute(
                'data-lpf-alert-dismiss',
                isFormError || isInlineWarning ? 'persist' : 'auto',
            );
        }

        getStack().appendChild(el);

        return el;
    };

    const collectPageAlerts = () => {
        const selector = [
            '.lpf-alert',
            '.flash',
            '.alert.alert-warning',
            '.alert[role="status"]',
            '.alert[role="alert"]',
        ].join(', ');

        document.querySelectorAll(selector).forEach((el) => {
            if (el.closest('#lpf-alerts') || el.closest('.app-cotisation-banner-wrap')) {
                return;
            }
            normalizeAlert(el);
        });
    };

    const updatePersistOffset = () => {
        const stack = getStack();
        const persistAlerts = stack.querySelectorAll(
            '[data-lpf-alert-dismiss="persist"]:not(.lpf-alert--leaving)',
        );
        const hasPersist = persistAlerts.length > 0;
        let height = 0;

        if (hasPersist) {
            persistAlerts.forEach((el) => {
                height += el.offsetHeight;
            });
        }

        document.body.classList.toggle('lpf-alerts-visible--persist', hasPersist);
        document.documentElement.style.setProperty('--lpf-alerts-offset', hasPersist ? height + 'px' : '0px');
    };

    const dismissAlert = (el, delayMs) => {
        window.setTimeout(() => {
            el.classList.add('lpf-alert--leaving');
            window.setTimeout(() => {
                el.remove();
                updatePersistOffset();
            }, LEAVE_MS);
        }, delayMs);
    };

    const scheduleAutoDismiss = (el, durationMs) => {
        const mode = el.getAttribute('data-lpf-alert-dismiss');

        if ('persist' === mode) {
            updatePersistOffset();

            return;
        }

        if ('transient' === mode) {
            return;
        }

        dismissAlert(el, durationMs ?? AUTO_DISMISS_MS);
    };

    const show = (message, variant, options) => {
        const opts = options || {};
        const stack = getStack();
        const el = document.createElement('div');
        const v = VARIANT_CLASSES[variant] ? variant : 'info';

        el.className = 'lpf-alert flash flash-' + v + ' ' + VARIANT_CLASSES[v];
        el.setAttribute('role', 'status');
        const dismiss = opts.dismiss === 'persist' || opts.dismiss === 'transient' ? opts.dismiss : 'auto';
        el.setAttribute('data-lpf-alert-dismiss', dismiss);

        const span = document.createElement('span');
        span.className = 'lpf-alert__text';
        span.textContent = message;
        el.appendChild(span);

        stack.appendChild(el);
        scheduleAutoDismiss(el, opts.durationMs);

        return el;
    };

    const init = () => {
        collectPageAlerts();
        getStack().querySelectorAll('.lpf-alert, .flash').forEach((el) => {
            scheduleAutoDismiss(el, AUTO_DISMISS_MS);
        });
        updatePersistOffset();
    };

    window.lpfAlerts = {
        show,
        dismiss: dismissAlert,
        AUTO_DISMISS_MS,
        AUTO_DISMISS_PRONOSTIC_MS,
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

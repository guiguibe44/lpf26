/* stimulusFetch: 'eager' */
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        subscribeUrl: String,
        unsubscribeUrl: String,
        csrfToken: String,
        swUrl: { type: String, default: '/sw.js' },
        swScope: { type: String, default: '/' },
    };

    static targets = ['status', 'enableBtn', 'disableBtn', 'testBtn'];

    connect() {
        this.boundEnable = (event) => this.enable(event);
        this.boundDisable = (event) => this.disable(event);

        if (this.hasEnableBtnTarget) {
            this.enableBtnTarget.addEventListener('click', this.boundEnable);
        }
        if (this.hasDisableBtnTarget) {
            this.disableBtnTarget.addEventListener('click', this.boundDisable);
        }
        this.boundTest = (event) => this.testLocal(event);
        if (this.hasTestBtnTarget) {
            this.testBtnTarget.addEventListener('click', this.boundTest);
        }

        this.isIos = /iPad|iPhone|iPod/.test(navigator.userAgent)
            || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
        this.isStandalone = window.matchMedia('(display-mode: standalone)').matches
            || window.navigator.standalone === true;

        const hasServiceWorker = 'serviceWorker' in navigator;
        const hasNotification = 'Notification' in window;
        const hasPushApi = 'PushManager' in window
            || (typeof ServiceWorkerRegistration !== 'undefined'
                && 'pushManager' in ServiceWorkerRegistration.prototype);

        this.supported = hasServiceWorker && hasNotification && hasPushApi;

        if (!this.supported) {
            this.setStatus(
                'Ce navigateur ne prend pas en charge les notifications push web.',
                'warning',
            );
            this.hideActions();
            return;
        }

        if (this.isIos && !this.isStandalone) {
            this.setStatus(
                'Sur iPhone : ajoutez d’abord LPF’26 à l’écran d’accueil, ouvrez l’icône, puis activez les notifications.',
                'warning',
            );
        }

        this.refreshUi();
    }

    disconnect() {
        if (this.hasEnableBtnTarget) {
            this.enableBtnTarget.removeEventListener('click', this.boundEnable);
        }
        if (this.hasDisableBtnTarget) {
            this.disableBtnTarget.removeEventListener('click', this.boundDisable);
        }
        if (this.hasTestBtnTarget) {
            this.testBtnTarget.removeEventListener('click', this.boundTest);
        }
    }

    swScriptUrl() {
        const url = this.swUrlValue || '/sw.js';
        return url.split('?')[0];
    }

    async getSwRegistration() {
        const scope = this.swScopeValue || '/';
        return (
            (await navigator.serviceWorker.getRegistration(scope))
            || (await navigator.serviceWorker.getRegistration())
        );
    }

    async enable(event) {
        event.preventDefault();
        event.stopPropagation();

        if (!this.supported) {
            this.setStatus('Notifications push non disponibles sur ce navigateur.', 'warning');
            return;
        }

        if (this.isIos && !this.isStandalone) {
            this.setStatus(
                'Sur iPhone, ouvrez le site depuis l’icône installée (écran d’accueil), puis réessayez.',
                'warning',
            );
            return;
        }

        this.setStatus('Activation en cours…', 'muted');
        this.lockStatus();

        try {
            let permission = Notification.permission;
            if (permission === 'default') {
                permission = await Notification.requestPermission();
            }

            if (permission !== 'granted') {
                this.setStatus(
                    'Autorisation refusée. Autorisez les notifications pour ce site dans le navigateur.',
                    'warning',
                );
                this.unlockStatus();
                return;
            }

            const keyResponse = await fetch('/api/push/vapid-public-key', { credentials: 'same-origin' });
            if (!keyResponse.ok) {
                throw new Error('Impossible de contacter le serveur (' + keyResponse.status + ').');
            }

            const keyData = await keyResponse.json();
            if (!keyData.configured || !keyData.publicKey) {
                this.setStatus('Clés VAPID manquantes côté serveur (.env).', 'warning');
                this.unlockStatus();
                return;
            }

            const registration = await navigator.serviceWorker.register(this.swScriptUrl(), { scope: '/' });
            await navigator.serviceWorker.ready;

            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: this.urlBase64ToUint8Array(keyData.publicKey),
            });

            const payload = subscription.toJSON();
            const encodings = registration.pushManager.supportedContentEncodings;
            payload.contentEncoding = encodings && encodings.length > 0 ? encodings[0] : 'aes128gcm';
            payload._csrf_token = this.csrfTokenValue;

            const response = await fetch(this.subscribeUrlValue, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.csrfTokenValue,
                },
                body: JSON.stringify(payload),
            });

            if (!response.ok) {
                const err = await response.json().catch(() => ({}));
                throw new Error(err.error || 'Enregistrement impossible (' + response.status + ').');
            }

            this.setStatus('Notifications activées sur cet appareil.', 'success');
            this.unlockStatus();
            await this.refreshUi();
        } catch (error) {
            console.error('[push-notifications]', error);
            this.setStatus(error.message || 'Erreur lors de l’activation.', 'danger');
            this.unlockStatus();
        }
    }

    async disable(event) {
        event.preventDefault();
        event.stopPropagation();

        if (!this.supported) {
            return;
        }

        this.setStatus('Désactivation…', 'muted');

        try {
            const registration = await this.getSwRegistration();
            const subscription = registration ? await registration.pushManager.getSubscription() : null;

            const body = { _csrf_token: this.csrfTokenValue };
            if (subscription) {
                body.endpoint = subscription.endpoint;
            }

            await fetch(this.unsubscribeUrlValue, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.csrfTokenValue,
                },
                body: JSON.stringify(body),
            });

            if (subscription) {
                await subscription.unsubscribe();
            }

            this.setStatus('Notifications désactivées sur cet appareil.', 'muted');
            this.unlockStatus();
            await this.refreshUi();
        } catch (error) {
            console.error('[push-notifications]', error);
            this.setStatus(error.message || 'Erreur lors de la désactivation.', 'danger');
            this.unlockStatus();
        }
    }

    async refreshUi() {
        if (!this.supported) {
            return;
        }

        try {
            const registration = await this.getSwRegistration();
            const subscribed = registration && (await registration.pushManager.getSubscription());

            if (this.hasEnableBtnTarget) {
                this.enableBtnTarget.hidden = !!subscribed;
                this.enableBtnTarget.disabled = false;
            }
            if (this.hasDisableBtnTarget) {
                this.disableBtnTarget.hidden = !subscribed;
            }
            if (this.hasTestBtnTarget) {
                this.testBtnTarget.hidden = !subscribed;
            }

            if (subscribed && this.hasStatusTarget && !this.statusTarget.dataset.locked) {
                this.setStatus('Vous recevrez les alertes LPF’26 sur cet appareil.', 'success');
            }
        } catch (error) {
            console.error('[push-notifications] refreshUi', error);
        }
    }

    setStatus(message, tone) {
        if (!this.hasStatusTarget) {
            console.warn('[push-notifications]', message);
            return;
        }
        this.statusTarget.textContent = message;
        this.statusTarget.className = 'push-notifications-card__status push-notifications-card__status--' + tone;
    }

    lockStatus() {
        if (this.hasStatusTarget) {
            this.statusTarget.dataset.locked = '1';
        }
    }

    unlockStatus() {
        if (this.hasStatusTarget) {
            delete this.statusTarget.dataset.locked;
        }
    }

    hideActions() {
        if (this.hasEnableBtnTarget) {
            this.enableBtnTarget.hidden = true;
        }
        if (this.hasDisableBtnTarget) {
            this.disableBtnTarget.hidden = true;
        }
        if (this.hasTestBtnTarget) {
            this.testBtnTarget.hidden = true;
        }
    }

    async testLocal(event) {
        event.preventDefault();
        event.stopPropagation();

        if (Notification.permission !== 'granted') {
            this.setStatus('Autorisez d’abord les notifications, puis réessayez.', 'warning');
            return;
        }

        try {
            await navigator.serviceWorker.register(this.swScriptUrl(), { scope: '/' });
            const registration = await navigator.serviceWorker.ready;
            await registration.showNotification('LPF\'26 — test', {
                body: 'Si vous voyez cette alerte, l’affichage local fonctionne.',
                icon: '/images/lpf26-logo-gta.png',
                tag: 'lpf26-push-test',
            });
            this.setStatus('Notification de test affichée.', 'success');
        } catch (error) {
            console.error('[push-notifications] testLocal', error);
            this.setStatus('Test local impossible : ' + (error.message || 'erreur inconnue'), 'danger');
        }
    }

    urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
        const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);
        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }
}

/* stimulusFetch: 'eager' */
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['sidebar', 'backdrop'];

    connect() {
        this.boundClose = () => this.close();
        document.addEventListener('keydown', this.boundKeydown);
        document.addEventListener('turbo:load', this.boundClose);
    }

    disconnect() {
        document.removeEventListener('keydown', this.boundKeydown);
        document.removeEventListener('turbo:load', this.boundClose);
    }

    boundKeydown = (event) => {
        if (event.key === 'Escape') {
            this.close();
        }
    };

    toggle(event) {
        event.preventDefault();
        if (document.body.classList.contains('ta-nav-open')) {
            this.close();
        } else {
            this.open();
        }
    }

    getBurgerButton() {
        return document.querySelector('.lpf-burger');
    }

    open() {
        document.body.classList.add('ta-nav-open');
        const burger = this.getBurgerButton();
        if (burger) {
            burger.classList.add('open');
            burger.setAttribute('aria-expanded', 'true');
            burger.setAttribute('aria-label', 'Fermer le menu');
        }
    }

    close() {
        document.body.classList.remove('ta-nav-open');
        const burger = this.getBurgerButton();
        if (burger) {
            burger.classList.remove('open');
            burger.setAttribute('aria-expanded', 'false');
            burger.setAttribute('aria-label', 'Ouvrir le menu');
        }
    }

    closeOnNavigate(event) {
        if (event.target.closest('a[href]')) {
            this.close();
        }
    }
}

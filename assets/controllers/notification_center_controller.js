/* stimulusFetch: 'eager' */
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['toggle', 'dropdown', 'list', 'badge'];

    static values = {
        listUrl: String,
        countUrl: String,
        readUrl: String,
        initialCount: { type: Number, default: 0 },
    };

    connect() {
        this.open = false;
        this.boundCloseOutside = (event) => {
            if (!this.element.contains(event.target)) {
                this.closeDropdown();
            }
        };
        this.updateBadge(this.initialCountValue);
        this.pollTimer = setInterval(() => this.refreshCount(), 60000);
    }

    disconnect() {
        document.removeEventListener('click', this.boundCloseOutside);
        clearInterval(this.pollTimer);
    }

    toggle(event) {
        event.preventDefault();
        event.stopPropagation();
        if (this.open) {
            this.closeDropdown();
        } else {
            this.openDropdown();
        }
    }

    async openDropdown() {
        this.open = true;
        this.dropdownTarget.removeAttribute('hidden');
        this.toggleTarget.setAttribute('aria-expanded', 'true');
        document.addEventListener('click', this.boundCloseOutside);
        await this.loadList();
    }

    closeDropdown() {
        this.open = false;
        this.dropdownTarget.setAttribute('hidden', 'hidden');
        this.toggleTarget.setAttribute('aria-expanded', 'false');
        document.removeEventListener('click', this.boundCloseOutside);
    }

    async loadList() {
        if (!this.listUrlValue) {
            return;
        }
        try {
            const response = await fetch(this.listUrlValue, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            if (!response.ok) {
                return;
            }
            const data = await response.json();
            this.renderList(data.notifications || []);
        } catch (error) {
            console.error('[notification-center]', error);
        }
    }

    renderList(notifications) {
        this.listTarget.innerHTML = '';
        if (notifications.length === 0) {
            const li = document.createElement('li');
            li.className = 'notification-dropdown__empty ta-card-text';
            li.textContent = 'Aucune notification.';
            this.listTarget.appendChild(li);
            return;
        }

        notifications.forEach((item) => {
            const li = document.createElement('li');
            li.className = 'notification-dropdown__item' + (item.read ? '' : ' notification-dropdown__item--unread');
            const link = document.createElement('a');
            link.href = item.url || '#';
            link.className = 'notification-dropdown__link';
            link.innerHTML = `<strong>${this.escape(item.title)}</strong><span>${this.escape(item.body)}</span>`;
            link.addEventListener('click', () => {
                if (!item.read && item.id) {
                    this.markIdsRead([item.id]);
                }
            });
            li.appendChild(link);
            this.listTarget.appendChild(li);
        });
    }

    async markAllRead(event) {
        event.preventDefault();
        await this.postRead({ all: true });
        await this.loadList();
    }

    async markIdsRead(ids) {
        await this.postRead({ ids });
    }

    async postRead(payload) {
        if (!this.readUrlValue) {
            return;
        }
        try {
            const response = await fetch(this.readUrlValue, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify(payload),
            });
            if (!response.ok) {
                return;
            }
            const data = await response.json();
            this.updateBadge(data.count ?? 0);
        } catch (error) {
            console.error('[notification-center]', error);
        }
    }

    async refreshCount() {
        if (!this.countUrlValue) {
            return;
        }
        try {
            const response = await fetch(this.countUrlValue, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            if (!response.ok) {
                return;
            }
            const data = await response.json();
            this.updateBadge(data.count ?? 0);
        } catch (error) {
            /* ignore polling errors */
        }
    }

    updateBadge(count) {
        if (!this.hasBadgeTarget) {
            return;
        }
        const value = Number(count) || 0;
        if (value < 1) {
            this.badgeTarget.setAttribute('hidden', 'hidden');
            this.badgeTarget.textContent = '0';
        } else {
            this.badgeTarget.removeAttribute('hidden');
            this.badgeTarget.textContent = value > 99 ? '99+' : String(value);
        }
        const label = value > 0
            ? `Notifications — ${value} non lue${value > 1 ? 's' : ''}`
            : 'Notifications';
        this.toggleTarget.setAttribute('aria-label', label);
    }

    escape(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }
}

/* Service worker LPF'26 — réception des notifications push */
const DEFAULT_TITLE = "LPF'26";
const DEFAULT_ICON = '/images/lpf26-logo-color.png';

self.addEventListener('install', (event) => {
    event.waitUntil(self.skipWaiting());
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('push', (event) => {
    let data = { title: DEFAULT_TITLE, body: '', url: '/accueil' };

    try {
        if (event.data) {
            const parsed = event.data.json();
            data = { ...data, ...parsed };
        }
    } catch (e) {
        try {
            data.body = event.data ? event.data.text() : data.body;
        } catch (e2) {
            data.body = 'Nouvelle alerte';
        }
    }

    const title = data.title || DEFAULT_TITLE;
    const body = (data.body && String(data.body).trim()) || 'Nouvelle alerte LPF\'26';
    const url = data.url || '/accueil';

    const options = {
        body,
        icon: DEFAULT_ICON,
        badge: DEFAULT_ICON,
        data: { url },
        tag: 'lpf26-push',
        renotify: true,
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = event.notification.data?.url || '/accueil';
    const absolute = new URL(url, self.location.origin).href;

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            for (const client of clientList) {
                if (client.url.startsWith(self.location.origin) && 'focus' in client) {
                    if ('navigate' in client) {
                        return client.navigate(absolute).then(() => client.focus());
                    }
                    return client.focus();
                }
            }
            if (clients.openWindow) {
                return clients.openWindow(absolute);
            }
        }),
    );
});

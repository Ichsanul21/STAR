import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const meta = (name) => document.querySelector(`meta[name="${name}"]`)?.content?.trim();

const userId = meta('user-id');
const key = meta('reverb-key');
const host = meta('reverb-host');

if (userId && key && host) {
    const scheme = meta('reverb-scheme') || 'https';
    const port = Number(meta('reverb-port') || (scheme === 'https' ? 443 : 80));

    const echo = new Echo({
        broadcaster: 'reverb',
        key,
        wsHost: host,
        wsPort: port,
        wssPort: port,
        forceTLS: scheme === 'https',
        enabledTransports: ['ws', 'wss'],
        authEndpoint: '/broadcasting/auth',
        auth: {
            headers: {
                'X-CSRF-TOKEN': meta('csrf-token'),
                'X-Requested-With': 'XMLHttpRequest',
            },
        },
    });

    window.Echo = echo;

    const setValue = (selector, value) => {
        document.querySelectorAll(selector).forEach((element) => {
            element.textContent = value;
        });
    };

    const setBadge = (selector, value) => {
        document.querySelectorAll(selector).forEach((element) => {
            element.textContent = value;
            element.classList.toggle('hidden', value <= 0);
        });
    };

    const applyCounts = (counts) => {
        if (!counts) return;

        setValue('[data-notif-total]', counts.total);
        setValue('[data-notif-count="task"]', counts.task);
        setValue('[data-notif-count="finance"]', counts.finance);
        setValue('[data-notif-count="report"]', counts.report);
        setBadge('[data-notif-nav-badge]', counts.unread);
        setBadge('[data-notif-mini]', counts.total);
        setBadge('[data-notif-topbar]', counts.total);
    };

    const stack = () => {
        let element = document.querySelector('.realtime-toast-stack');

        if (!element) {
            element = document.createElement('div');
            element.className = 'realtime-toast-stack';
            document.body.appendChild(element);
        }

        return element;
    };

    const showToast = (payload) => {
        const link = document.createElement('a');
        link.className = 'realtime-toast';
        link.href = payload.url || '/notifications';

        const title = document.createElement('span');
        title.className = 'realtime-toast-title';
        title.textContent = payload.title || 'Notifikasi baru';

        link.appendChild(title);

        if (payload.body) {
            const body = document.createElement('span');
            body.className = 'realtime-toast-body';
            body.textContent = payload.body;
            link.appendChild(body);
        }

        const close = document.createElement('button');
        close.type = 'button';
        close.className = 'realtime-toast-close';
        close.setAttribute('aria-label', 'Tutup');
        close.textContent = '\u00d7';
        close.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            link.remove();
        });

        link.appendChild(close);
        stack().appendChild(link);

        setTimeout(() => link.remove(), 8000);
    };

    echo.private(`App.Models.User.${userId}`).listen('.notification.created', (payload) => {
        applyCounts(payload.counts);
        showToast(payload);
    });
}

document.addEventListener('DOMContentLoaded', () => {
    const badge = document.querySelector('[data-notification-count]');
    if (!badge) return;
    const bell = document.querySelector('.notification-bell');
    const toast = document.querySelector('.notification-toast');
    let latest = null, stopped = false, timer;
    async function poll() {
        if (stopped || document.hidden) return;
        try {
            const response = await fetch('notification_count.php', {headers: {'X-Requested-With': 'XMLHttpRequest'}, cache: 'no-store'});
            if (response.status === 401 || response.status === 403) { stopped = true; clearInterval(timer); return; }
            if (!response.ok) return;
            const data = await response.json();
            badge.textContent = data.unread > 99 ? '99+' : String(data.unread);
            badge.hidden = data.unread === 0;
            bell.setAttribute('aria-label', 'Notifications' + (data.unread ? ', ' + data.unread + ' unread' : ''));
            if (latest !== null && data.latest > latest && data.unread > 0 && toast) {
                toast.replaceChildren(document.createTextNode('You have new activity. '));
                const link = document.createElement('a'); link.href = 'notifications.php'; link.textContent = 'View notifications';
                toast.appendChild(link); toast.hidden = false;
                setTimeout(() => { toast.hidden = true; }, 7000);
            }
            latest = data.latest;
        } catch (_) { /* Keep server-rendered notifications usable while offline. */ }
    }
    poll(); timer = setInterval(poll, 20000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });
});

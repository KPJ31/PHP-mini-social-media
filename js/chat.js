document.addEventListener('DOMContentLoaded', () => {
    const box = document.getElementById('chat-box');
    const form = document.getElementById('chatForm');
    if (!box || !form || box.dataset.history === 'true') return;
    const status = document.createElement('p');
    status.className = 'chat-status text-muted small px-3 mb-0';
    status.setAttribute('role', 'status');
    form.before(status);
    let inFlight;
    async function loadMessages() {
        if (inFlight) return inFlight;
        inFlight = (async () => {
            try {
                const response = await fetch('load_chat_messages.php?receiver_id=' + encodeURIComponent(form.elements.receiver_id.value),
                    {headers: {'X-Requested-With': 'XMLHttpRequest'}});
                if (!response.ok || response.redirected) throw new Error('Unable to refresh messages. Check your connection or log in again.');
                const html = await response.text();
                const atBottom = box.scrollHeight - box.scrollTop - box.clientHeight < 40;
                if (box.innerHTML !== html && !box.contains(document.activeElement)) {
                    box.innerHTML = html;
                    if (atBottom) box.scrollTop = box.scrollHeight;
                }
                status.textContent = '';
            } catch (error) {
                status.textContent = error.message;
            }
        })();
        try { await inFlight; } finally { inFlight = null; }
    }
    let timer;
    const start = () => {
        clearInterval(timer);
        if (document.hidden) return;
        loadMessages();
        timer = setInterval(loadMessages, 3000);
    };
    box.scrollTop = box.scrollHeight;
    start();
    document.addEventListener('visibilitychange', start);
    window.addEventListener('pagehide', () => clearInterval(timer));
    window.addEventListener('pageshow', start);
    form.addEventListener('submit', async event => {
        event.preventDefault();
        const button = form.querySelector('button[type="submit"]');
        button.disabled = true;
        try {
            const response = await fetch('send_message.php', {
                method: 'POST', body: new FormData(form),
                headers: {'X-Requested-With': 'XMLHttpRequest'}
            });
            if (!response.ok || response.redirected) throw new Error('Message was not sent. Reload the page and try again.');
            form.elements.message.value = '';
            if (inFlight) await inFlight;
            await loadMessages();
            box.scrollTop = box.scrollHeight;
        } catch (error) { status.textContent = error.message; }
        finally { button.disabled = false; }
    });
});

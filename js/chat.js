document.addEventListener('DOMContentLoaded', () => {
    const box = document.getElementById('chat-box');
    const form = document.getElementById('chatForm');
    if (!box || !form) return;
    let loading = false;
    async function loadMessages() {
        if (loading) return;
        loading = true;
        try {
            const response = await fetch('load_chat_messages.php?receiver_id=' + encodeURIComponent(form.elements.receiver_id.value));
            if (!response.ok || response.redirected) throw new Error('Unable to load messages. Please reload the page.');
            const atBottom = box.scrollHeight - box.scrollTop - box.clientHeight < 40;
            box.innerHTML = await response.text();
            if (atBottom) box.scrollTop = box.scrollHeight;
        } catch (error) {
            console.error(error);
        } finally {
            loading = false;
        }
    }
    box.scrollTop = box.scrollHeight;
    loadMessages();
    const timer = setInterval(loadMessages, 3000);
    window.addEventListener('pagehide', () => clearInterval(timer), {once: true});
    form.addEventListener('submit', async event => {
        event.preventDefault();
        const button = form.querySelector('button[type="submit"]');
        button.disabled = true;
        try {
            const response = await fetch('send_message.php', {
                method: 'POST', body: new FormData(form),
                headers: {'X-Requested-With': 'XMLHttpRequest'}
            });
            if (!response.ok || response.redirected) throw new Error('Message was not sent. Please reload and try again.');
            form.elements.message.value = '';
            await loadMessages();
            box.scrollTop = box.scrollHeight;
        } catch (error) {
            alert(error.message);
        } finally {
            button.disabled = false;
        }
    });
});

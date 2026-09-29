document.addEventListener('DOMContentLoaded', () => {
    async function loadComments(postId) {
        const target = document.getElementById('comments-' + postId);
        if (!target) return;
        const response = await fetch('get_comments.php?post_id=' + encodeURIComponent(postId));
        if (!response.ok || response.redirected) throw new Error('Unable to load comments.');
        target.innerHTML = await response.text();
    }
    document.querySelectorAll('.comment-form').forEach(form => {
        form.addEventListener('submit', async event => {
            event.preventDefault();
            try {
                const data = new FormData(form);
                data.set('csrf_token', document.querySelector('meta[name="csrf-token"]').content);
                const response = await fetch('comment_post.php', {
                    method: 'POST', body: data,
                    headers: {'X-Requested-With': 'XMLHttpRequest'}
                });
                if (!response.ok || response.redirected || (await response.text()).trim() !== 'success') throw new Error('Unable to save comment.');
                form.elements.comment.value = '';
                await loadComments(data.get('post_id'));
            } catch (error) { alert(error.message); }
        });
    });
    document.querySelectorAll('.comment-section').forEach(section => {
        loadComments(section.dataset.postId).catch(console.error);
    });
});

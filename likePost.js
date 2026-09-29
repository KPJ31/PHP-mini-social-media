document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.like-button').forEach(button => {
        button.addEventListener('click', async () => {
            button.disabled = true;
            try {
                const response = await fetch('like_post.php', {
                    method: 'POST',
                    body: new URLSearchParams({
                        post_id: button.dataset.postId,
                        csrf_token: document.querySelector('meta[name="csrf-token"]').content
                    })
                });
                const data = await response.json();
                if (!response.ok || !data.success) throw new Error(data.message || 'Unable to update like.');
                document.getElementById('like-count-' + button.dataset.postId).textContent = data.likes + ' likes';
            } catch (error) {
                alert(error.message);
            } finally {
                button.disabled = false;
            }
        });
    });
});

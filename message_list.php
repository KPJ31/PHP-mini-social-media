<?php
// Included after $conversation, $viewerId and $friendId are set.
if ($conversation['more']): ?>
<p class="text-center"><a class="btn btn-sm btn-outline-primary" href="chat.php?user_id=<?= $friendId ?>&before=<?= $conversation['rows'][0]['id'] ?>">Older messages</a></p>
<?php endif;
if (!$conversation['rows']): ?><p class="text-muted">No messages yet. Say hello to start the conversation.</p><?php endif;
foreach ($conversation['rows'] as $message):
    $own = $message['sender_id'] == $viewerId;
?>
<div class="d-flex mb-3 <?= $own ? 'justify-content-end' : 'justify-content-start' ?>">
    <div class="message-bubble <?= $own ? 'own' : '' ?>">
        <span><?= nl2br(htmlspecialchars($message['message'])) ?></span>
        <time datetime="<?= date('c', strtotime($message['sent_at'])) ?>"><?= date('M j, H:i', strtotime($message['sent_at'])) ?></time>
        <form action="delete_message.php" method="post" data-confirm="Delete this message for you?">
            <?= csrfField() ?><input type="hidden" name="id" value="<?= $message['id'] ?>">
            <button type="submit" class="message-delete" aria-label="Delete message for you">Delete for me</button>
        </form>
    </div>
</div>
<?php endforeach; ?>

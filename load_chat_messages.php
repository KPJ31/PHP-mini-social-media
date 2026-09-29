<?php
require_once 'config.php';
require_once 'session.php';
require_once 'function.php';
$viewerId = (int) $_SESSION['user_id'];
$friendId = inputId($_GET, 'receiver_id');
requireFriend($viewerId, $friendId);
$conversation = conversationMessages($viewerId, $friendId, inputId($_GET, 'before'));
require 'message_list.php';

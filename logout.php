<?php
require_once 'security.php';
requirePost();
$_SESSION = [];
session_destroy();
header('Location: login.php');
exit;

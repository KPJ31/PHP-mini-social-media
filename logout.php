<?php
require_once 'security.php';
requirePost();
clearLoginSession();
header('Location: login.php');
exit;

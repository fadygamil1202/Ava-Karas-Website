<?php
require 'includes/config.php';
$_SESSION = [];
session_destroy();
header('Location: login.php');
exit;

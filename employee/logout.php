<?php
session_start();
require_once '../includes/session.php';

// Destroy session
destroySession();

// Redirect to login
header('Location: login.php');
exit();
?>

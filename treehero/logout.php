<?php
session_start(); // Start the session to access session variables

// Unset all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to the login page (index.php)
header('Location: index.php?logged_out=true');
exit();
?>

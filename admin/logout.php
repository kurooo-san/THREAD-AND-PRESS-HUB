<?php
require '../includes/config.php';

// Check if user is logged in
if (isLoggedIn()) {
    // Destroy session
    session_destroy();
}

// Redirect to home page
header("Location: ../index.php");
exit();
?>

<?php
require_once 'config.php';

// Redirect root traffic to dashboard if logged in, or login if not
if (isLoggedIn()) {
    header('Location: dashboard.php');
} else {
    header('Location: login.php');
}
exit();

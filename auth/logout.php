
<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Only allow POST requests.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /NSBM_360/index.php');
    exit;
}

// Clear session data.
$_SESSION = [];

// Remove session cookie.
if (ini_get('session.use_cookies')) {

    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// Destroy session.
session_destroy();

// Redirect to Home Page.
header('Location: /NSBM_360/index.php');
exit;

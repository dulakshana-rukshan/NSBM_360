
<?php
// Start session only if it has not already been started.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Identify the current page.
$currentPage = basename($_SERVER['SCRIPT_NAME'] ?? '');

// Check login status.
$isLoggedIn = isset($_SESSION['user_id']);

// Default header button.
$buttonText = 'Login';
$buttonLink = '/NSBM_360/auth/login.php';
$buttonIcon = 'bi-person-circle';

// Logged-in users see the Profile icon.
if ($isLoggedIn) {
    $buttonText = '';
    $buttonLink = '/NSBM_360/pages/profile.php';
    $buttonIcon = 'bi-person-circle';

// Login page shows Sign Up.
} elseif ($currentPage === 'login.php') {
    $buttonText = 'Sign Up';
    $buttonLink = '/NSBM_360/auth/register.php';
    $buttonIcon = 'bi-person-plus';

// Register page shows Sign In.
} elseif ($currentPage === 'register.php') {
    $buttonText = 'Sign In';
    $buttonLink = '/NSBM_360/auth/login.php';
    $buttonIcon = 'bi-box-arrow-in-right';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        <?php
        echo htmlspecialchars(
            $pageTitle ?? 'NSBM 360 | Your Campus. Your World.',
            ENT_QUOTES,
            'UTF-8'
        );
        ?>
    </title>

    <!-- Bootstrap CSS -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css"
        rel="stylesheet"
        integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC"
        crossorigin="anonymous"
    >

    <!-- Bootstrap Icons -->
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    >

    <!-- Global CSS -->
    <link
        rel="stylesheet"
        href="/NSBM_360/assets/css/style.css"
    >

    <!-- Page-specific CSS -->
    <?php if (!empty($pageCSS)): ?>
        <link
            rel="stylesheet"
            href="<?php echo htmlspecialchars($pageCSS, ENT_QUOTES, 'UTF-8'); ?>"
        >
    <?php endif; ?>

</head>

<body>

    <header>
        <div class="header">

            <!-- Website Logo -->
            <a href="/NSBM_360/index.php">
                <img
                    src="/NSBM_360/assets/image/NSBM_360_Logo_without_background_1.png"
                    alt="NSBM 360 Logo"
                    class="logo"
                >
            </a>

            <!-- Dynamic Header Button -->
            <div class="login">
                <a href="<?php echo htmlspecialchars($buttonLink, ENT_QUOTES, 'UTF-8'); ?>"
                   <?php if ($isLoggedIn): ?>
                       aria-label="My Profile"
                       title="My Profile"
                   <?php endif; ?>
                >
                    <i class="bi <?php echo htmlspecialchars($buttonIcon, ENT_QUOTES, 'UTF-8'); ?>"></i>

                    <?php if ($buttonText !== ''): ?>
                        <?php echo htmlspecialchars($buttonText, ENT_QUOTES, 'UTF-8'); ?>
                    <?php endif; ?>
                </a>
            </div>

        </div>
    </header>

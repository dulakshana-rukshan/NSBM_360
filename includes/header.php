
<?php
// Start session only if needed.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Identify current page.
$currentPage = basename($_SERVER['SCRIPT_NAME'] ?? '');

// Check login status.
$isLoggedIn = isset($_SESSION['user_id']);

// Check admin role.
$isAdmin = $isLoggedIn &&
    ($_SESSION['role'] ?? '') === 'admin';

// Default header button.
$buttonText = 'Login';
$buttonLink = '/NSBM_360/auth/login.php';
$buttonIcon = 'bi-person-circle';

$profileImage = null;

// Logged-in user.
if ($isLoggedIn) {

    $buttonText = '';
    $buttonLink = '/NSBM_360/profile.php';
    $buttonIcon = '';

    // First get profile image from session.
    $profileImage = $_SESSION['profile_image'] ?? null;

    // Load latest profile image from database.
    try {

        require_once __DIR__ . '/../config/database.php';

        if (isset($pdo) && $pdo instanceof PDO) {

            $stmt = $pdo->prepare(
                "SELECT profile_image
                 FROM users
                 WHERE user_id = ?"
            );

            $stmt->execute([(int)$_SESSION['user_id']]);

            $dbProfileImage = $stmt->fetchColumn();

            if (!empty($dbProfileImage)) {

                $dbProfileImage = str_replace(
                    '\\',
                    '/',
                    trim($dbProfileImage)
                );

                if (str_starts_with($dbProfileImage, '/NSBM_360/')) {
                    $profileImage = $dbProfileImage;
                } else {
                    $profileImage = '/NSBM_360/' .
                        ltrim($dbProfileImage, '/');
                }

            } else {
                $profileImage = null;
            }

            $_SESSION['profile_image'] = $profileImage;
        }

    } catch (Throwable $e) {

        error_log(
            'Header profile image error: ' . $e->getMessage()
        );

    }

// Login page.
} elseif ($currentPage === 'login.php') {

    $buttonText = 'Sign Up';
    $buttonLink = '/NSBM_360/auth/register.php';
    $buttonIcon = 'bi-person-plus';

// Register page.
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

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

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
            href="<?php echo htmlspecialchars(
                $pageCSS,
                ENT_QUOTES,
                'UTF-8'
            ); ?>"
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

        <!-- Dynamic Header Buttons -->
        <div class="header-actions">

            <?php if ($isLoggedIn): ?>

                <?php if ($currentPage === 'profile.php'): ?>

                    <!-- Logout Button - Profile Page Only -->
                    <form
                        action="/NSBM_360/auth/logout.php"
                        method="POST"
                        class="header-logout-form"
                    >

                        <button
                            type="submit"
                            class="header-logout-btn"
                        >
                            <i class="bi bi-box-arrow-right"></i>
                            Logout
                        </button>

                    </form>

                <?php else: ?>

                    <!-- Admin Dashboard Button -->
                    <?php if ($isAdmin): ?>

                        <a
                            href="/NSBM_360/admin/admin_overview.php"
                            class="header-admin-btn"
                        >
                            <i class="bi bi-speedometer2"></i>
                            Admin Dashboard
                        </a>

                    <?php endif; ?>

                    <!-- Profile Image / Icon -->
                    <div class="header-profile">

                        <a
                            href="/NSBM_360/profile.php"
                            class="profile-link"
                            title="My Profile"
                            aria-label="My Profile"
                        >

                            <?php if (!empty($profileImage)): ?>

                                <img
                                    src="<?php echo htmlspecialchars(
                                        $profileImage,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>"
                                    alt="My Profile"
                                    class="header-profile-image"
                                >

                            <?php else: ?>

                                <span class="header-profile-placeholder">

                                    <i class="bi bi-person"></i>

                                </span>

                            <?php endif; ?>

                        </a>

                    </div>

                <?php endif; ?>

            <?php else: ?>

                <!-- Login / Sign Up / Sign In -->
                <div class="login">

                    <a href="<?php echo htmlspecialchars(
                        $buttonLink,
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>">

                        <i class="bi <?php echo htmlspecialchars(
                            $buttonIcon,
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>"></i>

                        <?php echo htmlspecialchars(
                            $buttonText,
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>

                    </a>

                </div>

            <?php endif; ?>

        </div>

    </div>

</header>

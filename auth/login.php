<?php
/* ===========================================================
   NSBM 360 - Login page
   Uses columns in the EXISTING users table.
   =========================================================== */
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Change these details if your XAMPP MySQL settings are different.
$dbHost = 'localhost';
$dbUser = 'root';
$dbPassword = '';
$dbName = 'nsbm_360';

// Small helper: escape user/database text before displaying it.
function e($text) {
    return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
}

// CSRF token ensures requests come from our form.
if (empty($_SESSION['login_csrf'])) {
    $_SESSION['login_csrf'] = bin2hex(random_bytes(24));
}

$error = '';
$email = '';

// Handle the sign-in form.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    if (!hash_equals($_SESSION['login_csrf'], (string)($_POST['csrf'] ?? ''))) {
        $error = 'Security check failed. Refresh the page and try again.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $error = 'Enter a valid email address and password.';
    } else {
        try {
            // Find the login account by unique email address.
            $db = new mysqli($dbHost, $dbUser, $dbPassword, $dbName);
            $db->set_charset('utf8mb4');
            $stmt = $db->prepare('SELECT user_id, first_name, role, password FROM users WHERE email = ? LIMIT 1');
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            // password_verify() works with the password_hash() value saved at registration.
            if ($user && password_verify($password, $user['password'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int)$user['user_id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['first_name'] = $user['first_name'];
                unset($_SESSION['login_csrf']);

                // Admins go to the dashboard; students go to the public campus pages.
                if ($user['role'] === 'admin') {
                    header('Location: overview.php');
                } elseif (is_file(__DIR__ . '/profile.php')) {
                    header('Location: profile.php');
                } elseif (is_file(__DIR__ . '/search.php')) {
                    header('Location: search.php');
                } else {
                    header('Location: index.php');
                }
                exit;
            }
            // Same error for unknown email and incorrect password.
            $error = 'Incorrect email or password.';
        } catch (mysqli_sql_exception $ex) {
            error_log($ex->getMessage());
            $error = 'Database connection failed. Check XAMPP and database settings.';
        }
    }
}


?>
<?php
    $pageCSS = 'login_style.css';
    $pageTitle = 'Sign in | NSBM 360';


    include '../includes/header.php';
?>
<!-- <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in | NSBM 360</title>
    <link rel="stylesheet" href="login_style.css">
</head>
<body> -->
    <main class="auth-layout">
        <aside class="campus-card" aria-label="NSBM 360 community">
            <img src="../assets/image/login_page_image.jpg" alt="NSBM campus buildings and lake">
            <div class="campus-card-content">
                <div class="eyebrow">ONE CAMPUS. COUNTLESS STORIES.</div>
                <h2>Stay close to your<br>campus community.</h2>
                <p>Read every story without signing in. An account helps club leaders share the moments that matter.</p>
            </div>
        </aside>

        <section class="auth-content">
            <a class="back-link" href="index.php">← Back to campus</a>
            <h1>Welcome back.</h1>
            <p class="lead">Sign in to your NSBM 360 account to manage your profile or your club's stories.</p>

            <?php if ($error): ?>
                <div class="notice error" role="alert"><?= e($error) ?></div>
            <?php endif; ?>
            <?php if (isset($_GET['registered'])): ?>
                <div class="notice success">Account created. Please verify your university email when the confirmation link arrives.</div>
            <?php endif; ?>
            <?php if (isset($_GET['verified'])): ?>
                <div class="notice success">Your email has been verified. You may sign in.</div>
            <?php endif; ?>
            <?php if (isset($_GET['reset'])): ?>
                <div class="notice success">Your password was updated. Sign in with the new password.</div>
            <?php endif; ?>

            <form method="post" action="login.php">
                <input type="hidden" name="csrf" value="<?= e($_SESSION['login_csrf']) ?>">
                <div class="field">
                    <label for="email">University email *</label>
                    <input id="email" type="email" name="email" autocomplete="username" maxlength="150" value="<?= e($email) ?>" placeholder="you@students.nsbm.ac.lk" required>
                </div>
                <div class="field">
                    <label for="password">Password *</label>
                    <input id="password" type="password" name="password" autocomplete="current-password" required>
                </div>
                <div class="below-password"><a href="passreset.php">Forgot password?</a></div>
                <button class="green-button" type="submit">Sign in →</button>
            </form>
            <p class="secondary-line">New to NSBM 360? <a href="register.php">Create an account</a></p>
            <div class="notice success info">
                <strong>◇ &nbsp;Just here to explore?</strong><br>
                All campus news, club profiles and events are public. You don't need an account to browse.
            </div>
        </section>
    </main>
</body>
<?php
    include '../includes/footer.php';
?>
</html>

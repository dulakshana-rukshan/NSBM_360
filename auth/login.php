
<?php
/* ===========================================================
   NSBM 360 - Login Page
   PDO Database + Admin Role Detection + Header Integration
   =========================================================== */

// Start session only if needed.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection.
require_once __DIR__ . '/../config/database.php';

// Page-specific CSS and title.
$pageCSS = '/NSBM_360/assets/css/login_style.css';
$pageTitle = 'Sign in | NSBM 360';

// Escape output for HTML.
if (!function_exists('e')) {
    function e($text) {
        return htmlspecialchars(
            (string)$text,
            ENT_QUOTES,
            'UTF-8'
        );
    }
}

// Create CSRF token.
if (empty($_SESSION['login_csrf'])) {
    $_SESSION['login_csrf'] = bin2hex(random_bytes(24));
}

$error = '';
$email = '';

// Handle login form.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $csrf = (string)($_POST['csrf'] ?? '');

    if (!hash_equals($_SESSION['login_csrf'], $csrf)) {

        $error = 'Security check failed. Refresh the page and try again.';

    } elseif (
        !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        $password === ''
    ) {

        $error = 'Enter a valid email address and password.';

    } elseif (!isset($pdo) || !($pdo instanceof PDO)) {

        $error = 'Database connection is not available.';

    } else {

        try {

            // Find user by email.
            $stmt = $pdo->prepare(
                'SELECT
                    user_id,
                    first_name,
                    role,
                    password
                 FROM users
                 WHERE email = ?
                 LIMIT 1'
            );

            $stmt->execute([$email]);

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Verify password.
            if (
                $user &&
                password_verify($password, $user['password'])
            ) {

                // Prevent session fixation.
                session_regenerate_id(true);

                // Save login information.
                $_SESSION['user_id'] = (int)$user['user_id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['first_name'] = $user['first_name'];

                // Remove login CSRF token.
                unset($_SESSION['login_csrf']);

                // Redirect based on database role.
                // Both Admin and Normal User redirect to Home Page
                header('Location: /NSBM_360/index.php');
                exit;
            }

            // Incorrect credentials.
            $error = 'Incorrect email or password.';

        } catch (PDOException $ex) {

            error_log($ex->getMessage());

            $error = 'Database connection failed. Check XAMPP and database settings.';
        }
    }
}

// Shared website header.
include __DIR__ . '/../includes/header.php';
?>

<main class="auth-layout">

    <!-- Left Side - Campus Image -->
    <aside class="campus-card" aria-label="NSBM 360 community">

        <img
            src="../assets/image/login_page_image.jpg"
            alt="NSBM campus buildings and lake"
        >

        <div class="campus-card-content">

            <div class="eyebrow">
                ONE CAMPUS. COUNTLESS STORIES.
            </div>

            <h2>
                Stay close to your<br>
                campus community.
            </h2>

            <p>
                Read every story without signing in.
                An account helps club leaders share
                the moments that matter.
            </p>

        </div>

    </aside>

    <!-- Right Side - Login Form -->
    <section class="auth-content">

        <a
            class="back-link"
            href="/NSBM_360/index.php"
        >
            ← Back to campus
        </a>

        <h1>Welcome back.</h1>

        <p class="lead">
            Sign in to your NSBM 360 account
            to manage your profile or your club's stories.
        </p>

        <!-- Login Error -->
        <?php if ($error): ?>

            <div class="notice error" role="alert">
                <?= e($error) ?>
            </div>

        <?php endif; ?>

        <!-- Registration Success -->
        <?php if (isset($_GET['registered'])): ?>

            <div class="notice success">
                Account created. Please verify your university
                email when the confirmation link arrives.
            </div>

        <?php endif; ?>

        <!-- Email Verification Success -->
        <?php if (isset($_GET['verified'])): ?>

            <div class="notice success">
                Your email has been verified.
                You may sign in.
            </div>

        <?php endif; ?>

        <!-- Password Reset Success -->
        <?php if (isset($_GET['reset'])): ?>

            <div class="notice success">
                Your password was updated.
                Sign in with the new password.
            </div>

        <?php endif; ?>

        <!-- Login Form -->
        <form
            method="post"
            action="/NSBM_360/auth/login.php"
        >

            <!-- CSRF Token -->
            <input
                type="hidden"
                name="csrf"
                value="<?= e($_SESSION['login_csrf']) ?>"
            >

            <!-- Email -->
            <div class="field">

                <label for="email">
                    University email *
                </label>

                <input
                    id="email"
                    type="email"
                    name="email"
                    autocomplete="username"
                    maxlength="150"
                    value="<?= e($email) ?>"
                    placeholder="you@students.nsbm.ac.lk"
                    required
                >

            </div>

            <!-- Password -->
            <div class="field">

                <label for="password">
                    Password *
                </label>

                <input
                    id="password"
                    type="password"
                    name="password"
                    autocomplete="current-password"
                    required
                >

            </div>

            <!-- Forgot Password -->
            <div class="below-password">

                <a href="passreset.php">
                    Forgot password?
                </a>

            </div>

            <!-- Submit -->
            <button
                class="green-button"
                type="submit"
            >
                Sign in →
            </button>

        </form>

        <!-- Registration -->
        <p class="secondary-line">

            New to NSBM 360?

            <a href="register.php">
                Create an account
            </a>

        </p>

        <!-- Public Access Information -->
        <div class="notice success info">

            <strong>
                ◇ &nbsp;Just here to explore?
            </strong>

            <br>

            All campus news, club profiles and events
            are public. You don't need an account to browse.

        </div>

    </section>

</main>

<?php
// Shared website footer.
include __DIR__ . '/../includes/footer.php';
?>

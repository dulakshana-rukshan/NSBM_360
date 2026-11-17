<?php
/* ===========================================================
   NSBM 360 - Forgot Password with 3-Step OTP Verification
   PHP + PDO + PHPMailer + Gmail SMTP
   =========================================================== */

// Initialize session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Require Composer autoloader for PHPMailer
require_once __DIR__ . '/../vendor/autoload.php';

// Include global configuration and constants first
require_once __DIR__ . '/../config/constants.php';

// Include database connection (uses variables defined in constants.php)
require_once __DIR__ . '/../config/database.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Set page specific CSS and title
$pageCSS = '/NSBM_360/assets/css/forgot_style.css';
$pageTitle = 'Password reset | NSBM 360';

// Sanitize output text to prevent XSS attacks
function e($text) {
    return htmlspecialchars(
        (string)$text,
        ENT_QUOTES,
        'UTF-8'
    );
}

// Send OTP email using Gmail SMTP configuration loaded from constants
function send_otp_email(
    string $receiverEmail,
    string $otpCode
): bool {
    // Retrieve SMTP credentials from global constants
    $smtpUsername = SMTP_USER;
    $smtpPassword = SMTP_PASS;

    $mail = new PHPMailer(true);

    try {
        // Enable SMTP debugging/handling
        $mail->isSMTP();

        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;

        $mail->Username = $smtpUsername;
        $mail->Password = $smtpPassword;

        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;

        // Bypass SSL certificate verification for local XAMPP testing
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        $mail->CharSet = 'UTF-8';

        $mail->setFrom(
            $smtpUsername,
            'NSBM 360'
        );

        $mail->addAddress(
            $receiverEmail
        );

        $mail->isHTML(true);

        $mail->Subject = 'Password Reset OTP - NSBM 360';

        $safeOtp = htmlspecialchars(
            $otpCode,
            ENT_QUOTES,
            'UTF-8'
        );

        // HTML body content for the OTP email
        $mail->Body =
            '<h2>NSBM 360 Password Reset</h2>' .
            '<p>A password reset was requested for your account.</p>' .
            '<p>Your OTP verification code is:</p>' .
            '<h1 style="color: #008000; letter-spacing: 2px;">' . $safeOtp . '</h1>' .
            '<p>This code is valid for 15 minutes. Do not share this code with anyone.</p>' .
            '<br><p>NSBM 360 Team</p>';

        // Plain text alternative body
        $mail->AltBody =
            "NSBM 360 Password Reset\n\n" .
            "Your OTP verification code is: " . $otpCode . "\n\n" .
            "This code is valid for 15 minutes.\n" .
            "If you did not request this, ignore this email.";

        // Send the email notification
        $mail->send();

        return true;

    } catch (Exception $ex) {
        // Log mail delivery error details
        error_log(
            'NSBM360 Mail Error: ' . $mail->ErrorInfo
        );

        return false;
    }
}

// Send confirmation email after password has been successfully updated
function send_password_changed_email(
    string $receiverEmail
): bool {
    $smtpUsername = SMTP_USER;
    $smtpPassword = SMTP_PASS;

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();

        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;

        $mail->Username = $smtpUsername;
        $mail->Password = $smtpPassword;

        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;

        // Bypass SSL certificate verification for local XAMPP testing
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        $mail->CharSet = 'UTF-8';

        $mail->setFrom(
            $smtpUsername,
            'NSBM 360'
        );

        $mail->addAddress(
            $receiverEmail
        );

        $mail->isHTML(true);

        $mail->Subject = 'Password Successfully Changed - NSBM 360';

        $mail->Body =
            '<h2>NSBM 360 Security Notice</h2>' .
            '<p>Your account password has been successfully updated.</p>' .
            '<p>If you did not perform this change, please contact the NSBM 360 administration immediately.</p>' .
            '<br><p>NSBM 360 Team</p>';

        $mail->AltBody =
            "NSBM 360 Security Notice\n\n" .
            "Your account password has been successfully updated.\n" .
            "If you did not perform this change, please contact administration immediately.";

        $mail->send();

        return true;

    } catch (Exception $ex) {
        error_log(
            'NSBM360 Password Change Mail Error: ' . $mail->ErrorInfo
        );

        return false;
    }
}

// Initialize error and success message variables
$error = '';
$success = '';

// Reset the password reset flow if requested via GET parameter
if (isset($_GET['reset_flow'])) {
    unset(
        $_SESSION['pwd_reset_step'],
        $_SESSION['pwd_reset_email'],
        $_SESSION['pwd_reset_otp'],
        $_SESSION['pwd_reset_expires'],
        $_SESSION['pwd_otp_verified']
    );

    header('Location: forgot.php');
    exit;
}

// Determine current step mode
$mode = $_SESSION['pwd_reset_step'] ?? 'request';
$email = trim(
    (string)(
        $_POST['email'] ??
        $_SESSION['pwd_reset_email'] ??
        ''
    )
);

// Process POST form submissions
if (
    isset($pdo) &&
    ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
) {
    $action = $_POST['action'] ?? '';

    // Step 1: Request OTP by entering university email address
    if ($action === 'request') {

        if (
            !filter_var($email, FILTER_VALIDATE_EMAIL) ||
            strlen($email) > 150
        ) {
            $error = 'Enter a valid university email address.';

        } else {
            // Check if user exists in the database
            $stmt = $pdo->prepare(
                'SELECT user_id, email
                 FROM users
                 WHERE email = ?
                 LIMIT 1'
            );

            $stmt->execute([$email]);

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // If account exists, generate and send 6-digit OTP code
            if ($user) {
                $otp = (string)rand(
                    100000,
                    999999
                );

                $_SESSION['pwd_reset_otp'] = $otp;
                $_SESSION['pwd_reset_email'] = $email;
                $_SESSION['pwd_reset_expires'] = time() + 900; // 15 minutes validity
                $_SESSION['pwd_otp_verified'] = false;

                $emailSent = send_otp_email(
                    $email,
                    $otp
                );

                if (!$emailSent) {
                    error_log(
                        'NSBM360 password reset OTP email delivery failed.'
                    );
                }
            }

            // Move to OTP verification step
            $_SESSION['pwd_reset_step'] = 'verify_otp';
            $mode = 'verify_otp';

            // Display uniform success message for security purposes
            $success =
                'If an account exists for this email, an OTP code has been sent. Check your inbox.';
        }

    }
    // Step 2: Verify the entered 6-digit OTP code
    elseif ($action === 'verify_otp') {

        $enteredOtp = trim(
            (string)($_POST['otp_code'] ?? '')
        );

        $storedOtp = $_SESSION['pwd_reset_otp'] ?? '';
        $expires = $_SESSION['pwd_reset_expires'] ?? 0;

        if (
            empty($enteredOtp) ||
            empty($storedOtp)
        ) {
            $error =
                'Session expired or invalid request. Please start over.';

            $mode = 'verify_otp';

        } elseif (time() > $expires) {
            $error =
                'OTP code has expired. Please request a new code.';

            $mode = 'verify_otp';

        } elseif ($enteredOtp !== $storedOtp) {
            $error =
                'Invalid OTP code. Please check and try again.';

            $mode = 'verify_otp';

        } else {
            // OTP is valid, proceed to new password creation step
            $_SESSION['pwd_otp_verified'] = true;
            $_SESSION['pwd_reset_step'] = 'reset_password';
            $mode = 'reset_password';

            // Unset stored OTP code for security once verified
            unset($_SESSION['pwd_reset_otp']);

            $success =
                'OTP verified! Please create your new password below.';
        }

    }
    // Step 3: Set new password (only allowed after successful OTP verification)
    elseif ($action === 'reset_password') {

        $isVerified = $_SESSION['pwd_otp_verified'] ?? false;
        $resetEmail = $_SESSION['pwd_reset_email'] ?? '';

        $password = (string)($_POST['password'] ?? '');
        $confirm = (string)(
            $_POST['confirm_password'] ?? ''
        );

        if (
            !$isVerified ||
            empty($resetEmail)
        ) {
            $error =
                'Unauthorized request. Please verify your OTP first.';

            $_SESSION['pwd_reset_step'] = 'request';
            $mode = 'request';

        } elseif (
            strlen($password) < 5 ||
            !preg_match('/[A-Za-z]/', $password) ||
            !preg_match('/[0-9]/', $password)
        ) {
            $error =
                'Password must have at least 5 characters, a letter and a number.';

            $mode = 'reset_password';

        } elseif ($password !== $confirm) {
            $error =
                'Passwords do not match.';

            $mode = 'reset_password';

        } else {
            // Hash the new password securely
            $newHash = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            // Update password in database
            $stmt = $pdo->prepare(
                'UPDATE users
                 SET password = ?
                 WHERE email = ?'
            );

            $stmt->execute([
                $newHash,
                $resetEmail
            ]);

            // Send password change confirmation email to user before redirecting
            send_password_changed_email($resetEmail);

            // Clear all password reset session variables
            unset(
                $_SESSION['pwd_reset_step'],
                $_SESSION['pwd_reset_email'],
                $_SESSION['pwd_reset_otp'],
                $_SESSION['pwd_reset_expires'],
                $_SESSION['pwd_otp_verified']
            );

            // Redirect to login page with success notification flag
            header('Location: login.php?reset=1');
            exit;
        }
    }
}

// Include page header template
include '../includes/header.php';
?>

<main class="auth-layout">

    <aside class="campus-card">

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

    <section class="auth-content">

        <a class="back-link" href="../index.php">
            ← Back to campus
        </a>

        <h1>
            <?php
            if ($mode === 'verify_otp') {
                echo 'Verify your OTP.';
            } elseif ($mode === 'reset_password') {
                echo 'Create a new password.';
            } else {
                echo 'Let’s get you back in.';
            }
            ?>
        </h1>

        <p class="lead">
            <?php
            if ($mode === 'verify_otp') {
                echo 'Enter the 6-digit verification code sent to your university email.';
            } elseif ($mode === 'reset_password') {
                echo 'Choose a strong, secure new password for your NSBM 360 account.';
            } else {
                echo 'Forgot your password? Enter your university email and we’ll send you a verification OTP.';
            }
            ?>
        </p>

        <div class="key-icon" aria-hidden="true">
            ⚿
        </div>

        <?php if ($error): ?>
            <div class="notice error" role="alert">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="notice success">
                <?= e($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($mode === 'verify_otp'): ?>

            <!-- Step 2: OTP Verification Form -->
            <form method="post" action="forgot.php">

                <input
                    type="hidden"
                    name="action"
                    value="verify_otp"
                >

                <div class="field">

                    <label for="otp_code">
                        Verification Code (OTP) *
                    </label>

                    <input
                        id="otp_code"
                        name="otp_code"
                        type="text"
                        maxlength="6"
                        required
                        placeholder="123456"
                        autocomplete="one-time-code"
                        style="letter-spacing: 3px; font-size: 1.2rem;"
                    >

                    <small>
                        Check your email inbox for the 6-digit code.
                    </small>

                </div>

                <button
                    class="green-button"
                    type="submit"
                >
                    Verify OTP
                </button>

            </form>

            <p class="secondary-line">
                <a href="forgot.php?reset_flow=1">
                    ← Start over / Change email
                </a>
            </p>

        <?php elseif ($mode === 'reset_password'): ?>

            <!-- Step 3: Create New Password Form (Shown only after OTP is verified) -->
            <form method="post" action="forgot.php">

                <input
                    type="hidden"
                    name="action"
                    value="reset_password"
                >

                <div class="field">

                    <label for="password">
                        New password *
                    </label>

                    <input
                        id="password"
                        type="password"
                        name="password"
                        minlength="5"
                        autocomplete="new-password"
                        required
                    >

                    <small>
                        At least 5 characters with a letter and a number.
                    </small>

                </div>

                <div class="field">

                    <label for="confirm_password">
                        Confirm new password *
                    </label>

                    <input
                        id="confirm_password"
                        type="password"
                        name="confirm_password"
                        minlength="5"
                        autocomplete="new-password"
                        required
                    >

                </div>

                <button
                    class="green-button"
                    type="submit"
                >
                    Update password
                </button>

            </form>

            <p class="secondary-line">
                <a href="forgot.php?reset_flow=1">
                    ← Cancel / Start over
                </a>
            </p>

        <?php else: ?>

            <!-- Step 1: Request Email Form -->
            <form method="post" action="forgot.php">

                <input
                    type="hidden"
                    name="action"
                    value="request"
                >

                <div class="field">

                    <label for="email">
                        University email *
                    </label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        maxlength="150"
                        required
                        value="<?= e($email) ?>"
                        placeholder="you@students.nsbm.ac.lk"
                    >

                </div>

                <button
                    class="green-button"
                    type="submit"
                >
                    ✉  Send OTP code
                </button>

            </form>

            <div class="notice success info">

                <strong>
                    ◇  Check your inbox
                </strong>

                <br>

                If an account exists, you'll receive a
                6-digit OTP code valid for 15 minutes.

                Check your spam folder if needed.

            </div>

        <?php endif; ?>

        <p class="secondary-line">
            <a href="login.php">
                ← Back to sign in
            </a>
        </p>

        <p class="help-text">
            No longer have access to your university email?
            <br>
            Contact your university administrator for
            account support.
        </p>

    </section>

</main>

<?php
// Include page footer template
include '../includes/footer.php';
?>
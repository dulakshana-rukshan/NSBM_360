
<?php
/*
===========================================================
NSBM 360 - Registration with OTP Verification
PHP + PDO + PHPMailer + Gmail SMTP
===========================================================
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

$pageCSS = '/NSBM_360/assets/css/register_style.css';
$pageTitle = 'Create account | NSBM 360';

if (!function_exists('e')) {
    function e($text) {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

// Reset registration session
if (isset($_GET['reset'])) {
    unset(
        $_SESSION['reg_step'],
        $_SESSION['reg_otp'],
        $_SESSION['temp_reg_data'],
        $_SESSION['otp_expires'],
        $_SESSION['reg_success_message'],
        $_SESSION['reg_otp_attempts']
    );

    header('Location: register.php');
    exit;
}

// Send email through Gmail SMTP
function send_mail_notification(
    string $receiverEmail,
    string $subject,
    string $htmlBody,
    string $altBody
): bool {

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;

        // Local XAMPP SSL settings
        // Use proper certificate verification in production.
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];

        $mail->CharSet = 'UTF-8';
        $mail->setFrom(SMTP_USER, 'NSBM 360');
        $mail->addAddress($receiverEmail);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = $altBody;

        $mail->send();
        return true;

    } catch (MailException $ex) {
        error_log('NSBM360 Mail Error: ' . $mail->ErrorInfo);
        return false;
    }
}

// Check whether a club exists and is active
function get_active_club(PDO $pdo, int $clubId) {

    if ($clubId < 1) {
        return false;
    }

    $stmt = $pdo->prepare(
        "SELECT club_id, club_name, club_type
         FROM clubs
         WHERE club_id = ?
           AND status = 'active'
         LIMIT 1"
    );

    $stmt->execute([$clubId]);

    return $stmt->fetch(PDO::FETCH_ASSOC);
}

$error = '';
$successMessage = '';
$clubs = [];

// Load General and Sports Clubs together
try {
    $stmt = $pdo->query(
        "SELECT club_id, club_name, club_type
         FROM clubs
         WHERE status = 'active'
         ORDER BY club_name ASC"
    );

    $clubs = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $ex) {
    error_log($ex->getMessage());
    $error = 'Unable to load clubs list.';
}

// Form values
$firstName = trim($_POST['first_name'] ?? '');
$lastName = trim($_POST['last_name'] ?? '');
$studentId = trim($_POST['student_id'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');

$requestedRole = $_POST['requested_role'] ?? 'student';
$requestedClub = (int) ($_POST['club_id'] ?? 0);

// Registration processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '') {

    $action = $_POST['action'] ?? 'register';

    /*
    ===========================================================
    STEP 1 - Registration Form and Send OTP
    ===========================================================
    */

    if ($action === 'register') {

        $password = (string) ($_POST['password'] ?? '');
        $confirmation = (string) ($_POST['confirm_password'] ?? '');

        // Validate user details
        if ($firstName === '' || strlen($firstName) > 100) {

            $error = 'Enter a valid first name (up to 100 characters).';

        } elseif ($lastName === '' || strlen($lastName) > 100) {

            $error = 'Enter a valid last name (up to 100 characters).';

        } elseif (
            $studentId === '' ||
            strlen($studentId) > 30 ||
            $phone === '' ||
            strlen($phone) > 20
        ) {

            $error = 'Enter a valid student ID and phone number.';

        } elseif (
            !filter_var($email, FILTER_VALIDATE_EMAIL) ||
            strlen($email) > 150 ||
            !preg_match('/@students\.nsbm\.ac\.lk$/i', $email)
        ) {

            $error = 'Invalid email! Please use your official NSBM student email ending with @students.nsbm.ac.lk.';

        } elseif (strlen($password) < 5) {

            $error = 'Password must be at least 5 characters long.';

        } elseif ($password !== $confirmation) {

            $error = 'Passwords do not match.';

        } elseif (
            !in_array(
                $requestedRole,
                ['student', 'president', 'vice_president'],
                true
            )
        ) {

            $error = 'Select a valid student or club leadership role.';

        } elseif (
            $requestedRole !== 'student' &&
            $requestedClub < 1
        ) {

            $error = 'Choose the club you are requesting to lead.';

        } else {

            try {

                // Validate selected club
                if ($requestedRole !== 'student') {

                    $validClub = get_active_club(
                        $pdo,
                        $requestedClub
                    );

                    if (!$validClub) {
                        $error = 'Selected club is invalid or inactive.';
                    }

                } else {
                    $requestedClub = 0;
                }

                // Continue only if validation passed
                if ($error === '') {

                    // Check existing account
                    $checkStmt = $pdo->prepare(
                        'SELECT user_id
                         FROM users
                         WHERE email = ?
                            OR student_id = ?
                         LIMIT 1'
                    );

                    $checkStmt->execute([
                        $email,
                        $studentId
                    ]);

                    if ($checkStmt->fetch()) {

                        $error = 'Email or student ID is already registered.';

                    } else {

                        // Generate secure 6-digit OTP
                        $otp = (string) random_int(100000, 999999);

                        $_SESSION['temp_reg_data'] = [
                            'first_name' => $firstName,
                            'last_name' => $lastName,
                            'student_id' => $studentId,
                            'email' => $email,
                            'phone' => $phone,
                            'password_hash' => password_hash(
                                $password,
                                PASSWORD_DEFAULT
                            ),
                            'role' => $requestedRole,
                            'club_id' => $requestedClub
                        ];

                        $_SESSION['reg_otp'] = password_hash(
                            $otp,
                            PASSWORD_DEFAULT
                        );

                        $_SESSION['otp_expires'] = time() + 300;
                        $_SESSION['reg_otp_attempts'] = 0;

                        $safeFirstName = e($firstName);

                        $subject = 'Email Verification OTP - NSBM 360';

                        $htmlBody =
                            "<h2>NSBM 360 Account Verification</h2>" .
                            "<p>Hello <b>{$safeFirstName}</b>,</p>" .
                            "<p>Your OTP verification code is:</p>" .
                            "<h1 style='color:#008000;letter-spacing:2px;'>{$otp}</h1>" .
                            "<p>This code is valid for 5 minutes.</p>" .
                            "<p>Do not share this code with anyone.</p>" .
                            "<p>NSBM 360 Team</p>";

                        $altBody = "Your NSBM 360 OTP code is: {$otp}";

                        $mailSent = send_mail_notification(
                            $email,
                            $subject,
                            $htmlBody,
                            $altBody
                        );

                        if ($mailSent) {

                            $_SESSION['reg_step'] = 'verify_otp';

                        } else {

                            unset(
                                $_SESSION['reg_otp'],
                                $_SESSION['temp_reg_data'],
                                $_SESSION['otp_expires'],
                                $_SESSION['reg_otp_attempts']
                            );

                            $error = 'Failed to send verification email. Please check your internet connection and try again.';
                        }
                    }
                }

            } catch (PDOException $ex) {

                error_log($ex->getMessage());
                $error = 'Database error occurred.';

            }
        }

    /*
    ===========================================================
    STEP 2 - Verify OTP and Create Account
    ===========================================================
    */

    } elseif ($action === 'verify_otp') {

        $enteredOtp = trim($_POST['otp_code'] ?? '');

        $storedOtp = $_SESSION['reg_otp'] ?? '';
        $expires = (int) ($_SESSION['otp_expires'] ?? 0);
        $tempData = $_SESSION['temp_reg_data'] ?? [];
        $attempts = (int) ($_SESSION['reg_otp_attempts'] ?? 0);

        if (
            $enteredOtp === '' ||
            $storedOtp === '' ||
            empty($tempData)
        ) {

            $error = 'Session expired or invalid request. Please start over.';
            $_SESSION['reg_step'] = 'form';

        } elseif (time() > $expires) {

            $error = 'OTP code has expired. Please register again.';

            unset(
                $_SESSION['reg_otp'],
                $_SESSION['temp_reg_data'],
                $_SESSION['otp_expires'],
                $_SESSION['reg_otp_attempts']
            );

            $_SESSION['reg_step'] = 'form';

        } elseif ($attempts >= 5) {

            $error = 'Too many incorrect OTP attempts. Please register again.';

            unset(
                $_SESSION['reg_otp'],
                $_SESSION['temp_reg_data'],
                $_SESSION['otp_expires'],
                $_SESSION['reg_otp_attempts']
            );

            $_SESSION['reg_step'] = 'form';

        } elseif (
            !preg_match('/^\d{6}$/', $enteredOtp) ||
            !password_verify($enteredOtp, $storedOtp)
        ) {

            $_SESSION['reg_otp_attempts'] = $attempts + 1;

            $error = 'Invalid OTP code. Please check and try again.';

            if ($_SESSION['reg_otp_attempts'] >= 5) {

                unset(
                    $_SESSION['reg_otp'],
                    $_SESSION['temp_reg_data'],
                    $_SESSION['otp_expires'],
                    $_SESSION['reg_otp_attempts']
                );

                $_SESSION['reg_step'] = 'form';

                $error = 'Too many incorrect OTP attempts. Please register again.';

            } else {
                $_SESSION['reg_step'] = 'verify_otp';
            }

        } else {

            try {

                $selectedClubName = '';

                // Revalidate club before saving request
                if ($tempData['role'] !== 'student') {

                    $validClub = get_active_club(
                        $pdo,
                        (int) $tempData['club_id']
                    );

                    if (!$validClub) {
                        throw new RuntimeException(
                            'Selected club is no longer active. Please start registration again.'
                        );
                    }

                    $selectedClubName = $validClub['club_name'];
                }

                // Begin database transaction
                $pdo->beginTransaction();

                // Create regular user account
                $stmt = $pdo->prepare(
                    'INSERT INTO users
                    (
                        first_name,
                        last_name,
                        email,
                        password,
                        phone,
                        student_id,
                        role,
                        email_verified
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                );

                $stmt->execute([
                    $tempData['first_name'],
                    $tempData['last_name'],
                    $tempData['email'],
                    $tempData['password_hash'],
                    $tempData['phone'],
                    $tempData['student_id'],
                    'user',
                    1
                ]);

                $newUserId = (int) $pdo->lastInsertId();

                // Create leadership request
                if ($tempData['role'] !== 'student') {

                    $stmt = $pdo->prepare(
                        "INSERT INTO role_requests
                        (
                            user_id,
                            club_id,
                            requested_role,
                            request_status
                        )
                        VALUES (?, ?, ?, 'pending')"
                    );

                    $stmt->execute([
                        $newUserId,
                        (int) $tempData['club_id'],
                        $tempData['role']
                    ]);
                }

                // Commit both inserts
                $pdo->commit();

                /*
                ===================================================
                Welcome Email
                ===================================================
                */

                $safeName = e($tempData['first_name']);

                $welcomeSub = 'Welcome to NSBM 360!';

                $welcomeHtml =
                    "<h2>Welcome to NSBM 360, {$safeName}!</h2>" .
                    "<p>Your account has been successfully verified and created.</p>" .
                    "<p>You can now sign in to explore campus news, events, and community stories.</p>" .
                    "<p>Best regards,<br>NSBM 360 Team</p>";

                if ($tempData['role'] !== 'student') {

                    $welcomeHtml .=
                        "<p>Your club leadership request has been submitted.</p>" .
                        "<p>Club: " . e($selectedClubName) . "</p>" .
                        "<p>Requested role: " .
                        e(ucwords(str_replace('_', ' ', $tempData['role']))) .
                        "</p>" .
                        "<p>Status: Pending Admin Approval</p>";
                }

                $welcomeAlt =
                    "Welcome to NSBM 360! Your account has been created.";

                send_mail_notification(
                    $tempData['email'],
                    $welcomeSub,
                    $welcomeHtml,
                    $welcomeAlt
                );

                // Store success message
                if ($tempData['role'] === 'student') {

                    $successMessage =
                        'You are successfully registered and verified!';

                } else {

                    $successMessage =
                        'Your account has been created successfully! Your club leadership request is pending Admin approval.';
                }

                $_SESSION['reg_success_message'] = $successMessage;

                // Clear temporary registration data
                unset(
                    $_SESSION['reg_otp'],
                    $_SESSION['temp_reg_data'],
                    $_SESSION['otp_expires'],
                    $_SESSION['reg_otp_attempts']
                );

                $_SESSION['reg_step'] = 'success';

            } catch (Throwable $ex) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log('Registration Error: ' . $ex->getMessage());

                $error = $ex instanceof RuntimeException
                    ? $ex->getMessage()
                    : 'Unable to complete registration. Please try again.';

                $_SESSION['reg_step'] = 'verify_otp';
            }
        }
    }
}

// Current registration step
$step = $_SESSION['reg_step'] ?? 'form';

if ($step === 'success') {

    $successMessage =
        $_SESSION['reg_success_message'] ??
        'Registration completed successfully!';

    unset(
        $_SESSION['reg_step'],
        $_SESSION['reg_success_message']
    );
}

?>

<?php include '../includes/header.php'; ?>

<main class="auth-layout">

    <aside class="campus-card">

        <img
            src="../assets/image/login_page_image.jpg"
            alt="login page image"
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

        <a class="back-link" href="/NSBM_360/index.php">
            ← Back to campus
        </a>

        <h1>Join your campus community.</h1>

        <?php if ($error): ?>

            <div class="notice error" role="alert">
                <?= e($error) ?>
            </div>

        <?php endif; ?>

        <?php if ($step === 'success'): ?>

            <!-- Registration Success -->

            <div class="notice success" role="alert">
                <?= e($successMessage) ?>
            </div>

            <p class="success-action-wrap">
                <a href="login.php" class="green-button">
                    Proceed to Sign In →
                </a>
            </p>

        <?php elseif ($step === 'verify_otp'): ?>

            <!-- OTP Verification -->

            <p class="lead">
                Enter the 6-digit verification code sent to
                <b>
                    <?= e(
                        $_SESSION['temp_reg_data']['email']
                        ?? 'your email'
                    ) ?>
                </b>.
            </p>

            <form method="post" action="register.php">

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
                        minlength="6"
                        inputmode="numeric"
                        pattern="[0-9]{6}"
                        required
                        placeholder="123456"
                        autocomplete="one-time-code"
                        style="letter-spacing:3px;font-size:1.2rem;"
                    >

                    <small>
                        Check your inbox (and spam folder)
                        for the 6-digit code.
                    </small>

                </div>

                <button type="submit" class="green-button">
                    Verify & Complete Registration
                </button>

            </form>

            <p style="margin-top:15px;">
                <a href="register.php?reset=1">
                    ← Start over / Change email
                </a>
            </p>

        <?php else: ?>

            <!-- Registration Form -->

            <p class="lead">
                Create your account. If you lead a club,
                request publishing access for your assigned community.
            </p>

            <form
                method="post"
                action="register.php"
                id="registrationForm"
            >

                <input
                    type="hidden"
                    name="action"
                    value="register"
                >

                <!-- First and Last Name -->

                <div class="two-col">

                    <div class="field">

                        <label for="first_name">
                            First name *
                        </label>

                        <input
                            id="first_name"
                            name="first_name"
                            maxlength="100"
                            required
                            placeholder="Pasindu"
                            value="<?= e($firstName) ?>"
                        >

                    </div>

                    <div class="field">

                        <label for="last_name">
                            Last name *
                        </label>

                        <input
                            id="last_name"
                            name="last_name"
                            maxlength="100"
                            required
                            placeholder="Silva"
                            value="<?= e($lastName) ?>"
                        >

                    </div>

                </div>

                <!-- Student ID -->

                <div class="field">

                    <label for="student_id">
                        Student ID *
                    </label>

                    <input
                        id="student_id"
                        name="student_id"
                        maxlength="30"
                        required
                        placeholder="12345"
                        value="<?= e($studentId) ?>"
                    >

                </div>

                <!-- Email -->

                <div class="field">

                    <label for="email">
                        University email *
                    </label>

                    <input
                        id="email"
                        name="email"
                        type="email"
                        maxlength="150"
                        required
                        placeholder="yourname@students.nsbm.ac.lk"
                        value="<?= e($email) ?>"
                    >

                    <small>
                        Must use your official NSBM student email
                        ending with @students.nsbm.ac.lk.
                    </small>

                </div>

                <!-- Phone -->

                <div class="field">

                    <label for="phone">
                        Phone number *
                    </label>

                    <input
                        id="phone"
                        name="phone"
                        type="tel"
                        maxlength="20"
                        required
                        placeholder="07XXXXXXXX"
                        value="<?= e($phone) ?>"
                    >

                </div>

                <!-- Passwords -->

                <div class="two-col">

                    <div class="field">

                        <label for="password">
                            Password *
                        </label>

                        <input
                            id="password"
                            name="password"
                            type="password"
                            minlength="5"
                            required
                            autocomplete="new-password"
                        >

                        <small>
                            Use at least 5 characters.
                        </small>

                    </div>

                    <div class="field">

                        <label for="confirm_password">
                            Confirm password *
                        </label>

                        <input
                            id="confirm_password"
                            name="confirm_password"
                            type="password"
                            minlength="5"
                            required
                            autocomplete="new-password"
                        >

                    </div>

                </div>

                <!-- Registration Role -->

                <div class="field">

                    <label for="requested_role">
                        I am registering as *
                    </label>

                    <select
                        id="requested_role"
                        name="requested_role"
                        required
                    >

                        <option
                            value="student"
                            <?= $requestedRole === 'student'
                                ? 'selected' : '' ?>
                        >
                            Student
                        </option>

                        <option
                            value="president"
                            <?= $requestedRole === 'president'
                                ? 'selected' : '' ?>
                        >
                            Club President
                        </option>

                        <option
                            value="vice_president"
                            <?= $requestedRole === 'vice_president'
                                ? 'selected' : '' ?>
                        >
                            Club Vice President
                        </option>

                    </select>

                </div>

                <!-- Club Selection -->

                <div
                    class="field"
                    id="club-field"
                    style="display: <?= $requestedRole !== 'student'
                        ? 'block' : 'none' ?>;"
                >

                    <label for="club_id">
                        Club *
                    </label>

                    <select id="club_id" name="club_id">

                        <option value="">
                            Select your club
                        </option>

                        <?php foreach ($clubs as $club): ?>

                            <option
                                value="<?= (int) $club['club_id'] ?>"
                                <?= (int) $club['club_id'] === $requestedClub
                                    ? 'selected' : '' ?>
                            >
                                <?= e($club['club_name']) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                    <small>
                        Both General Clubs and Sports Clubs
                        are available for leadership requests.
                    </small>

                </div>

                <!-- Submit Button -->

                <button
                    type="submit"
                    id="submitBtn"
                    class="green-button"
                >
                    <?= $requestedRole === 'student'
                        ? 'Create account'
                        : 'Request Role' ?>
                </button>

            </form>

        <?php endif; ?>

    </section>

</main>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const roleSelect = document.getElementById('requested_role');

    if (!roleSelect) {
        return;
    }

    const clubField = document.getElementById('club-field');
    const clubSelect = document.getElementById('club_id');
    const submitBtn = document.getElementById('submitBtn');

    function updateFormState() {

        const isStudent = roleSelect.value === 'student';

        if (isStudent) {

            clubField.style.display = 'none';
            clubSelect.required = false;
            clubSelect.disabled = true;

            submitBtn.textContent = 'Create account';

        } else {

            clubField.style.display = 'block';
            clubSelect.disabled = false;
            clubSelect.required = true;

            submitBtn.textContent = 'Request Role';
        }
    }

    roleSelect.addEventListener('change', updateFormState);

    updateFormState();

});
</script>

<?php include '../includes/footer.php'; ?>

<?php
/* ===========================================================
   NSBM 360 - Registration with OTP Verification & Welcome Email
   PHP + PDO + PHPMailer + Gmail SMTP
   =========================================================== */
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

$pageCSS = '/NSBM_360/assets/css/register_style.css';
$pageTitle = 'Create account | NSBM 360';

if (!function_exists('e')) {
    function e($text) {
        return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
    }
}

// if click reset (Reset session)
if (isset($_GET['reset'])) {
    unset($_SESSION['reg_step'], $_SESSION['reg_otp'], $_SESSION['temp_reg_data'], $_SESSION['otp_expires']);
    header('Location: register.php');
    exit;
}

// Send mail through gmail SMTP using constants
function send_mail_notification(string $receiverEmail, string $subject, string $htmlBody, string $altBody): bool {
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

        // XAMPP local server SSL Bypass
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        $mail->CharSet = 'UTF-8';
        $mail->setFrom($smtpUsername, 'NSBM 360');
        $mail->addAddress($receiverEmail);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = $altBody;

        $mail->send();
        return true;
    } catch (Exception $ex) {
        error_log('NSBM360 Mail Error: ' . $mail->ErrorInfo);
        return false;
    }
}

$error = '';
$successMessage = '';
$clubs = [];

try {
    $stmt = $pdo->query("SELECT club_id, club_name FROM clubs WHERE status='active' ORDER BY club_name");
    $clubs = $stmt->fetchAll();
} catch (PDOException $ex) {
    error_log($ex->getMessage());
    $error = 'Unable to load clubs list.';
}

$firstName = trim($_POST['first_name'] ?? '');
$lastName = trim($_POST['last_name'] ?? '');
$studentId = trim($_POST['student_id'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$requestedRole = $_POST['requested_role'] ?? 'student';
$requestedClub = (int)($_POST['club_id'] ?? 0);

if (isset($pdo) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'register';

    // 1. when submit 1st form (Register Details & Send OTP)
    if ($action === 'register') {
        $password = (string)($_POST['password'] ?? '');
        $confirmation = (string)($_POST['confirm_password'] ?? '');

        if ($firstName === '' || strlen($firstName) > 100) {
            $error = 'Enter a valid first name (up to 100 characters).';
        } elseif ($lastName === '' || strlen($lastName) > 100) {
            $error = 'Enter a valid last name (up to 100 characters).';
        } elseif ($studentId === '' || strlen($studentId) > 30 || $phone === '' || strlen($phone) > 20) {
            $error = 'Enter a student ID and phone number.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150
                || !preg_match('/@students\.nsbm\.ac\.lk$/i', $email)) {
            $error = 'Invalid email! Please use your official NSBM student email ending with @students.nsbm.ac.lk.';
        } elseif (strlen($password) < 5) {
            $error = 'Password must be at least 5 characters long.';
        } elseif ($password !== $confirmation) {
            $error = 'Passwords do not match.';
        } elseif (!in_array($requestedRole, ['student', 'president', 'vice_president'], true)) {
            $error = 'Select a valid student or club leadership role.';
        } elseif ($requestedRole !== 'student' && $requestedClub < 1) {
            $error = 'Choose the club you are requesting to lead.';
        } else {
            try {
                $checkStmt = $pdo->prepare('SELECT user_id FROM users WHERE email = ? OR student_id = ? LIMIT 1');
                $checkStmt->execute([$email, $studentId]);
                if ($checkStmt->fetch()) {
                    $error = 'Email or student ID is already registered.';
                } else {
                    $otp = (string)rand(100000, 999999);

                    $_SESSION['temp_reg_data'] = [
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'student_id' => $studentId,
                        'email' => $email,
                        'phone' => $phone,
                        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                        'role' => $requestedRole,
                        'club_id' => $requestedClub
                    ];
                    $_SESSION['reg_otp'] = $otp;
                    $_SESSION['otp_expires'] = time() + 300; // 5min

                    $subject = 'Email Verification OTP - NSBM 360';
                    $htmlBody = "<h2>NSBM 360 Account Verification</h2>" .
                                "<p>Hello <b>{$firstName}</b>,</p>" .
                                "<p>Your OTP verification code is:</p>" .
                                "<h1 style='color: #008000; letter-spacing: 2px;'>{$otp}</h1>" .
                                "<p>This code is valid for 5 minutes. Do not share this code with anyone.</p>" .
                                "<br><p>NSBM 360 Team</p>";
                    $altBody = "Your NSBM 360 OTP code is: {$otp}";

                    $mailSent = send_mail_notification($email, $subject, $htmlBody, $altBody);

                    if ($mailSent) {
                        $_SESSION['reg_step'] = 'verify_otp';
                    } else {
                        $error = 'Failed to send verification email. Please check your internet connection and try again.';
                    }
                }
            } catch (PDOException $ex) {
                error_log($ex->getMessage());
                $error = 'Database error occurred.';
            }
        }
    }
    // 2. OTP  Verification
    elseif ($action === 'verify_otp') {
        $enteredOtp = trim($_POST['otp_code'] ?? '');
        $storedOtp = $_SESSION['reg_otp'] ?? '';
        $expires = $_SESSION['otp_expires'] ?? 0;
        $tempData = $_SESSION['temp_reg_data'] ?? [];

        if (empty($enteredOtp) || empty($storedOtp) || empty($tempData)) {
            $error = 'Session expired or invalid request. Please start over.';
            $_SESSION['reg_step'] = 'form';
        } elseif (time() > $expires) {
            $error = 'OTP code has expired. Please register again.';
            unset($_SESSION['reg_otp'], $_SESSION['temp_reg_data'], $_SESSION['otp_expires']);
            $_SESSION['reg_step'] = 'form';
        } elseif ($enteredOtp !== $storedOtp) {
            $error = 'Invalid OTP code. Please check and try again.';
            $_SESSION['reg_step'] = 'verify_otp';
        } else {
            try {
                $selectedClubName = '';
                if ($tempData['role'] !== 'student') {
                    $stmt = $pdo->prepare("SELECT club_name FROM clubs WHERE club_id = ? AND status='active' LIMIT 1");
                    $stmt->execute([$tempData['club_id']]);
                    $validClub = $stmt->fetch();
                    if ($validClub) {
                        $selectedClubName = $validClub['club_name'];
                    }
                }

                $pdo->beginTransaction();

                $stmt = $pdo->prepare('INSERT INTO users
                    (first_name, last_name, email, password, phone, student_id, role, email_verified)
                    VALUES (?, ?, ?, ?, ?, ?, "user", 1)');
                $stmt->execute([
                    $tempData['first_name'],
                    $tempData['last_name'],
                    $tempData['email'],
                    $tempData['password_hash'],
                    $tempData['phone'],
                    $tempData['student_id']
                ]);
                $newUserId = $pdo->lastInsertId();

                if ($tempData['role'] !== 'student') {
                    $stmt = $pdo->prepare("INSERT INTO role_requests (user_id, club_id, requested_role, request_status) VALUES (?, ?, ?, 'pending')");
                    $stmt->execute([$newUserId, $tempData['club_id'], $tempData['role']]);

                    $adminEmail = 'admin@nsbm.ac.lk';
                    $adminSub = 'New Club Leadership Approval Required - NSBM 360';
                    $adminMsg = "Hello Admin,\n\nA new club leadership request has been submitted.\n\n" .
                                "User Name: {$tempData['first_name']} {$tempData['last_name']}\n" .
                                "Student ID: {$tempData['student_id']}\n" .
                                "Email: {$tempData['email']}\n" .
                                "Requested Role: " . ucwords(str_replace('_', ' ', $tempData['role'])) . "\n" .
                                "Club: {$selectedClubName}\n\nNSBM 360 System";
                    @mail($adminEmail, $adminSub, $adminMsg, "From: no-reply@nsbm.ac.lk\r\n");
                }

                $pdo->commit();

                //send Welcome Email to user
                $welcomeSub = 'Welcome to NSBM 360!';
                $welcomeHtml = "<h2>Welcome to NSBM 360, {$tempData['first_name']}!</h2>" .
                               "<p>Your account has been successfully verified and created.</p>" .
                               "<p>You can now sign in to explore campus news, events, and community stories.</p>" .
                               "<br><p>Best regards,<br>NSBM 360 Team</p>";
                $welcomeAlt = "Welcome to NSBM 360, {$tempData['first_name']}! Your account has been created.";
                send_mail_notification($tempData['email'], $welcomeSub, $welcomeHtml, $welcomeAlt);

                unset($_SESSION['reg_otp'], $_SESSION['temp_reg_data'], $_SESSION['otp_expires'], $_SESSION['reg_step']);

                $successMessage = ($tempData['role'] === 'student') 
                    ? 'You Are Successfully Registered and Verified!' 
                    : 'Registration submitted successfully! Your account and leadership role will be activated once Admin approves.';

                $_SESSION['reg_step'] = 'success';

            } catch (PDOException $ex) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log($ex->getMessage());
                $error = 'Database error: ' . $ex->getMessage();
            }
        }
    }
}

$step = $_SESSION['reg_step'] ?? 'form';
if ($step === 'success') {
    unset($_SESSION['reg_step']); // when complete show next form
}
?>
<?php include '../includes/header.php'; ?>
<main class="auth-layout">
    <aside class="campus-card">
        <img src="../assets/image/login_page_image.jpg" alt="login page image">
        <div class="campus-card-content">
            <div class="eyebrow">ONE CAMPUS. COUNTLESS STORIES.</div>
            <h2>Stay close to your<br>campus community.</h2>
            <p>Read every story without signing in. An account helps club leaders share the moments that matter.</p>
        </div>
    </aside>
    <section class="auth-content">
        <a class="back-link" href="/NSBM_360/index.php">← Back to campus</a>
        <h1>Join your campus community.</h1>

        <?php if ($error): ?><div class="notice error" role="alert"><?= e($error) ?></div><?php endif; ?>

        <?php if ($step === 'success'): ?>
            <div class="notice success" role="alert">
                <?= e($successMessage) ?>
            </div>
            <p class="success-action-wrap"><a href="login.php" class="green-button">Proceed to Sign In →</a></p>

        <?php elseif ($step === 'verify_otp'): ?>
            <p class="lead">Enter the 6-digit verification code sent to <b><?= e($_SESSION['temp_reg_data']['email'] ?? 'your email') ?></b>.</p>
            
            <form method="post" action="register.php">
                <input type="hidden" name="action" value="verify_otp">

                <div class="field">
                    <label for="otp_code">Verification Code (OTP) *</label>
                    <input id="otp_code" name="otp_code" type="text" maxlength="6" required placeholder="123456" autocomplete="one-time-code" style="letter-spacing: 3px; font-size: 1.2rem;">
                    <small>Check your inbox (and spam folder) for the 6-digit code.</small>
                </div>

                <button type="submit" class="green-button">Verify & Complete Registration</button>
            </form>
            <p style="margin-top: 15px;"><a href="register.php?reset=1">← Start over / Change email</a></p>

        <?php else: ?>
            <p class="lead">Create your account. If you lead a club, request publishing access for your assigned community.</p>

            <form method="post" action="register.php" id="registrationForm">
                <input type="hidden" name="action" value="register">
                
                <div class="two-col">
                    <div class="field"><label for="first_name">First name *</label><input id="first_name" name="first_name" maxlength="100" required placeholder="Pasindu" value="<?= e($firstName) ?>"></div>
                    <div class="field"><label for="last_name">Last name *</label><input id="last_name" name="last_name" maxlength="100" required placeholder="Silva" value="<?= e($lastName) ?>"></div>
                </div>

                <div class="field"><label for="student_id">Student ID *</label><input id="student_id" name="student_id" maxlength="30" required placeholder="12345" value="<?= e($studentId) ?>"></div>
                <div class="field">
                    <label for="email">University email *</label>
                    <input id="email" name="email" type="email" maxlength="150" required placeholder="yourname@students.nsbm.ac.lk" value="<?= e($email) ?>">
                    <small>Must use your official NSBM student email ending with @students.nsbm.ac.lk.</small>
                </div>
                <div class="field"><label for="phone">Phone number *</label><input id="phone" name="phone" type="tel" maxlength="20" required placeholder="07XXXXXXXX" value="<?= e($phone) ?>"></div>
                
                <div class="two-col">
                    <div class="field"><label for="password">Password *</label><input id="password" name="password" type="password" minlength="5" required autocomplete="new-password"><small>Use at least 5 characters.</small></div>
                    <div class="field"><label for="confirm_password">Confirm password *</label><input id="confirm_password" name="confirm_password" type="password" minlength="5" required autocomplete="new-password"></div>
                </div>

                <div class="field">
                    <label for="requested_role">I am registering as *</label>
                    <select id="requested_role" name="requested_role" required>
                        <option value="student" <?= ($requestedRole === 'student') ? 'selected' : '' ?>>Student</option>
                        <option value="president" <?= ($requestedRole === 'president') ? 'selected' : '' ?>>Club President</option>
                        <option value="vice_president" <?= ($requestedRole === 'vice_president') ? 'selected' : '' ?>>Club Vice President</option>
                    </select>
                </div>

                <div class="field" id="club-field" style="display: <?= ($requestedRole !== 'student') ? 'block' : 'none' ?>;">
                    <label for="club_id">Club *</label>
                    <select id="club_id" name="club_id">
                        <option value="">Select your club</option>
                        <?php foreach ($clubs as $club): ?>
                            <option value="<?= (int)$club['club_id'] ?>" <?= ((int)$club['club_id'] === $requestedClub) ? 'selected' : '' ?>><?= e($club['club_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" id="submitBtn" class="green-button">
                    <?= ($requestedRole === 'student') ? 'Create account' : 'Request Role' ?>
                </button>
            </form>
        <?php endif; ?>
    </section>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const roleSelect = document.getElementById('requested_role');
    if (!roleSelect) return;
    const clubField = document.getElementById('club-field');
    const clubSelect = document.getElementById('club_id');
    const submitBtn = document.getElementById('submitBtn');

    function updateFormState() {
        if (roleSelect.value === 'student') {
            clubField.style.display = 'none';
            clubSelect.required = false;
            submitBtn.textContent = 'Create account';
        } else {
            clubField.style.display = 'block';
            clubSelect.required = true;
            submitBtn.textContent = 'Request Role';
        }
    }

    roleSelect.addEventListener('change', updateFormState);
    updateFormState();
});
</script>
<?php include '../includes/footer.php'; ?>
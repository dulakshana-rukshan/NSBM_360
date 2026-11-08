<?php
/* ===========================================================
   NSBM 360 - Student registration with separate First and Last Name (PDO Version)
   =========================================================== */
session_start();

// Include external database connection file (which provides $pdo)
require_once '../config/database.php';

// Define page specific CSS and Title for header.php
$pageCSS = '/NSBM_360/assets/css/register_style.css';
$pageTitle = 'Create account | NSBM 360';

if (!function_exists('e')) {
    function e($text) {
        return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('is_local_request')) {
    function is_local_request() {
        $remoteIsLocal = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
        $siteName = strtolower($_SERVER['SERVER_NAME'] ?? '');
        return $remoteIsLocal && in_array($siteName, ['localhost', '127.0.0.1', '::1'], true);
    }
}

$error = '';
$successMessage = '';
$clubs = [];

try {
    // Fetch active clubs using $pdo connection
    $stmt = $pdo->query("SELECT club_id, club_name FROM clubs WHERE status='active' ORDER BY club_name");
    $clubs = $stmt->fetchAll();
} catch (PDOException $ex) {
    error_log($ex->getMessage());
    $error = 'Unable to load clubs list.';
}

if (empty($_SESSION['register_csrf'])) {
    $_SESSION['register_csrf'] = bin2hex(random_bytes(24));
}

// Retrieve form values matching database schema
$firstName = trim($_POST['first_name'] ?? '');
$lastName = trim($_POST['last_name'] ?? '');
$studentId = trim($_POST['student_id'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$requestedRole = $_POST['requested_role'] ?? 'student';
$requestedClub = (int)($_POST['club_id'] ?? 0);

if (isset($pdo) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = (string)($_POST['password'] ?? '');
    $confirmation = (string)($_POST['confirm_password'] ?? '');

    if (!hash_equals($_SESSION['register_csrf'], (string)($_POST['csrf'] ?? ''))) {
        $error = 'Security check failed. Please refresh the page.';
    } elseif ($firstName === '' || strlen($firstName) > 100) {
        $error = 'Enter a valid first name (up to 100 characters).';
    } elseif ($lastName === '' || strlen($lastName) > 100) {
        $error = 'Enter a valid last name (up to 100 characters).';
    } elseif ($studentId === '' || strlen($studentId) > 30 || $phone === '' || strlen($phone) > 20) {
        $error = 'Enter a student ID (max 30 characters) and phone number (max 20 characters).';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150
            || !preg_match('/@students\.nsbm\.ac\.lk$/i', $email)) {
        $error = 'Enter your NSBM student email (ending in @students.nsbm.ac.lk).';
    } elseif (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $error = 'Password needs at least 8 characters, including a letter and a number.';
    } elseif ($password !== $confirmation) {
        $error = 'Passwords do not match.';
    } elseif (!in_array($requestedRole, ['student', 'president', 'vice_president'], true)) {
        $error = 'Select a valid student or club leadership role.';
    } elseif ($requestedRole !== 'student' && $requestedClub < 1) {
        $error = 'Choose the club you are requesting to lead.';
    } else {
        try {
            if ($requestedRole !== 'student') {
                $stmt = $pdo->prepare("SELECT club_id FROM clubs WHERE club_id = ? AND status='active' LIMIT 1");
                $stmt->execute([$requestedClub]);
                $validClub = $stmt->fetch();
                if (!$validClub) {
                    throw new RuntimeException('The selected club is not active.');
                }
            }

            $pdo->beginTransaction();
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            
            /* =========================================================
               [COMMENTED OUT] OTP Generation & Expiry (Will add later)
               ========================================================= */
            // $otpCode = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            // $otpExpires = date('Y-m-d H:i:s', time() + 900);

            // Insert into users table without otp columns
            $stmt = $pdo->prepare('INSERT INTO users
                (first_name, last_name, email, password, phone, student_id, role, email_verified)
                VALUES (?, ?, ?, ?, ?, ?, "user", 1)');
            $stmt->execute([$firstName, $lastName, $email, $passwordHash, $phone, $studentId]);
            $newUserId = $pdo->lastInsertId();

            if ($requestedRole !== 'student') {
                $stmt = $pdo->prepare("INSERT INTO role_requests (user_id, club_id, requested_role, request_status) VALUES (?, ?, ?, 'pending')");
                $stmt->execute([$newUserId, $requestedClub, $requestedRole]);
            }
            $pdo->commit();

            /* =========================================================
               [COMMENTED OUT] OTP Email & Redirect (Will add later)
               ========================================================= */
            // $subject = 'Your NSBM 360 Verification Code';
            // $message = "Hello {$firstName},\n\nYour OTP verification code for NSBM 360 is: {$otpCode}\nThis code is valid for 15 minutes.\n\nNSBM 360";
            // @mail($email, $subject, $message, 'From: no-reply@nsbm.ac.lk');
            // if (is_local_request()) {
            //     $_SESSION['test_otp'] = $otpCode;
            // }
            // header('Location: verify_otp.php?uid=' . $newUserId);
            // exit;

            $successMessage = 'You Are Successfully Registered!';

        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log($ex->getMessage());
            $isDuplicate = ($ex->getCode() == 23000 || (isset($ex->errorInfo[1]) && $ex->errorInfo[1] == 1062));
            $error = $isDuplicate ? 'Email or student ID is already registered.' : 'Unable to register. Database error: ' . $ex->getMessage();
        } catch (RuntimeException $ex) {
            $error = $ex->getMessage();
        }
    }
}
?>
<?php
    include '../includes/header.php';
?>
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
        <a class="back-link" href="index.php">← Back to campus</a>
        <h1>Join your campus community.</h1>
        <p class="lead">Create your account. If you lead a club, request publishing access for your assigned community.</p>

        <?php if ($successMessage): ?>
            <div class="notice success" role="alert">
                <?= e($successMessage) ?>
            </div>
            <p class="success-action-wrap"><a href="login.php" class="green-button">Proceed to Sign In →</a></p>
        <?php else: ?>
            <?php if ($error): ?><div class="notice error" role="alert"><?= e($error) ?></div><?php endif; ?>

            <form method="post" action="register.php">
                <input type="hidden" name="csrf" value="<?= e($_SESSION['register_csrf']) ?>">
                
                <!-- Separate First Name and Last Name fields matching database columns -->
                <div class="two-col">
                    <div class="field"><label for="first_name">First name *</label><input id="first_name" name="first_name" maxlength="100" required placeholder="Pasindu" value="<?= e($firstName) ?>"></div>
                    <div class="field"><label for="last_name">Last name *</label><input id="last_name" name="last_name" maxlength="100" required placeholder="Silva" value="<?= e($lastName) ?>"></div>
                </div>

                <div class="field"><label for="student_id">Student ID *</label><input id="student_id" name="student_id" maxlength="30" required placeholder="UGC/2024/CS/084" value="<?= e($studentId) ?>"></div>
                <div class="field"><label for="email">University email *</label><input id="email" name="email" type="email" maxlength="150" required placeholder="you@students.nsbm.ac.lk" value="<?= e($email) ?>"><small>Use your NSBM student email address to verify your identity.</small></div>
                <div class="field"><label for="phone">Phone number *</label><input id="phone" name="phone" type="tel" maxlength="20" required placeholder="07XXXXXXXX" value="<?= e($phone) ?>"></div>
                
                <div class="two-col">
                    <div class="field"><label for="password">Password *</label><input id="password" name="password" type="password" minlength="8" required autocomplete="new-password"><small>Use at least 8 characters, a letter and a number.</small></div>
                    <div class="field"><label for="confirm_password">Confirm password *</label><input id="confirm_password" name="confirm_password" type="password" minlength="8" required autocomplete="new-password"></div>
                </div>

                <div class="role-panel">
                    <div class="panel-heading"><h3>Your role on campus</h3><span>OPTIONAL REQUEST</span></div>
                    <div class="field"><label for="requested_role">Requested role</label>
                        <select name="requested_role" id="requested_role">
                            <option value="student" <?= $requestedRole==='student'?'selected':'' ?>>Student / Reader</option>
                            <option value="president" <?= $requestedRole==='president'?'selected':'' ?>>Club President</option>
                            <option value="vice_president" <?= $requestedRole==='vice_president'?'selected':'' ?>>Club Vice President</option>
                        </select>
                    </div>
                    <div class="field" id="club-field"><label for="club_id">Requested club *</label>
                        <select name="club_id" id="club_id"><option value="">Select a club</option>
                            <?php foreach ($clubs as $club): ?><option value="<?= (int)$club['club_id'] ?>" <?= $requestedClub===(int)$club['club_id']?'selected':'' ?>><?= e($club['club_name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="notice success status">
                        <strong>◇ &nbsp;Role status: pending until approved</strong><br>
                        Your account starts as a Student / Reader. An approved request lets you manage only your assigned club's content.
                    </div>
                </div>
                <button class="green-button" type="submit">Create account &amp; request role</button>
            </form>
            <p class="secondary-line">Already have an account? <a href="login.php">Sign in</a></p>
        <?php endif; ?>
    </section>
</main>
<script>
(function () {
    var role = document.getElementById('requested_role');
    var box = document.getElementById('club-field');
    var club = document.getElementById('club_id');
    function update() { var leader = role.value !== 'student'; box.hidden = !leader; club.required = leader; if (!leader) club.value = ''; }
    role.addEventListener('change', update);
    update();
}());
</script>
</body>
<?php
    include '../includes/footer.php';
?>
</html>
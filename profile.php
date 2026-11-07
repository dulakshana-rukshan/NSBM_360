<?php
// Start the session and check whether the user is logged in.
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Connect to your NSBM 360 database (change credentials if needed).
$conn = new mysqli('localhost', 'root', '', 'nsbm_360');
if ($conn->connect_error) {
    die('Database connection failed.');
}
$conn->set_charset('utf8mb4');

// Escape text before displaying it in HTML.
function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$userId = (int)$_SESSION['user_id'];
$error = '';
$success = $_SESSION['profile_success'] ?? '';
unset($_SESSION['profile_success']);

// Create a security token for all profile forms.
if (empty($_SESSION['profile_token'])) {
    $_SESSION['profile_token'] = bin2hex(random_bytes(32));
}

// Get the current user's details from the users table.
$stmt = $conn->prepare('SELECT user_id, first_name, last_name, email, student_id, phone, password, profile_image, email_verified, created_at FROM users WHERE user_id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    // A session cannot access a user that no longer exists.
    session_unset();
    header('Location: login.php');
    exit;
}

// Process the three forms: personal details, password, and photo.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $token = $_POST['profile_token'] ?? '';

    if (!hash_equals($_SESSION['profile_token'], $token)) {
        $error = 'Invalid request. Please refresh the page and try again.';
    } elseif ($action === 'save_profile') {
        // Update fields that can be edited in the users table.
        // The design uses one Full name input; the users table has two name columns.
        // Keep the first word as first_name, and the rest as last_name.
        $fullNameInput = trim(preg_replace('/\s+/', ' ', $_POST['full_name'] ?? ''));
        $nameParts = explode(' ', $fullNameInput, 2);
        $firstName = $nameParts[0] ?? '';
        $lastName = $nameParts[1] ?? '';
        $phone = trim($_POST['phone'] ?? '');

        if ($firstName === '' || $lastName === '') {
            $error = 'Please enter both your first and last name.';
        } elseif (strlen($firstName) > 100 || strlen($lastName) > 100 || strlen($phone) > 20) {
            $error = 'One of the fields is too long.';
        } else {
            $stmt = $conn->prepare('UPDATE users SET first_name = ?, last_name = ?, phone = ? WHERE user_id = ?');
            $stmt->bind_param('sssi', $firstName, $lastName, $phone, $userId);
            if ($stmt->execute()) {
                $_SESSION['profile_success'] = 'Personal information updated successfully.';
                header('Location: profile.php');
                exit;
            }
            $error = 'Could not update personal information.';
            $stmt->close();
        }
    } elseif ($action === 'change_password') {
        // Verify the old password, then save a secure hash of the new one.
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (!password_verify($currentPassword, $user['password'])) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($newPassword) < 8) {
            $error = 'New password must contain at least 8 characters.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'New passwords do not match.';
        } else {
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $conn->prepare('UPDATE users SET password = ? WHERE user_id = ?');
            $stmt->bind_param('si', $newHash, $userId);
            if ($stmt->execute()) {
                $_SESSION['profile_success'] = 'Password updated successfully.';
                header('Location: profile.php');
                exit;
            }
            $error = 'Could not update your password.';
            $stmt->close();
        }
    } elseif ($action === 'change_photo') {
        // Accept small JPEG, PNG or WebP profile pictures only.
        if (!isset($_FILES['profile_image']) || $_FILES['profile_image']['error'] !== UPLOAD_ERR_OK) {
            $error = 'Please select an image to upload.';
        } elseif ($_FILES['profile_image']['size'] > 2 * 1024 * 1024) {
            $error = 'The image must be smaller than 2 MB.';
        } else {
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['profile_image']['tmp_name']);
            $types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

            if (!isset($types[$mime])) {
                $error = 'Only JPG, PNG and WebP images are allowed.';
            } else {
                $folder = __DIR__ . '/uploads/profile_photos/';
                if (!is_dir($folder) && !mkdir($folder, 0755, true)) {
                    $error = 'Could not create the photo folder.';
                } else {
                    $fileName = bin2hex(random_bytes(12)) . '.' . $types[$mime];
                    $filePath = $folder . $fileName;
                    $dbPath = 'uploads/profile_photos/' . $fileName;

                    if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $filePath)) {
                        $stmt = $conn->prepare('UPDATE users SET profile_image = ? WHERE user_id = ?');
                        $stmt->bind_param('si', $dbPath, $userId);
                        if ($stmt->execute()) {
                            $_SESSION['profile_success'] = 'Profile photo updated successfully.';
                            header('Location: profile.php');
                            exit;
                        }
                        unlink($filePath);
                        $stmt->close();
                    }
                    $error = 'Could not save the photo.';
                }
            }
        }
    } else {
        $error = 'Unknown form action.';
    }
}

// Load the clubs this user belongs to and their assigned club role.
$stmt = $conn->prepare('SELECT c.club_name, cm.club_role, cm.membership_status, cm.joined_at FROM club_memberships cm JOIN clubs c ON c.club_id = cm.club_id WHERE cm.user_id = ? ORDER BY cm.joined_at DESC');
$stmt->bind_param('i', $userId);
$stmt->execute();
$memberships = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Count requests awaiting an administrator's decision.
$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM role_requests WHERE user_id = ? AND request_status = 'pending'");
$stmt->bind_param('i', $userId);
$stmt->execute();
$pendingRequests = (int)$stmt->get_result()->fetch_assoc()['total'];
$stmt->close();
$conn->close();

// Prepare display values from existing database columns.
$fullName = trim($user['first_name'] . ' ' . $user['last_name']);
$initials = strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1));
$memberSince = date('d F Y', strtotime($user['created_at']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My profile | NSBM 360</title>
    <link rel="stylesheet" href="assets\css\profile-style.css">
    <link rel="stylesheet" href="\NSBM_360\assets\css\style.css">

</head>
<body>
<?php
    include 'includes/header.php';
?>
<?php
    include 'includes/navbar.php';
?>
<main class="profile-page">
    <!-- Breadcrumb and page heading, without a shared website header or footer. -->
    <nav class="breadcrumb" aria-label="Breadcrumb"><a href="index.php">Home</a><span>/</span>Account<span>/</span>Profile</nav>
    <header class="page-heading">
        <h1>My profile</h1>
        <p>Keep your account details up to date and review your campus role.</p>
    </header>

    <!-- Success and error notifications after user actions. -->
    <?php if ($success): ?>
        <div class="message success" role="status"><?= e($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="message error" role="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="profile-layout">
        <!-- LEFT: user account details and the user's club assignment(s). -->
        <aside class="left-column">
            <section class="card account-card">
                <h2>Your account</h2>
                <div class="avatar">
                    <?php if (!empty($user['profile_image'])): ?>
                        <img src="<?= e($user['profile_image']) ?>" alt="Your profile photo">
                    <?php else: ?>
                        <span><?= e($initials) ?></span>
                    <?php endif; ?>
                </div>
                <h3><?= e($fullName) ?></h3>
                <p class="student-id"><?= e($user['student_id']) ?></p>
                <?php if ((int)$user['email_verified'] === 1): ?>
                    <span class="tag verified">Email verified</span>
                <?php else: ?>
                    <span class="tag unverified">Email not verified</span>
                <?php endif; ?>
                <p class="member-date">Member since <?= e($memberSince) ?></p>

                <!-- Image upload: saves the file path in users.profile_image. -->
                <form class="photo-form" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="change_photo">
                    <input type="hidden" name="profile_token" value="<?= e($_SESSION['profile_token']) ?>">
                    <label for="profile-photo" class="button button-outline">Change profile photo</label>
                    <input type="file" id="profile-photo" name="profile_image"
                           accept="image/jpeg,image/png,image/webp" onchange="this.form.submit()" required>
                    <p class="help-text">JPG, PNG or WebP · Maximum 2 MB</p>
                </form>
            </section>

            <!-- Club name and role are read from club_memberships + clubs. -->
            <section class="card club-card">
                <h2>Role &amp; club assignment</h2>
                <?php if ($memberships): ?>
                    <?php foreach ($memberships as $membership): ?>
                        <div class="club-entry">
                            <span class="tag <?= $membership['membership_status'] === 'active' ? 'verified' : 'unverified' ?>">
                                <?= e(strtoupper($membership['membership_status'] . ' · ' . str_replace('_', ' ', $membership['club_role']))) ?>
                            </span>
                            <h3><?= e($membership['club_name']) ?></h3>
                            <p>Club role: <?= e(ucwords(str_replace('_', ' ', $membership['club_role']))) ?></p>
                            <p class="club-date">Joined <?= e(date('d F Y', strtotime($membership['joined_at']))) ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="no-club">No club assignment found.</p>
                <?php endif; ?>
                <p class="request-count">Pending role requests: <?= (int)$pendingRequests ?></p>
            </section>
        </aside>

        <!-- RIGHT: editable user fields and password form. -->
        <div class="right-column">
            <section class="card form-card personal-card">
                <h2>Personal information</h2>
                <p class="section-description">Your name appears on posts you publish. Required fields are marked *.</p>
                <form method="post" action="profile.php">
                    <input type="hidden" name="action" value="save_profile">
                    <input type="hidden" name="profile_token" value="<?= e($_SESSION['profile_token']) ?>">
                    <div class="form-grid">
                        <!-- Full name is saved as users.first_name and users.last_name. -->
                        <div class="field">
                            <label for="full_name">Full name *</label>
                            <input type="text" name="full_name" id="full_name" maxlength="201"
                                   autocomplete="name" value="<?= e(($_POST['action'] ?? '') === 'save_profile' ? ($_POST['full_name'] ?? '') : $fullName) ?>" required>
                        </div>
                        <!-- Student ID and email stay read-only: this page has no ID/email verification flow. -->
                        <div class="field">
                            <label for="student_id">Student ID *</label>
                            <input type="text" id="student_id" value="<?= e($user['student_id']) ?>" readonly>
                        </div>
                        <div class="field full-width">
                            <label for="email">University email *</label>
                            <input type="email" id="email" value="<?= e($user['email']) ?>" readonly>
                            <p class="help-text">Changing your email requires verification.</p>
                        </div>
                        <!-- Faculty is not in the uploaded database, so the UI omits it. -->
                        <div class="field">
                            <label for="phone">Contact number (optional)</label>
                            <input type="tel" id="phone" name="phone" maxlength="20"
                                   value="<?= e(($_POST['action'] ?? '') === 'save_profile' ? ($_POST['phone'] ?? '') : $user['phone']) ?>"
                                   placeholder="Contact number" autocomplete="tel">
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="button button-green">Save changes</button>
                        <button type="reset" class="button button-outline">Cancel</button>
                    </div>
                </form>
            </section>

            <!-- Password update: confirms the current password using password_verify(). -->
            <section class="card form-card password-card">
                <h2>Change password</h2>
                <p class="section-description">Choose a strong password you don't use elsewhere.</p>
                <form method="post" action="profile.php">
                    <input type="hidden" name="action" value="change_password">
                    <input type="hidden" name="profile_token" value="<?= e($_SESSION['profile_token']) ?>">
                    <div class="form-grid">
                        <div class="field full-width">
                            <label for="current_password">Current password *</label>
                            <input type="password" id="current_password" name="current_password"
                                   autocomplete="current-password" required>
                        </div>
                        <div class="field">
                            <label for="new_password">New password *</label>
                            <input type="password" id="new_password" name="new_password"
                                   minlength="8" autocomplete="new-password" placeholder="Enter new password" required>
                        </div>
                        <div class="field">
                            <label for="confirm_password">Confirm new password *</label>
                            <input type="password" id="confirm_password" name="confirm_password"
                                   minlength="8" autocomplete="new-password" placeholder="Re-enter new password" required>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="button button-outline">Update password</button>
                    </div>
                </form>
            </section>

            <!-- Admin information: no invented link or approval date. -->
            <div class="role-notice">
                <svg class="notice-icon" viewBox="0 0 24 24" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 2.5 20 5.5V11c0 5.1-3 8.8-8 10.7C7 19.8 4 16.1 4 11V5.5L12 2.5Z" fill="none" stroke="currentColor" stroke-width="1.8"/>
                    <path d="m8.5 12 2.5 2.5 4.5-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <div>
                    <strong>Need to change your role or club?</strong>
                    <p>Contact an administrator with your committee appointment details. Your existing club access stays unchanged until a new assignment is approved.</p>
                </div>
            </div>
        </div>
    </div>
</main>
</body>
<?php
    include 'includes/footer.php';
?>
</html>

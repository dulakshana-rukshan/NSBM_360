
<?php

$pageCSS = '/NSBM_360/assets/css/profile-style.css';
$pageTitle = 'My profile | NSBM 360';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: /NSBM_360/auth/login.php');
    exit;
}

// Shared database connection (PDO).
require_once __DIR__ . '/config/database.php';

// Assumes config/database.php provides $pdo.
if (!isset($pdo) || !($pdo instanceof PDO)) {
    die('Database connection is not configured correctly.');
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function profileImageUrl($path) {
    if (empty($path)) {
        return null;
    }

    $path = str_replace('\\', '/', trim($path));

    if (str_starts_with($path, '/NSBM_360/')) {
        return $path;
    }

    return '/NSBM_360/' . ltrim($path, '/');
}

$userId = (int)$_SESSION['user_id'];
$error = '';
$success = $_SESSION['profile_success'] ?? '';
unset($_SESSION['profile_success']);

if (empty($_SESSION['profile_token'])) {
    $_SESSION['profile_token'] = bin2hex(random_bytes(32));
}

// Get current user.
$stmt = $pdo->prepare(
    'SELECT user_id, first_name, last_name, email,
            student_id, phone, password, profile_image,
            email_verified, created_at
     FROM users
     WHERE user_id = ?'
);
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_unset();
    header('Location: /NSBM_360/auth/login.php');
    exit;
}

// Keep header image synchronized with database.
$_SESSION['profile_image'] = profileImageUrl($user['profile_image']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';
    $token = $_POST['profile_token'] ?? '';

    if (!is_string($token) ||
        !hash_equals($_SESSION['profile_token'], $token)) {

        $error = 'Invalid request. Please refresh and try again.';

    } elseif ($action === 'save_profile') {

        $fullNameInput = trim(
            preg_replace('/\s+/', ' ', $_POST['full_name'] ?? '')
        );

        $nameParts = explode(' ', $fullNameInput, 2);
        $firstName = $nameParts[0] ?? '';
        $lastName = $nameParts[1] ?? '';
        $phone = trim($_POST['phone'] ?? '');

        if ($firstName === '' || $lastName === '') {
            $error = 'Please enter both first and last name.';

        } elseif (
            mb_strlen($firstName) > 100 ||
            mb_strlen($lastName) > 100 ||
            mb_strlen($phone) > 20
        ) {
            $error = 'One of the fields is too long.';

        } else {
            $stmt = $pdo->prepare(
                'UPDATE users
                 SET first_name = ?, last_name = ?, phone = ?
                 WHERE user_id = ?'
            );

            if ($stmt->execute([
                $firstName, $lastName, $phone, $userId
            ])) {
                $_SESSION['first_name'] = $firstName;
                $_SESSION['profile_success'] =
                    'Personal information updated successfully.';

                header('Location: /NSBM_360/profile.php');
                exit;
            }

            $error = 'Could not update personal information.';
        }

    } elseif ($action === 'change_password') {

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

            $stmt = $pdo->prepare(
                'UPDATE users SET password = ? WHERE user_id = ?'
            );

            if ($stmt->execute([$newHash, $userId])) {
                $_SESSION['profile_success'] =
                    'Password updated successfully.';

                header('Location: /NSBM_360/profile.php');
                exit;
            }

            $error = 'Could not update your password.';
        }

    } elseif ($action === 'change_photo') {

        if (
            !isset($_FILES['profile_image']) ||
            $_FILES['profile_image']['error'] !== UPLOAD_ERR_OK
        ) {
            $error = 'Please select an image to upload.';

        } elseif ($_FILES['profile_image']['size'] > 2 * 1024 * 1024) {
            $error = 'The image must be smaller than 2 MB.';

        } else {

            $tempFile = $_FILES['profile_image']['tmp_name'];

            if (!is_uploaded_file($tempFile)) {
                $error = 'Invalid uploaded file.';

            } else {
                $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tempFile);

                $types = [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/webp' => 'webp'
                ];

                if (!isset($types[$mime]) ||
                    @getimagesize($tempFile) === false) {

                    $error = 'Only valid JPG, PNG and WebP images are allowed.';

                } else {

                    $folder = __DIR__ . '/uploads/profile_photos/';

                    if (!is_dir($folder) &&
                        !mkdir($folder, 0755, true)) {

                        $error = 'Could not create the photo folder.';

                    } else {

                        $fileName = bin2hex(random_bytes(16))
                            . '.' . $types[$mime];

                        $filePath = $folder . $fileName;
                        $dbPath = 'uploads/profile_photos/' . $fileName;

                        if (move_uploaded_file($tempFile, $filePath)) {

                            try {
                                $stmt = $pdo->prepare(
                                    'UPDATE users
                                     SET profile_image = ?
                                     WHERE user_id = ?'
                                );

                                $saved = $stmt->execute([
                                    $dbPath, $userId
                                ]);

                                if ($saved) {
                                    $_SESSION['profile_image'] =
                                        profileImageUrl($dbPath);

                                    $_SESSION['profile_success'] =
                                        'Profile photo updated successfully.';

                                    header('Location: /NSBM_360/profile.php');
                                    exit;
                                }

                                $error = 'Could not save the photo.';

                            } catch (PDOException $ex) {
                                error_log($ex->getMessage());
                                $error = 'Could not save the photo.';
                            }

                            // Remove file if database update failed.
                            if (is_file($filePath)) {
                                unlink($filePath);
                            }

                        } else {
                            $error = 'Could not upload the photo.';
                        }
                    }
                }
            }
        }

    } else {
        $error = 'Unknown form action.';
    }
}

// Club memberships.
$stmt = $pdo->prepare(
    'SELECT c.club_name, cm.club_role,
            cm.membership_status, cm.joined_at
     FROM club_memberships cm
     JOIN clubs c ON c.club_id = cm.club_id
     WHERE cm.user_id = ?
     ORDER BY cm.joined_at DESC'
);
$stmt->execute([$userId]);
$memberships = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Pending role requests.
$stmt = $pdo->prepare(
    "SELECT COUNT(*) AS total
     FROM role_requests
     WHERE user_id = ?
       AND request_status = 'pending'"
);
$stmt->execute([$userId]);
$pendingRequests = (int)$stmt->fetchColumn();

// Display values.
$fullName = trim($user['first_name'] . ' ' . $user['last_name']);

$initials = strtoupper(
    substr($user['first_name'], 0, 1) .
    substr($user['last_name'], 0, 1)
);

$memberSince = date('d F Y', strtotime($user['created_at']));
$profilePhotoUrl = profileImageUrl($user['profile_image']);

// Shared header.
include __DIR__ . '/includes/header.php';
?>

<main class="profile-page">

    <nav class="breadcrumb" aria-label="Breadcrumb">
        <a href="/NSBM_360/index.php">Home</a>
        <span>/</span>
        Account
        <span>/</span>
        Profile
    </nav>

    <header class="page-heading">
        <h1>My profile</h1>
        <p>
            Keep your account details up to date
            and review your campus role.
        </p>
    </header>

    <?php if ($success): ?>
        <div class="message success" role="status">
            <?= e($success) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="message error" role="alert">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <div class="profile-layout">

        <aside class="left-column">

            <section class="card account-card">

                <h2>Your account</h2>

                <div class="avatar">
                    <?php if ($profilePhotoUrl): ?>

                        <img
                            src="<?= e($profilePhotoUrl) ?>"
                            alt="Your profile photo"
                        >

                    <?php else: ?>

                        <span><?= e($initials) ?></span>

                    <?php endif; ?>
                </div>

                <h3><?= e($fullName) ?></h3>

                <p class="student-id">
                    <?= e($user['student_id']) ?>
                </p>

                <?php if ((int)$user['email_verified'] === 1): ?>
                    <span class="tag verified">
                        Email verified
                    </span>
                <?php else: ?>
                    <span class="tag unverified">
                        Email not verified
                    </span>
                <?php endif; ?>

                <p class="member-date">
                    Member since <?= e($memberSince) ?>
                </p>

                <form
                    class="photo-form"
                    method="post"
                    enctype="multipart/form-data"
                >

                    <input
                        type="hidden"
                        name="action"
                        value="change_photo"
                    >

                    <input
                        type="hidden"
                        name="profile_token"
                        value="<?= e($_SESSION['profile_token']) ?>"
                    >

                    <label
                        for="profile-photo"
                        class="button button-outline"
                    >
                        Change profile photo
                    </label>

                    <input
                        type="file"
                        id="profile-photo"
                        name="profile_image"
                        accept="image/jpeg,image/png,image/webp"
                        onchange="this.form.submit()"
                        required
                    >

                    <p class="help-text">
                        JPG, PNG or WebP · Maximum 2 MB
                    </p>

                </form>

            </section>

            <section class="card club-card">

                <h2>Role &amp; club assignment</h2>

                <?php if ($memberships): ?>

                    <?php foreach ($memberships as $membership): ?>

                        <div class="club-entry">

                            <span class="tag <?= $membership['membership_status'] === 'active' ? 'verified' : 'unverified' ?>">
                                <?= e(strtoupper(
                                    $membership['membership_status'] .
                                    ' · ' .
                                    str_replace('_', ' ', $membership['club_role'])
                                )) ?>
                            </span>

                            <h3>
                                <?= e($membership['club_name']) ?>
                            </h3>

                            <p>
                                Club role:
                                <?= e(ucwords(str_replace(
                                    '_', ' ', $membership['club_role']
                                ))) ?>
                            </p>

                            <p class="club-date">
                                Joined
                                <?= e(date(
                                    'd F Y',
                                    strtotime($membership['joined_at'])
                                )) ?>
                            </p>

                        </div>

                    <?php endforeach; ?>

                <?php else: ?>

                    <p class="no-club">
                        No club assignment found.
                    </p>

                <?php endif; ?>

                <p class="request-count">
                    Pending role requests:
                    <?= (int)$pendingRequests ?>
                </p>

            </section>

        </aside>

        <div class="right-column">

            <section class="card form-card personal-card">

                <h2>Personal information</h2>

                <p class="section-description">
                    Your name appears on posts you publish.
                    Required fields are marked *.
                </p>

                <form method="post" action="/NSBM_360/profile.php">

                    <input
                        type="hidden"
                        name="action"
                        value="save_profile"
                    >

                    <input
                        type="hidden"
                        name="profile_token"
                        value="<?= e($_SESSION['profile_token']) ?>"
                    >

                    <div class="form-grid">

                        <div class="field">
                            <label for="full_name">
                                Full name *
                            </label>

                            <input
                                type="text"
                                name="full_name"
                                id="full_name"
                                maxlength="201"
                                autocomplete="name"
                                value="<?= e(
                                    ($_POST['action'] ?? '') === 'save_profile'
                                        ? ($_POST['full_name'] ?? '')
                                        : $fullName
                                ) ?>"
                                required
                            >
                        </div>

                        <div class="field">
                            <label for="student_id">
                                Student ID *
                            </label>

                            <input
                                type="text"
                                id="student_id"
                                value="<?= e($user['student_id']) ?>"
                                readonly
                            >
                        </div>

                        <div class="field full-width">
                            <label for="email">
                                University email *
                            </label>

                            <input
                                type="email"
                                id="email"
                                value="<?= e($user['email']) ?>"
                                readonly
                            >

                            <p class="help-text">
                                Changing your email requires verification.
                            </p>
                        </div>

                        <div class="field">
                            <label for="phone">
                                Contact number (optional)
                            </label>

                            <input
                                type="tel"
                                id="phone"
                                name="phone"
                                maxlength="20"
                                value="<?= e(
                                    ($_POST['action'] ?? '') === 'save_profile'
                                        ? ($_POST['phone'] ?? '')
                                        : $user['phone']
                                ) ?>"
                                placeholder="Contact number"
                                autocomplete="tel"
                            >
                        </div>

                    </div>

                    <div class="form-actions">

                        <button
                            type="submit"
                            class="button button-green"
                        >
                            Save changes
                        </button>

                        <button
                            type="reset"
                            class="button button-outline"
                        >
                            Cancel
                        </button>

                    </div>

                </form>

            </section>

            <section class="card form-card password-card">

                <h2>Change password</h2>

                <p class="section-description">
                    Choose a strong password you don't use elsewhere.
                </p>

                <form method="post" action="/NSBM_360/profile.php">

                    <input
                        type="hidden"
                        name="action"
                        value="change_password"
                    >

                    <input
                        type="hidden"
                        name="profile_token"
                        value="<?= e($_SESSION['profile_token']) ?>"
                    >

                    <div class="form-grid">

                        <div class="field full-width">

                            <label for="current_password">
                                Current password *
                            </label>

                            <input
                                type="password"
                                id="current_password"
                                name="current_password"
                                autocomplete="current-password"
                                required
                            >

                        </div>

                        <div class="field">

                            <label for="new_password">
                                New password *
                            </label>

                            <input
                                type="password"
                                id="new_password"
                                name="new_password"
                                minlength="8"
                                autocomplete="new-password"
                                placeholder="Enter new password"
                                required
                            >

                        </div>

                        <div class="field">

                            <label for="confirm_password">
                                Confirm new password *
                            </label>

                            <input
                                type="password"
                                id="confirm_password"
                                name="confirm_password"
                                minlength="8"
                                autocomplete="new-password"
                                placeholder="Re-enter new password"
                                required
                            >

                        </div>

                    </div>

                    <div class="form-actions">
                        <button
                            type="submit"
                            class="button button-outline"
                        >
                            Update password
                        </button>
                    </div>

                </form>

            </section>

            <div class="role-notice">

                <svg
                    class="notice-icon"
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                    xmlns="http://www.w3.org/2000/svg"
                >
                    <path
                        d="M12 2.5 20 5.5V11c0 5.1-3 8.8-8 10.7C7 19.8 4 16.1 4 11V5.5L12 2.5Z"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                    />
                    <path
                        d="m8.5 12 2.5 2.5 4.5-5"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    />
                </svg>

                <div>
                    <strong>
                        Need to change your role or club?
                    </strong>

                    <p>
                        Contact an administrator with your committee
                        appointment details. Your existing club access
                        stays unchanged until a new assignment is approved.
                    </p>
                </div>

            </div>

        </div>

    </div>

</main>

<?php
include __DIR__ . '/includes/footer.php';
?>

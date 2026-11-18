<?php
/**
 * NSBM 360 - Users & Role Requests Administration
 * Manages platform user accounts, verification status, and club leadership role requests.
 */

// Include shared database connection (provides $pdo)
require_once __DIR__ . '/../config/database.php';

// Initialize session
session_start();

// Verify that an administrator is logged in
$adminId = (int) ($_SESSION['user_id'] ?? 0);
if ($adminId < 1) {
    http_response_code(401);
    include __DIR__ . '/../admin/admin_access_denied.php';
    exit;
}

// ==========================================================================
// PDO & Utility Helper Functions
// ==========================================================================

/**
 * Runs a prepared SELECT query and returns all matching rows.
 */
function rows(PDO $pdo, string $sql, array $values = []): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($values);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Runs a prepared INSERT, UPDATE, or DELETE query.
 */
function run_sql(PDO $pdo, string $sql, array $values = []): void
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($values);
}

/**
 * Runs a COUNT query and returns an integer result.
 */
function count_sql(PDO $pdo, string $sql, array $values = []): int
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($values);
    return (int) ($stmt->fetchColumn() ?? 0);
}

/**
 * Safely escapes output for HTML rendering to prevent XSS.
 */
function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Formats a database timestamp into a readable date string.
 */
function display_date(?string $date): string
{
    return $date ? date('d M Y', strtotime($date)) : 'Not set';
}

/**
 * Redirects to a target page with optional query parameters and status message.
 */
function goto_page(string $file, string $message = '', array $extra = []): void
{
    if ($message !== '') {
        $extra['ok'] = $message;
    }
    $queryString = $extra ? '?' . http_build_query($extra) : '';
    header('Location: ' . $file . $queryString);
    exit;
}

// Confirm current user holds the 'admin' role
$adminRecord = rows($pdo, 'SELECT first_name, last_name, role FROM users WHERE user_id = ?', [$adminId]);
if (!$adminRecord || $adminRecord[0]['role'] !== 'admin') {
    http_response_code(403);
    include __DIR__ . '/../admin_access_denied.php';
    exit;
}
$adminName = $adminRecord[0]['first_name'];

// ==========================================================================
// CSRF Protection & Logout Handling
// ==========================================================================

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(24));
}
$csrf = $_SESSION['admin_csrf'];
$error = '';
$postAllowed = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string) ($_POST['csrf'] ?? '');
    if (!hash_equals($csrf, $token)) {
        $postAllowed = false;
        $error = 'Security check failed. Refresh the page and try again.';
    } elseif (($_POST['action'] ?? '') === 'logout') {
        session_destroy();
        header('Location: login.php');
        exit;
    }
}

// ==========================================================================
// Layout & Pagination Helper Functions
// ==========================================================================

/**
 * Renders numbered pagination links while preserving active search filters.
 */
function pagination(int $page, int $total, int $perPage): void
{
    $pages = max(1, (int) ceil($total / $perPage));
    if ($pages <= 1) {
        return;
    }

    $params = $_GET;
    echo '<nav class="pagination" aria-label="Table pages">';
    for ($p = max(1, $page - 2); $p <= min($pages, $page + 2); $p++) {
        $params['page'] = $p;
        $link = '?' . http_build_query($params);
        $activeClass = ($p === $page) ? 'current' : '';
        echo '<a class="' . $activeClass . '" href="' . e($link) . '">' . $p . '</a>';
    }
    echo '</nav>';
}

/**
 * Returns the FontAwesome icon HTML corresponding to a sidebar navigation slug.
 */
function get_nav_icon(string $slug): string
{
    return match ($slug) {
        'overview'      => '<i class="fa-solid fa-chart-pie"></i>',
        'users'         => '<i class="fa-solid fa-users-gear"></i>',
        'clubs'         => '<i class="fa-solid fa-people-group"></i>',
        'news'          => '<i class="fa-solid fa-newspaper"></i>',
        'announcements' => '<i class="fa-solid fa-bullhorn"></i>',
        default         => '<i class="fa-solid fa-circle"></i>'
    };
}

/**
 * Outputs the top HTML head, site navbar, sidebar, and page header.
 */
function admin_header(string $title, string $subtitle, string $active): void
{
    global $csrf;
    $nav = [
        'admin_overview.php'            => ['Overview', 'overview'],
        'admin_users_role_requests.php' => ['Users & role requests', 'users'],
        'admin_clubs.php'               => ['Clubs & leadership', 'clubs'],
        'admin_officialnews.php'        => ['Official news', 'news'],
        'admin_announcements.php'       => ['Announcements', 'announcements'],
    ];
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> | NSBM 360 Admin</title>
    <link rel="stylesheet" href="../assets/css/admin_users_role_requests.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<?php
// Include shared site header and navigation bar
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<div class="app-shell">
    <!-- SIDEBAR NAVIGATION -->
    <aside class="sidebar">
        <div class="side-heading">
            <small>PLATFORM ADMINISTRATION</small>
            <strong>NSBM 360</strong>
            <span>ADMINISTRATOR</span>
        </div>

        <nav class="side-nav" aria-label="Administration">
            <?php foreach ($nav as $file => $item): ?>
                <a class="<?= $active === $item[1] ? 'active' : '' ?>" href="<?= e($file) ?>">
                    <?= get_nav_icon($item[1]) ?> <?= e($item[0]) ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="sidebar-bottom">
            <p>Manage platform accounts, club assignments and official campus content.</p>
            <a href="../index.php"><i class="fa-solid fa-arrow-up-right-from-square"></i> View public site</a>
            <form method="post" action="/NSBM_360/auth/logout.php">
                <button type="submit" class="signout" name="action" value="logout">
                    <i class="fa-solid fa-right-from-bracket"></i> Sign out
                </button>
            </form>
        </div>
    </aside>

    <!-- MAIN CONTENT CONTAINER -->
    <main class="main-content">
        <div class="breadcrumbs">Home / Administration / <?= e($title) ?></div>
        <div class="page-heading">
            <div>
                <h1><?= e($title) ?></h1>
                <p><?= e($subtitle) ?></p>
            </div>
        </div>
<?php
}

/**
 * Closes the main content container and outputs the site footer.
 */
function admin_footer(): void
{
    ?>
    </main>
</div>

<footer class="site-footer">
    <?php include __DIR__ . '/../includes/footer.php'; ?>
</footer>

</body>
</html>
<?php
}

// ==========================================================================
// POST Action Handlers (Create User, Edit User, Delete User, Role Requests)
// ==========================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $postAllowed) {
    try {
        $action = $_POST['action'] ?? '';

        // Create or Update User Account
        if ($action === 'add_user' || $action === 'edit_user') {
            $userId    = (int) ($_POST['user_id'] ?? 0);
            $first     = trim($_POST['first_name'] ?? '');
            $last      = trim($_POST['last_name'] ?? '');
            $email     = trim($_POST['email'] ?? '');
            $phone     = trim($_POST['phone'] ?? '');
            $studentId = trim($_POST['student_id'] ?? '');
            $password  = (string) ($_POST['password'] ?? '');
            $role      = $_POST['role'] ?? 'user';
            $verified  = isset($_POST['email_verified']) ? 1 : 0;

            if (!$first || !$last || !$phone || !$studentId || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Enter the user name, valid email, phone and student ID.');
            }
            if (!in_array($role, ['user', 'admin'], true)) {
                throw new Exception('Invalid account role.');
            }

            if ($action === 'add_user') {
                if (strlen($password) < 8) {
                    throw new Exception('Use a password of at least 8 characters.');
                }
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                run_sql(
                    $pdo,
                    'INSERT INTO users (first_name, last_name, email, password, phone, role, email_verified, student_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                    [$first, $last, $email, $passwordHash, $phone, $role, $verified, $studentId]
                );
                goto_page('admin_users_role_requests.php', 'User account created.');
            } else {
                if ($userId < 1) {
                    throw new Exception('Select a user to edit.');
                }
                if ($password !== '' && strlen($password) < 8) {
                    throw new Exception('Use a password of at least 8 characters.');
                }
                if ($userId === $adminId && $role !== 'admin') {
                    throw new Exception('You cannot remove your own administrator role.');
                }

                run_sql(
                    $pdo,
                    'UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, student_id = ?, role = ?, email_verified = ? WHERE user_id = ?',
                    [$first, $last, $email, $phone, $studentId, $role, $verified, $userId]
                );

                // Clear pending verification tokens if marked verified
                if ($verified) {
                    run_sql(
                        $pdo,
                        'UPDATE users SET email_verification_token_hash = NULL, email_verification_expires_at = NULL WHERE user_id = ?',
                        [$userId]
                    );
                }

                // Update password only if a new one was provided
                if ($password !== '') {
                    run_sql(
                        $pdo,
                        'UPDATE users SET password = ? WHERE user_id = ?',
                        [password_hash($password, PASSWORD_DEFAULT), $userId]
                    );
                }

                goto_page('admin_users_role_requests.php', 'User updated.', ['edit' => $userId]);
            }
        }

        // Delete Non-Admin User Account
        if ($action === 'delete_user') {
            $userId = (int) ($_POST['user_id'] ?? 0);
            $target = rows($pdo, 'SELECT role FROM users WHERE user_id = ?', [$userId]);

            if (!$target || $target[0]['role'] === 'admin') {
                throw new Exception('Administrator accounts cannot be deleted from this page.');
            }

            run_sql($pdo, 'DELETE FROM users WHERE user_id = ?', [$userId]);
            goto_page('admin_users_role_requests.php', 'User deleted.');
        }

        // Approve or Reject Club Leadership Role Request
        if ($action === 'approve_request' || $action === 'reject_request') {
            $requestId = (int) ($_POST['request_id'] ?? 0);
            $pdo->beginTransaction();

            try {
                $request = rows(
                    $pdo,
                    'SELECT rr.*, u.role AS account_role 
                     FROM role_requests rr 
                     JOIN users u ON u.user_id = rr.user_id 
                     WHERE rr.request_id = ? FOR UPDATE',
                    [$requestId]
                )[0] ?? null;

                if (!$request || $request['request_status'] !== 'pending') {
                    throw new Exception('This request is no longer pending.');
                }

                if ($action === 'approve_request') {
                    $clubId        = (int) $request['club_id'];
                    $userId        = (int) $request['user_id'];
                    $requestedRole = $request['requested_role'];

                    // Lock club row and replace any existing holder of that club role
                    rows($pdo, 'SELECT club_id FROM clubs WHERE club_id = ? FOR UPDATE', [$clubId]);
                    run_sql(
                        $pdo,
                        'DELETE FROM club_memberships WHERE club_id = ? AND (club_role = ? OR user_id = ?)',
                        [$clubId, $requestedRole, $userId]
                    );
                    run_sql(
                        $pdo,
                        'INSERT INTO club_memberships (user_id, club_id, club_role) VALUES (?, ?, ?)',
                        [$userId, $clubId, $requestedRole]
                    );
                }

                $newStatus = ($action === 'approve_request') ? 'approved' : 'rejected';
                run_sql(
                    $pdo,
                    'UPDATE role_requests SET request_status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE request_id = ?',
                    [$newStatus, $adminId, $requestId]
                );

                $pdo->commit();
                goto_page('admin_users_role_requests.php', 'Role request ' . $newStatus . '.');
            } catch (Throwable $exception) {
                $pdo->rollBack();
                throw $exception;
            }
        }
    } catch (Throwable $exception) {
        $error = $exception instanceof PDOException
            ? 'Database change failed. Check that email/student ID is unique, or whether related records prevent deletion.'
            : $exception->getMessage();
        error_log($exception->getMessage());
    }
}

// ==========================================================================
// Data Fetching & Search Filtering
// ==========================================================================

// Fetch all pending leadership requests
$pending = rows(
    $pdo,
    'SELECT rr.*, u.first_name, u.last_name, u.email, u.student_id, u.email_verified, c.club_name
     FROM role_requests rr 
     JOIN users u ON u.user_id = rr.user_id 
     JOIN clubs c ON c.club_id = rr.club_id
     WHERE rr.request_status = "pending" 
     ORDER BY rr.requested_at ASC, rr.request_id ASC'
);

// Summary statistics
$stats   = rows($pdo, 'SELECT COUNT(*) AS total, SUM(role = "admin") AS admins, SUM(email_verified = 1) AS verified FROM users')[0];
$leaders = count_sql($pdo, 'SELECT COUNT(DISTINCT user_id) AS total FROM club_memberships WHERE membership_status = "active"');

// Search & filter parameters
$search       = trim($_GET['search'] ?? '');
$roleFilter   = $_GET['role'] ?? '';
$verifyFilter = $_GET['verified'] ?? '';
$parts        = [];
$params       = [];

if ($search !== '') {
    $parts[] = '(CONCAT(u.first_name, " ", u.last_name) LIKE ? OR u.email LIKE ? OR u.student_id LIKE ?)';
    array_push($params, '%' . $search . '%', '%' . $search . '%', '%' . $search . '%');
}

if ($roleFilter === 'admin') {
    $parts[] = 'u.role = "admin"';
} elseif ($roleFilter === 'user') {
    $parts[] = 'u.role = "user"';
} elseif (in_array($roleFilter, ['president', 'vice_president'], true)) {
    $parts[]  = 'EXISTS(SELECT 1 FROM club_memberships x WHERE x.user_id = u.user_id AND x.club_role = ? AND x.membership_status = "active")';
    $params[] = $roleFilter;
}

if ($verifyFilter === '1' || $verifyFilter === '0') {
    $parts[]  = 'u.email_verified = ?';
    $params[] = (int) $verifyFilter;
}

$whereSql = $parts ? ' WHERE ' . implode(' AND ', $parts) : '';

// Pagination setup
$perPage = 6;
$page    = max(1, (int) ($_GET['page'] ?? 1));
$total   = count_sql($pdo, 'SELECT COUNT(*) AS total FROM users u' . $whereSql, $params);
$page    = min($page, max(1, (int) ceil($total / $perPage)));
$offset  = ($page - 1) * $perPage;

// Fetch paginated user records with active club leadership roles
$usersQuery = '
    SELECT u.user_id, u.first_name, u.last_name, u.email, u.student_id, u.role, u.email_verified, u.created_at,
           GROUP_CONCAT(DISTINCT CASE WHEN m.membership_status = "active" THEN CONCAT(UPPER(REPLACE(m.club_role, "_", " ")), ": ", c.club_name) END SEPARATOR ", ") AS leadership,
           (SELECT COUNT(*) FROM role_requests rr WHERE rr.user_id = u.user_id AND rr.request_status = "pending") AS pending_count
    FROM users u 
    LEFT JOIN club_memberships m ON m.user_id = u.user_id 
    LEFT JOIN clubs c ON c.club_id = m.club_id'
    . $whereSql .
    ' GROUP BY u.user_id, u.first_name, u.last_name, u.email, u.student_id, u.role, u.email_verified, u.created_at
      ORDER BY u.created_at DESC, u.user_id DESC 
      LIMIT ? OFFSET ?';

$queryParams = array_merge($params, [$perPage, $offset]);
$stmtUsers   = $pdo->prepare($usersQuery);
foreach ($queryParams as $idx => $val) {
    $stmtUsers->bindValue($idx + 1, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmtUsers->execute();
$users = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

// Selected user for editing
$selectedId = (int) ($_GET['edit'] ?? 0);
$selected   = ($selectedId > 0 && !isset($_GET['new']))
    ? (rows($pdo, 'SELECT user_id, first_name, last_name, email, phone, student_id, role, email_verified FROM users WHERE user_id = ?', [$selectedId])[0] ?? null)
    : null;

// Selected request for detailed review
$reviewId = (int) ($_GET['review'] ?? 0);
$review   = null;
foreach ($pending as $request) {
    if ((int) $request['request_id'] === $reviewId) {
        $review = $request;
    }
}

// Render page header
admin_header('Users & Role Requests', 'Manage accounts, review leadership requests and keep club access accurate.', 'users');
?>

<!-- Top Add User Button -->
<div class="page-actions">
    <a class="btn btn-green" href="admin_users_role_requests.php?new=1#user-form">＋ Add User</a>
</div>

<!-- Status / Error Messages -->
<?php if ($error): ?>
    <div class="message error"><?= e($error) ?></div>
<?php endif; ?>

<?php if (isset($_GET['ok'])): ?>
    <div class="message"><?= e($_GET['ok']) ?></div>
<?php endif; ?>

<!-- PENDING ROLE REQUESTS PANEL -->
<section class="panel">
    <h2>Pending Role Requests · <?= count($pending) ?></h2>
    <p class="small">Approval grants leadership for the requested club. Rejection leaves the user's account role unchanged.</p>

    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>Applicant</th>
                    <th>Requested club</th>
                    <th>Role</th>
                    <th>Submitted</th>
                    <th>Decision</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pending as $request): ?>
                    <tr>
                        <td>
                            <div class="table-title"><?= e($request['first_name'] . ' ' . $request['last_name']) ?></div>
                            <div class="table-subtitle"><?= e($request['email']) ?></div>
                        </td>
                        <td><?= e($request['club_name']) ?></td>
                        <td><?= e(ucwords(str_replace('_', ' ', $request['requested_role']))) ?></td>
                        <td><?= e(display_date($request['requested_at'])) ?></td>
                        <td class="actions">
                            <a href="?review=<?= (int) $request['request_id'] ?>#request-review">Review</a> ·
                            <form class="inline-form" method="post" onsubmit="return confirm('Approve and assign this club leader?');">
                                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                                <input type="hidden" name="request_id" value="<?= (int) $request['request_id'] ?>">
                                <button name="action" value="approve_request">Approve</button>
                            </form> ·
                            <form class="inline-form" method="post" onsubmit="return confirm('Reject this role request?');">
                                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                                <input type="hidden" name="request_id" value="<?= (int) $request['request_id'] ?>">
                                <button class="danger" name="action" value="reject_request">Reject</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (!$pending): ?>
                    <tr>
                        <td class="empty" colspan="5">No pending role requests.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="info">
        <strong>Review before granting access</strong><br>
        Approving replaces an existing holder of the same position.
    </div>
</section>

<!-- REQUEST DETAILS REVIEW PANEL -->
<?php if ($review): ?>
    <section class="panel" id="request-review">
        <h2>Review request #<?= (int) $review['request_id'] ?></h2>
        <p>
            <strong><?= e($review['first_name'] . ' ' . $review['last_name']) ?></strong> · <?= e($review['student_id']) ?><br>
            <?= e($review['email']) ?><br>
            Club: <?= e($review['club_name']) ?><br>
            Position: <?= e(str_replace('_', ' ', $review['requested_role'])) ?>
        </p>
    </section>
<?php endif; ?>

<!-- USER SUMMARY TABS -->
<div class="tabs">
    <span class="badge blue">All users · <?= (int) $stats['total'] ?></span>
    <span class="badge blue">Club leaders · <?= $leaders ?></span>
    <span class="badge blue">Administrators · <?= (int) $stats['admins'] ?></span>
</div>

<!-- SEARCH & FILTER FORM -->
<form method="get" class="panel filters">
    <div class="field field-grow">
        <label>Search User</label>
        <input name="search" placeholder="Search by name, university email or student ID" value="<?= e($search) ?>">
    </div>

    <div class="field field-select">
        <label>Role</label>
        <select name="role">
            <option value="">All Roles</option>
            <?php
            $roleOptions = [
                'admin'          => 'Administrator',
                'user'           => 'Student / Reader',
                'president'      => 'President',
                'vice_president' => 'Vice President',
            ];
            foreach ($roleOptions as $key => $label):
            ?>
                <option value="<?= e($key) ?>" <?= $roleFilter === $key ? 'selected' : '' ?>>
                    <?= e($label) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="field field-select">
        <label>Email</label>
        <select name="verified">
            <option value="">All Accounts</option>
            <option value="1" <?= $verifyFilter === '1' ? 'selected' : '' ?>>Verified</option>
            <option value="0" <?= $verifyFilter === '0' ? 'selected' : '' ?>>Not verified</option>
        </select>
    </div>

    <button class="btn btn-green">Search</button>
</form>

<!-- RESULTS TOOLBAR -->
<div class="toolbar">
    <span><?= $total ?> registered users matching filters</span>
    <span>Sort by: Newest first</span>
</div>

<!-- USERS DATA TABLE -->
<div class="table-scroll">
    <table>
        <thead>
            <tr>
                <th>User</th>
                <th>Role / assigned club</th>
                <th>Email</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td>
                        <div class="table-title"><?= e($user['first_name'] . ' ' . $user['last_name']) ?></div>
                        <div class="table-subtitle">
                            <?= e($user['email']) ?><br>
                            <?= e($user['student_id']) ?>
                        </div>
                    </td>

                    <td>
                        <span class="badge blue">
                            <?= $user['role'] === 'admin' ? 'Administrator' : ($user['leadership'] ? 'Club leader' : 'Student / Reader') ?>
                        </span>
                        <div class="table-subtitle"><?= e($user['leadership'] ?: 'Not assigned') ?></div>
                    </td>

                    <td>
                        <span class="badge <?= $user['email_verified'] ? 'green' : 'gray' ?>">
                            <?= $user['email_verified'] ? 'Verified' : 'Not verified' ?>
                        </span>
                        <?php if ($user['pending_count']): ?>
                            <div class="table-subtitle">Pending role request</div>
                        <?php endif; ?>
                    </td>

                    <td class="actions">
                        <a href="?edit=<?= (int) $user['user_id'] ?>#user-form">Edit</a>
                        <?php if ($user['role'] !== 'admin'): ?> ·
                            <form method="post" class="inline-form" onsubmit="return confirm('Delete this account? Related records may prevent deletion.');">
                                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                                <input type="hidden" name="user_id" value="<?= (int) $user['user_id'] ?>">
                                <button class="danger" name="action" value="delete_user">Delete</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if (!$users): ?>
                <tr>
                    <td colspan="4" class="empty">No users found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- PAGINATION FOOTER -->
<div class="pagination-bar">
    <span>Showing <?= $total ? $offset + 1 : 0 ?>–<?= min($offset + $perPage, $total) ?> of <?= $total ?> users</span>
    <?php pagination($page, $total, $perPage); ?>
</div>

<!-- ADD / EDIT USER FORM PANEL -->
<section class="panel user-edit" id="user-form">
    <h2><?= $selected ? 'Edit User Account' : 'Add User Account' ?></h2>
    <p class="small">Student / Reader and Administrator are account roles. Club President and Vice President are assigned separately.</p>

    <form method="post">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="user_id" value="<?= (int) ($selected['user_id'] ?? 0) ?>">

        <div class="two-cols">
            <div class="field">
                <label>First name *</label>
                <input required maxlength="100" name="first_name" value="<?= e($selected['first_name'] ?? '') ?>">
            </div>

            <div class="field">
                <label>Last name *</label>
                <input required maxlength="100" name="last_name" value="<?= e($selected['last_name'] ?? '') ?>">
            </div>

            <div class="field">
                <label>Email *</label>
                <input required type="email" maxlength="150" name="email" value="<?= e($selected['email'] ?? '') ?>">
            </div>

            <div class="field">
                <label>Phone *</label>
                <input required maxlength="20" name="phone" value="<?= e($selected['phone'] ?? '') ?>">
            </div>

            <div class="field">
                <label>Student ID *</label>
                <input required maxlength="30" name="student_id" value="<?= e($selected['student_id'] ?? '') ?>">
            </div>

            <div class="field">
                <label><?= $selected ? 'New password (leave blank to keep current)' : 'Password *' ?></label>
                <input type="password" minlength="8" name="password" <?= $selected ? '' : 'required' ?>>
            </div>

            <div class="field">
                <label>Account role</label>
                <select name="role">
                    <option value="user" <?= ($selected['role'] ?? 'user') === 'user' ? 'selected' : '' ?>>Student / Reader</option>
                    <option value="admin" <?= ($selected['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Administrator</option>
                </select>
            </div>

        </div>

        <div class="form-buttons">
            <button class="btn btn-green" name="action" value="<?= $selected ? 'edit_user' : 'add_user' ?>">
                <?= $selected ? 'Save changes' : 'Add User' ?>
            </button>
            <a class="btn" href="admin_users_role_requests.php">Cancel</a>
        </div>
    </form>
</section>

<p class="small">Accounts with related posts or requests cannot be deleted until those records are handled. Administrator accounts are protected from deletion here.</p>

<?php admin_footer(); ?>
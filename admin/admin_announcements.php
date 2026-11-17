<?php
/**
 * NSBM 360 - Announcements Administration
 * Handles creation, editing, scheduling, expiring, publishing, archiving, 
 * and deletion of campus announcements by administrators.
 */

// Include shared database connection (provides $pdo)
require_once __DIR__ . '/../config/database.php';

// Initialize session
session_start();

// Verify that an administrator is logged in
$adminId = (int) ($_SESSION['user_id'] ?? 0);
if ($adminId < 1) {
    http_response_code(401);
    include __DIR__ . '/../admin_access_denied.php';
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
    return $date ? date('d M Y, h:i A', strtotime($date)) : 'Not set';
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
    <link rel="stylesheet" href="../assets/css/admin_announcements.css">
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
            <a href="index.php"><i class="fa-solid fa-arrow-up-right-from-square"></i> View public site</a>
            <form method="post">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
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

// Fetch faculties dynamically from the database table with a fallback if empty
$facultiesFromDb = rows($pdo, "SELECT faculty_id, faculty_name FROM faculties ORDER BY faculty_id ASC");
$facultiesMap = [];
foreach ($facultiesFromDb as $fac) {
    $facultiesMap[(int) $fac['faculty_id']] = $fac['faculty_name'];
}

if (empty($facultiesMap)) {
    $facultiesMap = [
        1 => 'Faculty of Computing',
        2 => 'Faculty of Engineering',
        3 => 'Faculty of Business',
        4 => 'Faculty of Science',
        5 => 'Faculty of Post Graduates',
        6 => 'General / All Faculties'
    ];
}

// ==========================================================================
// POST Action Handlers (Save Announcement, Delete Announcement)
// ==========================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $postAllowed) {
    try {
        $action = $_POST['action'] ?? '';

        // Save or Update Announcement
        if ($action === 'save_announcement') {
            $id          = (int) ($_POST['announcement_id'] ?? 0);
            $title       = trim($_POST['title'] ?? '');
            $content     = trim($_POST['content'] ?? '');
            $status      = $_POST['submit_status'] ?? $_POST['status'] ?? 'draft';
            $facultyId   = !empty($_POST['faculty_id']) ? (int) $_POST['faculty_id'] : null;
            $isImportant = isset($_POST['is_important']) ? 1 : 0;
            
            $expiryInput = trim($_POST['expires_at'] ?? '');
            $expiry      = null;

            if ($expiryInput !== '') {
                $timestamp = strtotime($expiryInput);
                if ($timestamp === false) {
                    throw new Exception('Please enter a valid expiry date.');
                }
                $expiry = date('Y-m-d H:i:s', $timestamp);
            }

            if ($title === '' || $content === '' || !in_array($status, ['draft', 'published', 'archived'], true)) {
                throw new Exception('Title, content and a valid status are required.');
            }

            $currentTime = date('Y-m-d H:i:s');
            $imagePath = null;
            $publishedAt = null;

            if ($id > 0) {
                $existingNotice = rows($pdo, 'SELECT status, image FROM announcements WHERE announcement_id = ?', [$id])[0] ?? null;
                $imagePath = $existingNotice['image'] ?? null;
            }

            if ($status === 'published') {
                $publishedAt = $currentTime;
            }

            // Handle Announcement Image Upload
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $fileTmpPath   = $_FILES['image']['tmp_name'];
                $fileName      = $_FILES['image']['name'];
                $fileSize      = $_FILES['image']['size'];
                $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
                $maxFileSize       = 5 * 1024 * 1024; // 5MB limit

                if (!in_array($fileExtension, $allowedExtensions, true)) {
                    throw new Exception('Invalid file type. Only JPG, PNG, and WEBP images are allowed.');
                }

                if ($fileSize > $maxFileSize) {
                    throw new Exception('Uploaded image exceeds the 5MB size limit.');
                }

                $uploadDir = __DIR__ . '/../uploads/announcements/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $newFileName = 'announcement_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $fileExtension;
                $destPath    = $uploadDir . $newFileName;

                if (move_uploaded_file($fileTmpPath, $destPath)) {
                    $imagePath = '../uploads/announcements/' . $newFileName;
                } else {
                    throw new Exception('Failed to move uploaded file to destination folder.');
                }
            }

            if ($id > 0) {
                run_sql(
                    $pdo,
                    'UPDATE announcements SET title = ?, content = ?, image = ?, status = ?, expires_at = ?, faculty_id = ?, is_important = ?, published_at = ? WHERE announcement_id = ?',
                    [$title, $content, $imagePath, $status, $expiry, $facultyId, $isImportant, $publishedAt, $id]
                );
            } else {
                run_sql(
                    $pdo,
                    'INSERT INTO announcements (created_by, title, content, image, status, expires_at, faculty_id, is_important, published_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
                    [$adminId, $title, $content, $imagePath, $status, $expiry, $facultyId, $isImportant, $publishedAt]
                );
                $id = (int) $pdo->lastInsertId();
            }

            // Clean redirect without keeping edit ID stuck in URL
            goto_page('admin_announcements.php', 'Announcement saved successfully.');
        }

        // Delete Announcement Record
        if ($action === 'delete_announcement') {
            $id = (int) ($_POST['announcement_id'] ?? 0);
            if ($id < 1) {
                throw new Exception('Invalid announcement.');
            }

            run_sql($pdo, 'DELETE FROM announcements WHERE announcement_id = ?', [$id]);
            goto_page('admin_announcements.php', 'Announcement deleted.');
        }
    } catch (Throwable $exception) {
        $error = $exception instanceof PDOException ? 'Could not save the announcement. Check your data.' : $exception->getMessage();
        error_log($exception->getMessage());
    }
}

// ==========================================================================
// Data Fetching, Search Filtering & Pagination
// ==========================================================================

$search = trim($_GET['search'] ?? '');
$filter = $_GET['status'] ?? '';
$where  = [];
$values = [];

if ($search !== '') {
    $where[]  = '(a.title LIKE ? OR a.content LIKE ?)';
    $values[] = '%' . $search . '%';
    $values[] = '%' . $search . '%';
}

if ($filter === 'expired') {
    $where[] = 'a.status = "published" AND a.expires_at IS NOT NULL AND a.expires_at < NOW()';
} elseif (in_array($filter, ['published', 'draft', 'archived'], true)) {
    $where[]  = 'a.status = ?';
    $values[] = $filter;
}

$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

// Pagination calculations
$perPage = 6;
$page    = max(1, (int) ($_GET['page'] ?? 1));
$total   = count_sql($pdo, 'SELECT COUNT(*) AS total FROM announcements a' . $whereSql, $values);
$page    = min($page, max(1, (int) ceil($total / $perPage)));
$offset  = ($page - 1) * $perPage;

// Fetch paginated notices
$noticesQuery = 'SELECT a.*, CONCAT(u.first_name, " ", u.last_name) AS author, f.faculty_name FROM announcements a
    JOIN users u ON u.user_id = a.created_by
    LEFT JOIN faculties f ON a.faculty_id = f.faculty_id' . $whereSql . ' ORDER BY a.created_at DESC, a.announcement_id DESC LIMIT ? OFFSET ?';

$queryParams = array_merge($values, [$perPage, $offset]);
$stmtNotices = $pdo->prepare($noticesQuery);
$idxVal = 1;
foreach ($queryParams as $val) {
    if (is_int($val)) {
        $stmtNotices->bindValue($idxVal, $val, PDO::PARAM_INT);
    } else {
        $stmtNotices->bindValue($idxVal, $val, PDO::PARAM_STR);
    }
    $idxVal++;
}
$stmtNotices->execute();
$notices = $stmtNotices->fetchAll(PDO::FETCH_ASSOC);

// Summary statistics for status tabs
$stats = rows($pdo, 'SELECT COUNT(*) AS total, SUM(status = "published") AS published,
    SUM(status = "draft") AS draft, SUM(status = "archived") AS archived,
    SUM(status = "published" AND expires_at IS NOT NULL AND expires_at < NOW()) AS expired FROM announcements')[0];

// Selected announcement for preview or editing (Clean state initialization)
$selectedId = (int) ($_GET['edit'] ?? $_GET['view'] ?? 0);
$selected   = ($selectedId > 0)
    ? (rows($pdo, 'SELECT a.*, CONCAT(u.first_name, " ", u.last_name) AS author, f.faculty_name FROM announcements a JOIN users u ON a.created_by = u.user_id LEFT JOIN faculties f ON a.faculty_id = f.faculty_id WHERE a.announcement_id = ?', [$selectedId])[0] ?? null)
    : null;

// Render page header
admin_header('Announcements', 'Timely campus notices, published by administrators.', 'announcements');
?>

<!-- Top Create Announcement Action -->
<div class="page-actions">
    <a class="btn btn-green" href="admin_announcements.php?new=1#announcement-form">＋ Create announcement</a>
</div>

<!-- Status / Error Messages -->
<?php if ($error): ?>
    <div class="message error"><?= e($error) ?></div>
<?php endif; ?>

<?php if (isset($_GET['ok'])): ?>
    <div class="message"><?= e($_GET['ok']) ?></div>
<?php endif; ?>

<!-- Status Filter Tabs -->
<div class="tabs">
    <?php foreach (['' => 'All notices', 'published' => 'Published', 'draft' => 'Drafts', 'archived' => 'Archived', 'expired' => 'Expired'] as $key => $label): ?>
        <?php $number = ($key === '') ? $stats['total'] : $stats[$key]; ?>
        <a class="<?= $filter === $key ? 'active' : '' ?>" href="?<?= http_build_query(['status' => $key]) ?>">
            <?= e($label) ?> <?= (int) $number ?>
        </a>
    <?php endforeach; ?>
</div>

<!-- Search & Filter Form -->
<form class="panel filters" method="get">
    <div class="field field-grow">
        <label>Search announcements</label>
        <input name="search" placeholder="Search announcement title or content" value="<?= e($search) ?>">
    </div>

    <div class="field field-select">
        <label>Status</label>
        <select name="status">
            <option value="">All statuses</option>
            <?php foreach (['published', 'draft', 'archived', 'expired'] as $s): ?>
                <option value="<?= e($s) ?>" <?= $filter === $s ? 'selected' : '' ?>>
                    <?= ucfirst($s) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <button class="btn btn-green">Search</button>
</form>

<!-- Results Toolbar -->
<div class="toolbar">
    <span><?= $total ?> announcements matching filters</span>
    <span>Sort by: Newest first</span>
</div>

<!-- Announcements Data Table -->
<div class="table-scroll">
    <table>
        <thead>
            <tr>
                <th>Image</th>
                <th>Announcement</th>
                <th>Faculty</th>
                <th>Created by</th>
                <th>Status</th>
                <th>Created / expiry</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($notices as $notice):
                $expired     = $notice['status'] === 'published' && $notice['expires_at'] && strtotime($notice['expires_at']) < time();
                $isImportant = !empty($notice['is_important']);
                $facultyName = $notice['faculty_name'] ?? 'General / All Faculties';
            ?>
                <tr <?= $isImportant ? 'style="background-color: #fdf2f2;"' : '' ?>>
                    <td>
                        <?php if (!empty($notice['image'])): ?>
                            <img src="<?= e($notice['image']) ?>" alt="Thumbnail" class="table-thumb">
                        <?php else: ?>
                            <span class="no-thumb">No image</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="table-title">
                            <?= e($notice['title']) ?> 
                            <?php if ($isImportant): ?>
                                <span class="badge red" style="font-size: 9px; padding: 2px 5px;">Important</span>
                            <?php endif; ?>
                        </div>
                        <div class="table-subtitle"><?= e(substr((string) $notice['content'], 0, 100)) ?></div>
                    </td>
                    <td><?= e($facultyName) ?></td>
                    <td><?= e($notice['author']) ?></td>
                    <td>
                        <span class="badge <?= $expired ? 'yellow' : ($notice['status'] === 'published' ? 'green' : ($notice['status'] === 'archived' ? 'blue' : ($notice['status'] === 'draft' ? 'gray' : ''))) ?>">
                            <?= e($expired ? 'Expired' : $notice['status']) ?>
                        </span>
                    </td>
                    <td>
                        <?= e(display_date($notice['created_at'])) ?>
                        <div class="table-subtitle">Expires: <?= e(display_date($notice['expires_at'])) ?></div>
                    </td>
                    <td class="actions">
                        <a href="?view=<?= (int) $notice['announcement_id'] ?>#announcement-preview">View</a> · 
                        <a href="?edit=<?= (int) $notice['announcement_id'] ?>#announcement-form">Edit</a> · 
                        <form method="post" class="inline-form" onsubmit="return confirm('Delete this announcement?');">
                            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                            <input type="hidden" name="announcement_id" value="<?= (int) $notice['announcement_id'] ?>">
                            <button class="danger" name="action" value="delete_announcement">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if (!$notices): ?>
                <tr>
                    <td class="empty" colspan="7">No announcements found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Pagination Footer -->
<div class="pagination-bar">
    <span>Showing <?= $total ? $offset + 1 : 0 ?>–<?= min($offset + $perPage, $total) ?> of <?= $total ?> notices</span>
    <?php pagination($page, $total, $perPage); ?>
</div>

<!-- Announcement Preview Section -->
<?php if ($selected && isset($_GET['view'])): ?>
    <section class="panel" id="announcement-preview">
        <h2>
            <?= e($selected['title']) ?> 
            <?php if (!empty($selected['is_important'])): ?>
                <span class="badge red" style="font-size: 11px;">Important</span>
            <?php endif; ?>
        </h2>
        <p class="small">
            By <?= e($selected['author']) ?> · Faculty: <?= e($selected['faculty_name'] ?? 'General / All Faculties') ?> · Published: <?= e(display_date($selected['published_at'] ?? null)) ?>
        </p>
        <p><?= nl2br(e($selected['content'])) ?></p>
        <?php if ($selected['image']): ?>
            <p><img class="preview-image" src="<?= e($selected['image']) ?>" alt="Announcement image"></p>
        <?php endif; ?>
    </section>
<?php endif; ?>

<!-- Two-Column Management Forms -->
<div class="two-cols wide-left">
    <!-- Left Column: Create / Edit Form -->
    <section class="panel" id="announcement-form">
        <h2><?= $selected ? 'Edit announcement' : 'Create announcement' ?></h2>
        <p class="small"><?= $selected ? 'Selected: ' . e($selected['title']) : 'Write a new campus announcement.' ?></p>

        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="action" value="save_announcement">
            <input type="hidden" name="announcement_id" value="<?= (int) ($selected['announcement_id'] ?? 0) ?>">
            
            <div class="field">
                <label>Title *</label>
                <input name="title" required maxlength="255" value="<?= e($selected['title'] ?? '') ?>">
            </div>
            
            <!-- Faculty Radio Buttons List -->
            <div class="field">
                <label>Faculty *</label>
                <div class="announcement-faculty-group">
                    <?php 
                    $currentFaculty = (int) ($selected['faculty_id'] ?? 0);
                    foreach ($facultiesMap as $fId => $fName): 
                    ?>
                        <label class="announcement-faculty-label">
                            <input type="radio" name="faculty_id" value="<?= $fId ?>" <?= $currentFaculty === $fId ? 'checked' : '' ?>> 
                            <?= e($fName) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="field">
                <label>Announcement content *</label>
                <textarea rows="6" name="content" required><?= e($selected['content'] ?? '') ?></textarea>
            </div>
            
            <!-- Is Important Checkbox -->
            <div class="field" style="flex-direction: row; align-items: center; gap: 10px;">
                <input type="checkbox" name="is_important" value="1" id="is_important_check" <?= !empty($selected['is_important']) ? 'checked' : '' ?>>
                <label for="is_important_check" style="cursor: pointer; margin-bottom: 0;">Mark as Important Notice</label>
            </div>

            <div class="field">
                <label>Announcement image (optional)</label>
                <?php if (!empty($selected['image'])): ?>
                    <div style="margin-bottom: 8px;">
                        <img src="<?= e($selected['image']) ?>" alt="Current image" style="max-height: 80px; border-radius: 4px; display: block; margin-bottom: 4px;">
                        <span class="small">Current file: <?= e(basename($selected['image'])) ?></span>
                    </div>
                <?php endif; ?>
                <input type="file" name="image" accept="image/png, image/jpeg, image/webp">
            </div>

            <div class="field">
                <label>Expiry date (optional)</label>
                <input type="datetime-local" name="expires_at" value="<?= $selected && $selected['expires_at'] ? e(date('Y-m-d\TH:i', strtotime($selected['expires_at']))) : '' ?>">
            </div>

            <div class="field">
                <label>Status</label>
                <select name="status">
                    <?php foreach (['draft', 'published', 'archived'] as $state): ?>
                        <option value="<?= e($state) ?>" <?= ($selected['status'] ?? 'draft') === $state ? 'selected' : '' ?>>
                            <?= ucfirst($state) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-buttons">
                <button class="btn btn-green" name="submit_status" value="published">Publish announcement</button>
                <button class="btn" name="submit_status" value="draft">Save draft</button>
                <button class="btn" type="submit">Save selected status</button>
            </div>
        </form>
    </section>

    <!-- Right Column: Publication Settings & Info Sidebar -->
    <aside class="panel">
        <h2>Publication settings</h2>
        <?php if ($selected): ?>
            <p>
                <span class="badge <?= $selected['status'] === 'published' ? 'green' : ($selected['status'] === 'expired' ? 'yellow' : ($selected['status'] === 'archived' ? 'blue' : 'gray')) ?>">
                    <?= e($selected['status']) ?>
                </span>
            </p>
            <p class="small">
                Created by <?= e($selected['author']) ?><br>
                Created: <?= e(display_date($selected['created_at'])) ?><br>
                Published: <?= e(display_date($selected['published_at'] ?? null)) ?><br>
                Expires: <?= e(display_date($selected['expires_at'])) ?>
            </p>
            <p><a href="?view=<?= (int) $selected['announcement_id'] ?>#announcement-preview">Preview announcement →</a></p>
            <form method="post" onsubmit="return confirm('Permanently delete this announcement?');">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="announcement_id" value="<?= (int) $selected['announcement_id'] ?>">
                <button class="btn btn-red" name="action" value="delete_announcement">Delete announcement</button>
            </form>
        <?php else: ?>
            <p class="small">Save an announcement to see its publication details.</p>
        <?php endif; ?>
        <div class="info">
            <strong>Keep notices useful</strong><br>
            Set an expiry date for time-sensitive notices. Expired notices remain in management history.
        </div>
    </aside>
</div>

<?php admin_footer(); ?>
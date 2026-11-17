<?php
/**
 * NSBM 360 - Official News & Posts Administration
 * Handles creation, editing, publishing, archiving, and deletion of campus posts,
 * including event-specific details, cover images, and additional gallery images.
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

// Fetch Dropdown Data securely from database
$categories    = rows($pdo, "SELECT category_id, category_name FROM categories ORDER BY category_id ASC");
$subcategories = rows($pdo, "SELECT subcategory_id, category_id, subcategory_name FROM subcategories ORDER BY subcategory_name ASC");
$faculties     = rows($pdo, "SELECT faculty_id, faculty_name FROM faculties ORDER BY faculty_id ASC");
$clubs         = rows($pdo, "SELECT club_id, club_name FROM clubs WHERE status = 'active' ORDER BY club_name ASC");
$sports        = rows($pdo, "SELECT sport_id, sport_name FROM sports WHERE is_active = 1 ORDER BY sport_name ASC");

// ==========================================================================
// POST Action Handlers (Save Post, Delete Image, Delete Post)
// ==========================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $postAllowed) {
    try {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'save_post') {
            $postId        = (int) ($_POST['post_id'] ?? 0);
            $title         = trim($_POST['title'] ?? '');
            $categoryId    = (int) ($_POST['category_id'] ?? 0);
            $subcategoryId = !empty($_POST['subcategory_id']) ? (int) $_POST['subcategory_id'] : null;
            $facultyId     = !empty($_POST['faculty_id']) ? (int) $_POST['faculty_id'] : null;
            $clubId        = !empty($_POST['club_id']) ? (int) $_POST['club_id'] : null;
            $sportId       = !empty($_POST['sport_id']) ? (int) $_POST['sport_id'] : null;
            $summary       = trim($_POST['summary'] ?? '');
            $content       = trim($_POST['content'] ?? '');
            $status        = $_POST['submit_status'] ?? $_POST['status'] ?? 'draft';

            if ($title === '' || $content === '' || $categoryId < 1) {
                throw new Exception('Title, Content and Main Category are required fields.');
            }
            if (!in_array($status, ['draft', 'published', 'archived'], true)) {
                throw new Exception('Invalid post status selected.');
            }

            $currentTime = date('Y-m-d H:i:s');
            $coverImage = null;
            $publishedAt = null;

            if ($postId > 0) {
                $existingPost = rows($pdo, 'SELECT status, published_at, cover_image FROM posts WHERE post_id = ?', [$postId])[0] ?? null;
                $coverImage = $existingPost['cover_image'] ?? null;
                
                // If status is published, update published_at to now. Otherwise keep existing published_at or null.
                if ($status === 'published') {
                    $publishedAt = $currentTime;
                } else {
                    $publishedAt = $existingPost['published_at'] ?? null;
                }
            } else {
                if ($status === 'published') {
                    $publishedAt = $currentTime;
                }
            }

            $uploadDir = __DIR__ . '/../uploads/posts/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];

            // Handle Cover Image Upload
            if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, $allowedExtensions, true)) {
                    throw new Exception('Invalid cover image type. Only JPG, PNG, and WEBP allowed.');
                }
                if ($_FILES['cover_image']['size'] > 5 * 1024 * 1024) {
                    throw new Exception('Cover image size must be less than 5MB.');
                }
                $newFileName = 'cover_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $uploadDir . $newFileName)) {
                    $coverImage = '../uploads/posts/' . $newFileName;
                } else {
                    throw new Exception('Failed to upload cover image.');
                }
            }

            $pdo->beginTransaction();

            if ($postId > 0) {
                // created_at is intentionally omitted here to preserve original creation time; updated_at refreshes to NOW()
                run_sql(
                    $pdo,
                    "UPDATE posts SET category_id = ?, subcategory_id = ?, faculty_id = ?, sport_id = ?, club_id = ?, title = ?, summary = ?, content = ?, cover_image = ?, status = ?, published_at = ?, updated_at = NOW() WHERE post_id = ?",
                    [$categoryId, $subcategoryId, $facultyId, $sportId, $clubId, $title, $summary, $content, $coverImage, $status, $publishedAt, $postId]
                );
            } else {
                run_sql(
                    $pdo,
                    "INSERT INTO posts (created_by, category_id, subcategory_id, faculty_id, sport_id, club_id, title, summary, content, cover_image, status, published_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
                    [$adminId, $categoryId, $subcategoryId, $facultyId, $sportId, $clubId, $title, $summary, $content, $coverImage, $status, $publishedAt]
                );
                $postId = (int) $pdo->lastInsertId();
            }

            // Handle Additional Images Upload
            if (!empty($_FILES['additional_images']['name'][0])) {
                $maxOrder = count_sql($pdo, "SELECT COALESCE(MAX(display_order), 0) FROM post_images WHERE post_id = ?", [$postId]);
                foreach ($_FILES['additional_images']['name'] as $idx => $name) {
                    if ($_FILES['additional_images']['error'][$idx] === UPLOAD_ERR_OK) {
                        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                        if (!in_array($ext, $allowedExtensions, true)) {
                            throw new Exception("Invalid file type for '$name'. Only JPG, PNG, and WEBP allowed.");
                        }
                        if ($_FILES['additional_images']['size'][$idx] > 5 * 1024 * 1024) {
                            throw new Exception("Image '$name' exceeds the 5MB limit.");
                        }
                        $addFileName = 'post_img_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                        if (move_uploaded_file($_FILES['additional_images']['tmp_name'][$idx], $uploadDir . $addFileName)) {
                            $maxOrder++;
                            run_sql(
                                $pdo,
                                "INSERT INTO post_images (post_id, image_path, alt_text, display_order) VALUES (?, ?, ?, ?)",
                                [$postId, '../uploads/posts/' . $addFileName, $title, $maxOrder]
                            );
                        }
                    }
                }
            }

            // Handle Event-Specific Fields if Category is 'Events'
            $catName = rows($pdo, "SELECT category_name FROM categories WHERE category_id = ?", [$categoryId])[0]['category_name'] ?? '';
            if ($catName === 'Events') {
                $startAt     = trim($_POST['start_at'] ?? '');
                $endAt       = trim($_POST['end_at'] ?? '');
                $venue       = trim($_POST['venue'] ?? '');
                $regDeadline = !empty($_POST['registration_deadline']) ? $_POST['registration_deadline'] : null;
                $regLink     = trim($_POST['registration_link'] ?? '');

                if ($startAt === '' || $endAt === '' || $venue === '') {
                    throw new Exception('Event Start Date/Time, End Date/Time, and Venue are required for Events.');
                }
                if (strtotime($endAt) <= strtotime($startAt)) {
                    throw new Exception('Event End Date & Time must be later than Start Date & Time.');
                }

                $eventDate = date('Y-m-d', strtotime($startAt));
                $startTime = date('H:i:s', strtotime($startAt));
                $endTime   = date('H:i:s', strtotime($endAt));

                if (count_sql($pdo, "SELECT COUNT(*) FROM events WHERE post_id = ?", [$postId]) > 0) {
                    run_sql(
                        $pdo,
                        "UPDATE events SET event_date = ?, start_time = ?, end_time = ?, start_at = ?, end_at = ?, venue = ?, registration_deadline = ?, registration_link = ? WHERE post_id = ?",
                        [$eventDate, $startTime, $endTime, $startAt, $endAt, $venue, $regDeadline, $regLink, $postId]
                    );
                } else {
                    run_sql(
                        $pdo,
                        "INSERT INTO events (post_id, event_date, start_time, end_time, start_at, end_at, venue, registration_deadline, registration_link) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                        [$postId, $eventDate, $startTime, $endTime, $startAt, $endAt, $venue, $regDeadline, $regLink]
                    );
                }
            } else {
                run_sql($pdo, "DELETE FROM events WHERE post_id = ?", [$postId]);
            }

            $pdo->commit();
            goto_page('admin_officialnews.php', 'Post successfully saved.');
        }

        // Delete Individual Additional Image
        if ($action === 'delete_post_image') {
            $imageId = (int) ($_POST['image_id'] ?? 0);
            $postId  = (int) ($_POST['post_id'] ?? 0);
            if ($imageId > 0) {
                run_sql($pdo, "DELETE FROM post_images WHERE image_id = ?", [$imageId]);
            }
            goto_page('admin_officialnews.php', 'Additional image removed.', ['edit' => $postId]);
        }

        // Delete Entire Post and Associated Records
        if ($action === 'delete_post') {
            $postId = (int) ($_POST['post_id'] ?? 0);
            if ($postId < 1) {
                throw new Exception('Invalid Post ID.');
            }

            $pdo->beginTransaction();
            run_sql($pdo, "DELETE FROM events WHERE post_id = ?", [$postId]);
            run_sql($pdo, "DELETE FROM post_images WHERE post_id = ?", [$postId]);
            run_sql($pdo, "DELETE FROM posts WHERE post_id = ?", [$postId]);
            $pdo->commit();

            goto_page('admin_officialnews.php', 'Post deleted successfully.');
        }

    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $exception->getMessage();
    }
}

// ==========================================================================
// Search, Filtering & Pagination
// ==========================================================================

$search         = trim($_GET['search'] ?? '');
$filterCategory = $_GET['category_id'] ?? '';
$where          = [];
$values         = [];

if ($search !== '') {
    $where[]  = '(p.title LIKE ? OR p.content LIKE ?)';
    $values[] = '%' . $search . '%';
    $values[] = '%' . $search . '%';
}
if ($filterCategory !== '' && is_numeric($filterCategory)) {
    $where[]  = 'p.category_id = ?';
    $values[] = (int) $filterCategory;
}

$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$perPage  = 6;
$page     = max(1, (int) ($_GET['page'] ?? 1));
$total    = count_sql($pdo, 'SELECT COUNT(*) FROM posts p' . $whereSql, $values);
$page     = min($page, max(1, (int) ceil($total / $perPage)));
$offset   = ($page - 1) * $perPage;

$postsQuery = "SELECT p.*, c.category_name, sc.subcategory_name, f.faculty_name, cl.club_name, CONCAT(u.first_name, ' ', u.last_name) AS author 
    FROM posts p 
    JOIN categories c ON p.category_id = c.category_id 
    LEFT JOIN subcategories sc ON p.subcategory_id = sc.subcategory_id 
    LEFT JOIN faculties f ON p.faculty_id = f.faculty_id 
    LEFT JOIN clubs cl ON p.club_id = cl.club_id 
    JOIN users u ON p.created_by = u.user_id" . $whereSql . " ORDER BY p.created_at DESC LIMIT ? OFFSET ?";

$queryParams = array_merge($values, [$perPage, $offset]);
$stmt        = $pdo->prepare($postsQuery);
foreach ($queryParams as $idx => $val) {
    $stmt->bindValue($idx + 1, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$postsList = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Selected post for viewing or editing (Clean state initialization)
$selectedId   = (int) ($_GET['edit'] ?? $_GET['view'] ?? 0);
$selectedPost = (!isset($_GET['new']) && $selectedId > 0)
    ? (rows($pdo, "SELECT p.*, c.category_name, sc.subcategory_name, f.faculty_name, cl.club_name, s.sport_name, CONCAT(u.first_name, ' ', u.last_name) AS author, e.start_at, e.end_at, e.venue, e.registration_deadline, e.registration_link 
        FROM posts p 
        JOIN categories c ON p.category_id = c.category_id 
        LEFT JOIN subcategories sc ON p.subcategory_id = sc.subcategory_id 
        LEFT JOIN faculties f ON p.faculty_id = f.faculty_id 
        LEFT JOIN clubs cl ON p.club_id = cl.club_id 
        LEFT JOIN sports s ON p.sport_id = s.sport_id
        JOIN users u ON p.created_by = u.user_id
        LEFT JOIN events e ON p.post_id = e.post_id 
        WHERE p.post_id = ?", [$selectedId])[0] ?? null)
    : null;

$selectedPostImages = $selectedPost ? rows($pdo, "SELECT * FROM post_images WHERE post_id = ? ORDER BY display_order ASC, image_id ASC", [$selectedPost['post_id']]) : [];

// ==========================================================================
// Layout & Helper Functions
// ==========================================================================

function pagination(int $page, int $total, int $perPage): void
{
    $pages = max(1, (int) ceil($total / $perPage));
    if ($pages <= 1) return;
    $params = $_GET;
    echo '<nav class="pagination" aria-label="Table pages">';
    for ($p = max(1, $page - 2); $p <= min($pages, $page + 2); $p++) {
        $params['page'] = $p;
        echo '<a class="' . ($p === $page ? 'current' : '') . '" href="?' . e(http_build_query($params)) . '">' . $p . '</a>';
    }
    echo '</nav>';
}

function get_nav_icon(string $slug): string {
    return match($slug) {
        'overview'      => '<i class="fa-solid fa-chart-pie"></i>',
        'users'         => '<i class="fa-solid fa-users-gear"></i>',
        'clubs'         => '<i class="fa-solid fa-people-group"></i>',
        'news'          => '<i class="fa-solid fa-newspaper"></i>',
        'announcements' => '<i class="fa-solid fa-bullhorn"></i>',
        default         => '<i class="fa-solid fa-circle"></i>'
    };
}

function admin_header(string $title, string $subtitle, string $active): void {
    global $adminName, $csrf;
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
    <link rel="stylesheet" href="../assets/css/admin_officialnews.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<?php 
@include __DIR__ . '/../includes/header.php'; 
@include __DIR__ . '/../includes/navbar.php';
?>

<div class="app-shell">
    <aside class="sidebar">
        <div class="side-heading"><small>PLATFORM ADMINISTRATION</small><strong>NSBM 360</strong><span>ADMINISTRATOR</span></div>
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
            <form method="post"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><button type="submit" class="signout" name="action" value="logout"><i class="fa-solid fa-right-from-bracket"></i>Sign out</button></form>
        </div>
    </aside>
    <main class="main-content">
        <div class="breadcrumbs">Home / Administration / <?= e($title) ?></div>
        <div class="page-heading"><div><h1><?= e($title) ?></h1><p><?= e($subtitle) ?></p></div></div>
    <?php
}

function admin_footer(): void {
    ?>
    </main>
</div>
<footer class="site-footer">
    <?php @include __DIR__ . '/../includes/footer.php'; ?>
</footer>
<script src="../assets/js/admin_officialnews.js"></script>
</body>
</html>
    <?php
}

admin_header('Official News', 'Manage campus news, events, sports, academic notices, and club posts.', 'news');
?>

<!-- Action Buttons -->
<div class="page-actions"><a class="btn btn-green" href="admin_officialnews.php?new=1#post-form">＋ Create New Post</a></div>

<?php if ($error): ?><div class="message error"><?= e($error) ?></div><?php endif; ?>
<?php if (isset($_GET['ok'])): ?><div class="message"><?= e($_GET['ok']) ?></div><?php endif; ?>

<!-- Search & Category Filter Bar -->
<form class="panel filters" method="get">
    <div class="field field-grow"><label>Search Posts</label><input type="text" name="search" placeholder="Search title or content..." value="<?= e($search) ?>"></div>
    <div class="field field-select">
        <label>Filter Category</label>
        <select name="category_id">
            <option value="">All Categories</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['category_id'] ?>" <?= $filterCategory == $cat['category_id'] ? 'selected' : '' ?>><?= e($cat['category_name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button class="btn btn-green">Filter</button>
</form>

<div class="toolbar"><span><?= $total ?> posts matching filters</span><span>Sort by: Newest first</span></div>

<!-- Posts Data Table -->
<div class="table-scroll"><table>
<thead>
    <tr>
        <th>Cover</th>
        <th>Title & Category</th>
        <th>Faculty / Club</th>
        <th>Author</th>
        <th>Status</th>
        <th>Created Date</th>
        <th>Actions</th>
    </tr>
</thead>
<tbody>
<?php foreach ($postsList as $p): ?>
    <tr>
        <td><?php if (!empty($p['cover_image'])): ?><img src="<?= e($p['cover_image']) ?>" alt="Cover" class="table-thumb"><?php else: ?><span class="no-thumb">No Image</span><?php endif; ?></td>
        <td>
            <div class="table-title"><?= e($p['title']) ?></div>
            <div class="table-subtitle"><?= e($p['category_name']) ?> > <?= e($p['subcategory_name'] ?? 'General') ?></div>
        </td>
        <td>
            Faculty: <?= e($p['faculty_name'] ?? 'All Faculties') ?><br>
            <span class="table-subtitle"><?= e($p['club_name'] ?? 'Official University Post') ?></span>
        </td>
        <td><?= e($p['author']) ?></td>
        <td><span class="badge <?= $p['status'] === 'published' ? 'green' : ($p['status'] === 'archived' ? 'blue' : 'gray') ?>"><?= e(ucfirst($p['status'])) ?></span></td>
        <td><?= e(display_date($p['created_at'])) ?></td>
        <td class="actions">
            <a href="?view=<?= (int)$p['post_id'] ?>#post-preview">View</a> · 
            <a href="?edit=<?= (int)$p['post_id'] ?>#post-form">Edit</a> · 
            <form method="post" class="inline-form" onsubmit="return confirm('Are you sure you want to delete this post?');">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="post_id" value="<?= (int)$p['post_id'] ?>">
                <button type="submit" name="action" value="delete_post" class="danger">Delete</button>
            </form>
        </td>
    </tr>
<?php endforeach; ?>
<?php if (!$postsList): ?><tr><td class="empty" colspan="7">No posts found.</td></tr><?php endif; ?>
</tbody></table></div>

<div class="pagination-bar"><span>Showing <?= $total ? $offset+1:0 ?>–<?= min($offset+$perPage,$total) ?> of <?= $total ?> posts</span><?php pagination($page,$total,$perPage); ?></div>

<!-- Post Preview Section -->
<?php if ($selectedPost && isset($_GET['view'])): ?>
<section class="panel" id="post-preview">
    <div class="preview-header">
        <h2><?= e($selectedPost['title']) ?></h2>
        <span class="badge <?= $selectedPost['status'] === 'published' ? 'green' : ($selectedPost['status'] === 'archived' ? 'blue' : 'gray') ?>"><?= e(ucfirst($selectedPost['status'])) ?></span>
    </div>
    <p class="small">
        By <?= e($selectedPost['author']) ?><br>
        Category: <?= e($selectedPost['category_name']) ?> (<?= e($selectedPost['subcategory_name'] ?? 'General') ?>)<br>
        Faculty: <?= e($selectedPost['faculty_name'] ?? 'All Faculties') ?><br>
        Club: <?= e($selectedPost['club_name'] ?? 'Official University Post') ?><br>
        <?php if (!empty($selectedPost['sport_name'])): ?>Sport: <?= e($selectedPost['sport_name']) ?><br><?php endif; ?>
        Created: <?= e(display_date($selectedPost['created_at'])) ?> | Updated: <?= e(display_date($selectedPost['updated_at'])) ?>
    </p>

    <?php if (!empty($selectedPost['summary'])): ?>
        <p class="preview-summary"><strong>Summary:</strong> <?= e($selectedPost['summary']) ?></p>
    <?php endif; ?>

    <?php if ($selectedPost['category_name'] === 'Events' && !empty($selectedPost['start_at'])): ?>
        <div class="event-preview-box">
            <strong>Event Details:</strong><br>
            Start: <?= e(display_date($selectedPost['start_at'])) ?> | End: <?= e(display_date($selectedPost['end_at'])) ?><br>
            Venue: <?= e($selectedPost['venue']) ?>
            <?php if (!empty($selectedPost['registration_deadline'])): ?><br>Registration Deadline: <?= e(display_date($selectedPost['registration_deadline'])) ?><?php endif; ?>
            <?php if (!empty($selectedPost['registration_link'])): ?><br>Registration Link: <a href="<?= e($selectedPost['registration_link']) ?>" target="_blank"><?= e($selectedPost['registration_link']) ?></a><?php endif; ?>
        </div>
    <?php endif; ?>

    <p><?= nl2br(e($selectedPost['content'])) ?></p>

    <?php if (!empty($selectedPost['cover_image'])): ?>
        <div class="preview-cover-wrap">
            <strong>Cover Image:</strong><br>
            <img class="preview-image" src="<?= e($selectedPost['cover_image']) ?>" alt="Cover image">
        </div>
    <?php endif; ?>

    <?php if (!empty($selectedPostImages)): ?>
        <div class="preview-gallery-wrap">
            <strong>Additional Images:</strong>
            <div class="preview-gallery">
                <?php foreach ($selectedPostImages as $img): ?>
                    <img src="<?= e($img['image_path']) ?>" alt="<?= e($img['alt_text']) ?>" class="gallery-thumb">
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</section>
<?php endif; ?>

<!-- Post Create & Edit Form -->
<div class="two-cols wide-left form-layout-gap">
    <section class="panel" id="post-form">
        <h2><?= $selectedPost ? 'Edit Post' : 'Create New Post' ?></h2>
        <p class="small"><?= $selectedPost ? 'Editing: '.e($selectedPost['title']) : 'Publish a new post to the platform.' ?></p>
        
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="action" value="save_post">
            <input type="hidden" name="post_id" value="<?= (int)($selectedPost['post_id'] ?? 0) ?>">

            <div class="field">
                <label>Post Title *</label>
                <input type="text" name="title" required maxlength="255" value="<?= e($selectedPost['title'] ?? '') ?>">
            </div>

            <div class="field-row">
                <div class="field field-half">
                    <label>Main Category *</label>
                    <select name="category_id" id="category_select" required>
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['category_id'] ?>" <?= isset($selectedPost['category_id']) && $selectedPost['category_id'] == $cat['category_id'] ? 'selected' : '' ?>><?= e($cat['category_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field field-half">
                    <label>Subcategory *</label>
                    <select name="subcategory_id" id="subcategory_select" required>
                        <option value="">Select Subcategory</option>
                        <?php foreach ($subcategories as $sub): ?>
                            <option value="<?= $sub['subcategory_id'] ?>" data-cat="<?= $sub['category_id'] ?>" <?= isset($selectedPost['subcategory_id']) && $selectedPost['subcategory_id'] == $sub['subcategory_id'] ? 'selected' : '' ?>><?= e($sub['subcategory_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="field-row">
                <div class="field field-half">
                    <label>Faculty Selection</label>
                    <select name="faculty_id">
                        <option value="">All Faculties (General)</option>
                        <?php foreach ($faculties as $fac): ?>
                            <option value="<?= $fac['faculty_id'] ?>" <?= isset($selectedPost['faculty_id']) && $selectedPost['faculty_id'] == $fac['faculty_id'] ? 'selected' : '' ?>><?= e($fac['faculty_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field field-half">
                    <label>Organizing Club / Related Club</label>
                    <select name="club_id">
                        <option value="">No Club / Official University Post</option>
                        <?php foreach ($clubs as $club): ?>
                            <option value="<?= $club['club_id'] ?>" <?= isset($selectedPost['club_id']) && $selectedPost['club_id'] == $club['club_id'] ? 'selected' : '' ?>><?= e($club['club_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="field">
                <label>Sport (Required if Sport Category / Sports Event)</label>
                <select name="sport_id" id="sport_select">
                    <option value="">Select Sport (Optional)</option>
                    <?php foreach ($sports as $sport): ?>
                        <option value="<?= $sport['sport_id'] ?>" <?= isset($selectedPost['sport_id']) && $selectedPost['sport_id'] == $sport['sport_id'] ? 'selected' : '' ?>><?= e($sport['sport_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label>Summary</label>
                <textarea name="summary" rows="2"><?= e($selectedPost['summary'] ?? '') ?></textarea>
            </div>

            <div class="field">
                <label>Content / Description *</label>
                <textarea name="content" rows="6" required><?= e($selectedPost['content'] ?? '') ?></textarea>
            </div>

            <!-- Event Specific Extra Fields Container -->
            <div id="event_fields" class="event-fields-box">
                <h3>Event Specific Details</h3>
                <div class="field-row">
                    <div class="field field-half">
                        <label>Event Start Date & Time *</label>
                        <input type="datetime-local" name="start_at" value="<?= !empty($selectedPost['start_at']) ? e(date('Y-m-d\TH:i', strtotime($selectedPost['start_at']))) : '' ?>">
                    </div>
                    <div class="field field-half">
                        <label>Event End Date & Time *</label>
                        <input type="datetime-local" name="end_at" value="<?= !empty($selectedPost['end_at']) ? e(date('Y-m-d\TH:i', strtotime($selectedPost['end_at']))) : '' ?>">
                    </div>
                </div>
                <div class="field-row">
                    <div class="field field-half">
                        <label>Venue *</label>
                        <input type="text" name="venue" value="<?= e($selectedPost['venue'] ?? '') ?>">
                    </div>
                    <div class="field field-half">
                        <label>Registration Deadline</label>
                        <input type="datetime-local" name="registration_deadline" value="<?= !empty($selectedPost['registration_deadline']) ? e(date('Y-m-d\TH:i', strtotime($selectedPost['registration_deadline']))) : '' ?>">
                    </div>
                </div>
                <div class="field">
                    <label>Registration Link</label>
                    <input type="url" name="registration_link" value="<?= e($selectedPost['registration_link'] ?? '') ?>">
                </div>
            </div>

            <div class="field">
                <label>Cover Image (optional)</label>
                <?php if (!empty($selectedPost['cover_image'])): ?>
                    <div class="current-image-box">
                        <img src="<?= e($selectedPost['cover_image']) ?>" alt="Cover" class="current-cover-preview">
                        <span class="small">Current: <?= e(basename($selectedPost['cover_image'])) ?></span>
                    </div>
                <?php endif; ?>
                <input type="file" name="cover_image" accept="image/png, image/jpeg, image/webp">
            </div>

            <!-- Uploaded Additional Images (Shown before the upload field) -->
            <?php if (!empty($selectedPostImages)): ?>
                <div class="field">
                    <label>Uploaded Additional Images</label>
                    <div class="additional-images-grid">
                        <?php foreach ($selectedPostImages as $img): ?>
                            <div class="additional-img-card">
                                <img src="<?= e($img['image_path']) ?>" alt="Additional image">
                                <button type="submit" form="delete-img-<?= (int)$img['image_id'] ?>" class="btn-remove-img">Remove</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="field">
                <label>Additional Images (optional, multiple allowed)</label>
                <input type="file" name="additional_images[]" accept="image/png, image/jpeg, image/webp" multiple>
            </div>

            <div class="field">
                <label>Status</label>
                <select name="status">
                    <option value="draft" <?= ($selectedPost['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>Draft</option>
                    <option value="published" <?= ($selectedPost['status'] ?? '') === 'published' ? 'selected' : '' ?>>Published</option>
                    <option value="archived" <?= ($selectedPost['status'] ?? '') === 'archived' ? 'selected' : '' ?>>Archived</option>
                </select>
            </div>

            <div class="form-buttons">
                <button class="btn btn-green" name="submit_status" value="published">Publish post</button>
                <button class="btn" name="submit_status" value="draft">Save draft</button>
                <button class="btn" type="submit">Save selected status</button>
            </div>
        </form>

        <!-- Hidden Standalone Forms for Removing Individual Additional Images -->
        <?php if (!empty($selectedPostImages)): ?>
            <?php foreach ($selectedPostImages as $img): ?>
                <form id="delete-img-<?= (int)$img['image_id'] ?>" method="post" onsubmit="return confirm('Remove this image?');">
                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="post_id" value="<?= (int)$selectedPost['post_id'] ?>">
                    <input type="hidden" name="image_id" value="<?= (int)$img['image_id'] ?>">
                    <input type="hidden" name="action" value="delete_post_image">
                </form>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <!-- Sidebar Guidelines -->
    <aside class="panel">
        <h2>Post Guidelines</h2>
        <?php if ($selectedPost): ?>
            <p><span class="badge <?= $selectedPost['status'] === 'published' ? 'green' : ($selectedPost['status'] === 'archived' ? 'blue' : 'gray') ?>"><?= e(ucfirst($selectedPost['status'])) ?></span></p>
            <p class="small">
                Created by <?= e($selectedPost['author']) ?><br>
                Created: <?= e(display_date($selectedPost['created_at'])) ?><br>
                Published: <?= e(display_date($selectedPost['published_at'] ?? null)) ?><br>
                Updated: <?= e(display_date($selectedPost['updated_at'] ?? null)) ?>
            </p>
            <p><a href="?view=<?= (int)$selectedPost['post_id'] ?>#post-preview">Preview post →</a></p>
        <?php endif; ?>
        <p class="small">Administrators can directly publish posts across any category, faculty, or club without requiring club president approval.</p>
        <div class="info"><strong>Events Note</strong><br>Selecting the 'Events' category automatically reveals the venue and date/time fields required for event publication.</div>
    </aside>
</div>

<?php admin_footer(); ?>
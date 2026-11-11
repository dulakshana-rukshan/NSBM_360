<?php
// Include your team's shared database connection (provides $pdo)
require_once __DIR__ . '/../config/database.php';
/* Common page setup: session, security and layout helpers. */
session_start();

// Only a logged-in administrator may access these pages.
$adminId = (int) ($_SESSION['user_id'] ?? 0);
if ($adminId < 1) {
    http_response_code(401);
    exit('Please sign in as an administrator. Your login code must set $_SESSION["user_id"].');
}

// --- PDO HELPER FUNCTIONS ---

function rows(PDO $pdo, string $sql, array $values = []): array
{
    // Runs a prepared SELECT query and returns all matching rows.
    $stmt = $pdo->prepare($sql);
    $stmt->execute($values);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function run_sql(PDO $pdo, string $sql, array $values = []): void
{
    // Runs an INSERT, UPDATE or DELETE query with safe parameters.
    $stmt = $pdo->prepare($sql);
    $stmt->execute($values);
}

function count_sql(PDO $pdo, string $sql, array $values = []): int
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($values);
    return (int) ($stmt->fetchColumn() ?? 0);
}

function e($value): string
{
    // Prevents stored HTML/script text from being executed in the browser.
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function display_date(?string $date): string
{
    return $date ? date('d M Y', strtotime($date)) : 'Not set';
}

function goto_page(string $file, string $message = '', array $extra = []): void
{
    if ($message !== '') {
        $extra['ok'] = $message;
    }
    header('Location: ' . $file . ($extra ? '?' . http_build_query($extra) : ''));
    exit;
}

$adminRecord = rows($pdo, 'SELECT first_name, last_name, role FROM users WHERE user_id = ?', [$adminId]);
if (!$adminRecord || $adminRecord[0]['role'] !== 'admin') {
    http_response_code(403);
    exit('Access denied. Administrator account required.');
}
$adminName = $adminRecord[0]['first_name'];

// CSRF token protects forms that change information.
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

function pagination(int $page, int $total, int $perPage): void
{
    // Retains active search filters when moving between pages.
    $pages = max(1, (int) ceil($total / $perPage));
    if ($pages <= 1) return;
    $params = $_GET;
    echo '<nav class="pagination" aria-label="Table pages">';
    for ($p = max(1, $page - 2); $p <= min($pages, $page + 2); $p++) {
        $params['page'] = $p;
        $link = '?' . http_build_query($params);
        echo '<a class="' . ($p === $page ? 'current' : '') . '" href="' . e($link) . '">' . $p . '</a>';
    }
    echo '</nav>';
}

function admin_header(string $title, string $subtitle, string $active, string $css): void
{
    global $adminName, $csrf;
    $nav = [
        'admin_overview.php' => ['Overview', 'overview'],
        'admin_users_role_requests.php' => ['Users & role requests', 'users'],
        'admin_clubs.php' => ['Clubs & leadership', 'clubs'],
        'admin_officialnews.php' => ['Official news', 'news'],
        'admin_announcements.php' => ['Announcements', 'announcements'],
    ];
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> | NSBM 360 Admin</title>
    <link rel="stylesheet" href="../assets/css/admin_announcements.css">
</head>
<body>

<?php 
// Include the shared public/site header component
include 'C:\xampp\htdocs\NSBM_360\includes\header.php'; 
include 'C:\xampp\htdocs\NSBM_360\includes\navbar.php';
?>

<div class="app-shell">
    <aside class="sidebar">
        <div class="side-heading"><small>PLATFORM ADMINISTRATION</small><strong>NSBM 360</strong><span>ADMINISTRATOR</span></div>
        <nav class="side-nav" aria-label="Administration">
            <?php foreach ($nav as $file => $item): ?>
                <a class="<?= $active === $item[1] ? 'active' : '' ?>" href="<?= e($file) ?>"><?= e($item[0]) ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-bottom">
            <p>Manage platform accounts, club assignments and official campus content.</p>
            <a href="index.php">↗ View public site</a>
            <form method="post"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><button type="submit" class="signout" name="action" value="logout">Sign out</button></form>
        </div>
    </aside>
    <main class="main-content">
        <div class="breadcrumbs">Home / Administration / <?= e($title) ?></div>
        <div class="page-heading"><div><h1><?= e($title) ?></h1><p><?= e($subtitle) ?></p></div></div>
<?php
}

function admin_footer(): void
{
    ?>
    </main>
</div>
<footer class="site-footer">
    <?php
    include '..\includes\footer.php';
    ?>
</footer>
</body>
</html>
<?php
}
/* Announcements: create, update, publish, archive, view and delete notices. */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $postAllowed) {
    try {
        $action = $_POST['action'] ?? '';
        if ($action === 'save_announcement') {
            $id = (int) ($_POST['announcement_id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $content = trim($_POST['content'] ?? '');
            $image = trim($_POST['image'] ?? '');
            $status = $_POST['submit_status'] ?? $_POST['status'] ?? 'draft';
            $expiryInput = trim($_POST['expires_at'] ?? '');
            $expiry = null;
            if ($expiryInput !== '') {
                $timestamp = strtotime($expiryInput);
                if ($timestamp === false) throw new Exception('Please enter a valid expiry date.');
                $expiry = date('Y-m-d H:i:s', $timestamp);
            }
            if ($title === '' || $content === '' || !in_array($status, ['draft', 'published', 'archived'], true)) {
                throw new Exception('Title, content and a valid status are required.');
            }
            if ($id > 0) {
                run_sql($pdo, 'UPDATE announcements SET title=?, content=?, image=?, status=?, expires_at=? WHERE announcement_id=?',
                    [$title, $content, $image ?: null, $status, $expiry, $id]);
            } else {
                run_sql($pdo, 'INSERT INTO announcements (created_by, title, content, image, status, expires_at) VALUES (?, ?, ?, ?, ?, ?)',
                    [$adminId, $title, $content, $image ?: null, $status, $expiry]);
                $id = (int) $pdo->lastInsertId();
            }
            goto_page('admin_announcements.php', 'Announcement saved successfully.', ['edit' => $id]);
        }
        if ($action === 'delete_announcement') {
            $id = (int) ($_POST['announcement_id'] ?? 0);
            if ($id < 1) throw new Exception('Invalid announcement.');
            run_sql($pdo, 'DELETE FROM announcements WHERE announcement_id=?', [$id]);
            goto_page('admin_announcements.php', 'Announcement deleted.');
        }
    } catch (Throwable $exception) {
        $error = $exception instanceof PDOException ? 'Could not save the announcement. Check your data.' : $exception->getMessage();
        error_log($exception->getMessage());
    }
}

// The database supports draft, published, archived and an expiry date.
$search = trim($_GET['search'] ?? '');
$filter = $_GET['status'] ?? '';
$where = [];
$values = [];
if ($search !== '') {
    $where[] = '(a.title LIKE ? OR a.content LIKE ?)';
    $values[] = '%' . $search . '%';
    $values[] = '%' . $search . '%';
}
if ($filter === 'expired') {
    $where[] = 'a.status="published" AND a.expires_at IS NOT NULL AND a.expires_at < NOW()';
} elseif (in_array($filter, ['published', 'draft', 'archived'], true)) {
    $where[] = 'a.status=?';
    $values[] = $filter;
}
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$perPage = 6;
$page = max(1, (int) ($_GET['page'] ?? 1));
$total = count_sql($pdo, 'SELECT COUNT(*) AS total FROM announcements a' . $whereSql, $values);
$page = min($page, max(1, (int) ceil($total / $perPage)));
$offset = ($page - 1) * $perPage;

$noticesQuery = 'SELECT a.*, CONCAT(u.first_name, " ", u.last_name) AS author FROM announcements a
    JOIN users u ON u.user_id=a.created_by' . $whereSql . ' ORDER BY a.created_at DESC, a.announcement_id DESC LIMIT ? OFFSET ?';

$queryParams = array_merge($values, [$perPage, $offset]);
$stmtNotices = $pdo->prepare($noticesQuery);
$i = 1;
foreach ($queryParams as $val) {
    if (is_int($val)) {
        $stmtNotices->bindValue($i, $val, PDO::PARAM_INT);
    } else {
        $stmtNotices->bindValue($i, $val, PDO::PARAM_STR);
    }
    $i++;
}
$stmtNotices->execute();
$notices = $stmtNotices->fetchAll(PDO::FETCH_ASSOC);

$stats = rows($pdo, 'SELECT COUNT(*) AS total, SUM(status="published") AS published,
    SUM(status="draft") AS draft, SUM(status="archived") AS archived,
    SUM(status="published" AND expires_at IS NOT NULL AND expires_at < NOW()) AS expired FROM announcements')[0];

$selectedId = (int) ($_GET['edit'] ?? $_GET['view'] ?? ($notices[0]['announcement_id'] ?? 0));
$selected = (!isset($_GET['new']) && $selectedId > 0)
    ? (rows($pdo, 'SELECT a.*, CONCAT(u.first_name, " ", u.last_name) AS author FROM announcements a JOIN users u ON a.created_by=u.user_id WHERE a.announcement_id=?', [$selectedId])[0] ?? null)
    : null;

admin_header('Announcements', 'Timely campus notices, published by administrators.', 'announcements', 'admin_announcements.css');
?>
<div class="page-actions"><a class="btn btn-green" href="admin_announcements.php?new=1#announcement-form">＋ Create announcement</a></div>
<?php if ($error): ?><div class="message error"><?= e($error) ?></div><?php endif; ?>
<?php if (isset($_GET['ok'])): ?><div class="message"><?= e($_GET['ok']) ?></div><?php endif; ?>
<div class="tabs">
    <?php foreach ([''=>'All notices', 'published'=>'Published', 'draft'=>'Drafts', 'archived'=>'Archived', 'expired'=>'Expired'] as $key=>$label): ?>
        <?php $number = $key === '' ? $stats['total'] : $stats[$key]; ?>
        <a class="<?= $filter===$key?'active':'' ?>" href="?<?= http_build_query(['status'=>$key]) ?>"><?= e($label) ?> <?= (int)$number ?></a>
    <?php endforeach; ?>
</div>
<form class="panel filters" method="get">
    <div class="field field-grow"><label>Search announcements</label><input name="search" placeholder="Search announcement title or content" value="<?= e($search) ?>"></div>
    <div class="field field-select"><label>Status</label><select name="status"><option value="">All statuses</option><?php foreach (['published','draft','archived','expired'] as $s): ?><option value="<?= e($s) ?>" <?= $filter===$s?'selected':'' ?>><?= ucfirst($s) ?></option><?php endforeach; ?></select></div>
    <button class="btn btn-green">Search</button>
</form>
<div class="toolbar"><span><?= $total ?> announcements matching filters</span><span>Sort by: Newest first</span></div>
<div class="table-scroll"><table>
<thead><tr><th>Announcement</th><th>Created by</th><th>Status</th><th>Created / expiry</th><th>Actions</th></tr></thead>
<tbody>
<?php foreach ($notices as $notice):
    $expired = $notice['status']==='published' && $notice['expires_at'] && strtotime($notice['expires_at']) < time(); ?>
<tr>
    <td><div class="table-title"><?= e($notice['title']) ?></div><div class="table-subtitle"><?= e(substr((string)$notice['content'], 0, 100)) ?></div></td>
    <td><?= e($notice['author']) ?></td>
    <td><span class="badge <?= $expired ? 'yellow' : ($notice['status']==='draft'?'gray':($notice['status']==='archived'?'blue':'')) ?>"><?= e($expired ? 'Expired' : $notice['status']) ?></span></td>
    <td><?= e(display_date($notice['created_at'])) ?><div class="table-subtitle">Expires: <?= e(display_date($notice['expires_at'])) ?></div></td>
    <td class="actions"><a href="?view=<?= (int)$notice['announcement_id'] ?>#announcement-preview">View</a> · <a href="?edit=<?= (int)$notice['announcement_id'] ?>#announcement-form">Edit</a> · <form method="post" class="inline-form" onsubmit="return confirm('Delete this announcement?');"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="announcement_id" value="<?= (int)$notice['announcement_id'] ?>"><button class="danger" name="action" value="delete_announcement">Delete</button></form></td>
</tr>
<?php endforeach; ?>
<?php if (!$notices): ?><tr><td class="empty" colspan="5">No announcements found.</td></tr><?php endif; ?>
</tbody></table></div>
<div class="pagination-bar"><span>Showing <?= $total ? $offset+1:0 ?>–<?= min($offset+$perPage,$total) ?> of <?= $total ?> notices</span><?php pagination($page,$total,$perPage); ?></div>
<?php if ($selected && isset($_GET['view'])): ?>
<section class="panel" id="announcement-preview"><h2><?= e($selected['title']) ?></h2><p class="small">By <?= e($selected['author']) ?> · <?= e(display_date($selected['created_at'])) ?></p><p><?= nl2br(e($selected['content'])) ?></p><?php if ($selected['image']): ?><p><img class="preview-image" src="<?= e($selected['image']) ?>" alt="Announcement image"></p><?php endif; ?></section>
<?php endif; ?>
<div class="two-cols wide-left">
    <section class="panel" id="announcement-form"><h2><?= $selected ? 'Edit announcement' : 'Create announcement' ?></h2>
        <p class="small"><?= $selected ? 'Selected: '.e($selected['title']) : 'Write a new campus announcement.' ?></p>
        <form method="post"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="save_announcement"><input type="hidden" name="announcement_id" value="<?= (int)($selected['announcement_id'] ?? 0) ?>">
            <div class="field"><label>Title *</label><input name="title" required maxlength="255" value="<?= e($selected['title'] ?? '') ?>"></div>
            <div class="field"><label>Announcement content *</label><textarea rows="6" name="content" required><?= e($selected['content'] ?? '') ?></textarea></div>
            <div class="field"><label>Image path (optional)</label><input maxlength="255" name="image" placeholder="uploads/announcements/image.jpg" value="<?= e($selected['image'] ?? '') ?>"></div>
            <div class="field"><label>Expiry date (optional)</label><input type="datetime-local" name="expires_at" value="<?= $selected && $selected['expires_at'] ? e(date('Y-m-d\TH:i',strtotime($selected['expires_at']))) : '' ?>"></div>
            <div class="field"><label>Status</label><select name="status"><?php foreach (['draft','published','archived'] as $state): ?><option value="<?= e($state) ?>" <?= ($selected['status']??'draft')===$state?'selected':'' ?>><?= ucfirst($state) ?></option><?php endforeach; ?></select></div>
            <div class="form-buttons"><button class="btn" name="submit_status" value="draft">Save draft</button><button class="btn btn-green" name="submit_status" value="published">Publish announcement</button><button class="btn" type="submit">Save selected status</button></div>
        </form>
    </section>
    <aside class="panel"><h2>Publication settings</h2>
        <?php if ($selected): ?>
            <p><span class="badge <?= $selected['status']==='draft'?'gray':'' ?>"><?= e($selected['status']) ?></span></p>
            <p class="small">Created by <?= e($selected['author']) ?><br>Created <?= e(display_date($selected['created_at'])) ?><br>Expires <?= e(display_date($selected['expires_at'])) ?></p>
            <p><a href="?view=<?= (int)$selected['announcement_id'] ?>#announcement-preview">Preview announcement →</a></p>
            <form method="post" onsubmit="return confirm('Permanently delete this announcement?');"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="announcement_id" value="<?= (int)$selected['announcement_id'] ?>"><button class="btn btn-red" name="action" value="delete_announcement">Delete announcement</button></form>
        <?php else: ?><p class="small">Save an announcement to see its publication details.</p><?php endif; ?>
        <div class="info"><strong>Keep notices useful</strong><br>Set an expiry date for time-sensitive notices. Expired notices remain in management history.</div>
    </aside>
</div>
<?php admin_footer(); ?>
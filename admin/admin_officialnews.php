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
    <link rel="stylesheet" href="../assets/css/admin_officialnews.css">
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
/* Official news: manage posts created by admins with no club_id. */
/* Note: the schema has no official-news flag or scheduled status. */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $postAllowed) {
    try {
        $action = $_POST['action'] ?? '';
        if ($action === 'save_post') {
            $id = (int)($_POST['post_id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $summary = trim($_POST['summary'] ?? '');
            $content = trim($_POST['content'] ?? '');
            $cover = trim($_POST['cover_image'] ?? '');
            $categoryId = (int)($_POST['category_id'] ?? 0);
            $status = $_POST['submit_status'] ?? $_POST['status'] ?? 'draft';
            if (!$title || !$content || !in_array($status,['draft','published','archived'],true)) {
                throw new Exception('Title, content and a valid status are required.');
            }
            if (!rows($pdo, 'SELECT category_id FROM categories WHERE category_id=?', [$categoryId])) {
                throw new Exception('Choose a category from your categories table.');
            }
            if ($id > 0) {
                // Editing is limited to official posts; existing club posts are protected.
                $existing = rows($pdo, 'SELECT p.published_at FROM posts p JOIN users u ON u.user_id=p.created_by WHERE p.post_id=? AND p.club_id IS NULL AND u.role="admin"', [$id])[0] ?? null;
                if (!$existing) throw new Exception('Official post was not found.');
                $publishedAt = $status === 'published' ? ($existing['published_at'] ?: date('Y-m-d H:i:s'))
                    : ($status === 'archived' ? $existing['published_at'] : null);
                run_sql($pdo, 'UPDATE posts SET title=?,summary=?,content=?,cover_image=?,category_id=?,status=?,published_at=? WHERE post_id=? AND club_id IS NULL',
                    [$title, $summary ?: null, $content, $cover ?: null, $categoryId, $status, $publishedAt, $id]);
            } else {
                $publishedAt = $status === 'published' ? date('Y-m-d H:i:s') : null;
                run_sql($pdo, 'INSERT INTO posts (created_by,club_id,category_id,title,summary,content,cover_image,status,published_at)
                    VALUES (?,NULL,?,?,?,?,?,?,?)',
                    [$adminId, $categoryId, $title, $summary ?: null, $content, $cover ?: null, $status, $publishedAt]);
                $id = (int)$pdo->lastInsertId();
            }
            goto_page('admin_officialnews.php', 'Official news post saved.', ['edit'=>$id]);
        }
        if ($action === 'delete_post' || $action === 'publish_post') {
            $id = (int)($_POST['post_id'] ?? 0);
            $valid = rows($pdo, 'SELECT p.post_id FROM posts p JOIN users u ON u.user_id=p.created_by WHERE p.post_id=? AND p.club_id IS NULL AND u.role="admin"', [$id]);
            if (!$valid) throw new Exception('Official post not found.');
            if ($action === 'delete_post') {
                // post_images/events with this post_id are deleted by database CASCADE rules.
                run_sql($pdo, 'DELETE FROM posts WHERE post_id=? AND club_id IS NULL', [$id]);
                goto_page('admin_officialnews.php','Official post deleted.');
            }
            run_sql($pdo, 'UPDATE posts SET status="published",published_at=COALESCE(published_at,NOW()) WHERE post_id=? AND club_id IS NULL', [$id]);
            goto_page('admin_officialnews.php','Official post published.');
        }
    } catch (Throwable $exception) {
        $error = $exception instanceof PDOException ? 'Could not change the official post. Check required data and category.' : $exception->getMessage();
        error_log($exception->getMessage());
    }
}

$search = trim($_GET['search'] ?? '');
$category = (int)($_GET['category'] ?? 0);
$filter = $_GET['status'] ?? '';
$where = ['p.club_id IS NULL','u.role="admin"'];
$values = [];
if ($search !== '') {
    $where[] = '(p.title LIKE ? OR p.summary LIKE ? OR CONCAT(u.first_name," ",u.last_name) LIKE ?)';
    array_push($values, '%'.$search.'%', '%'.$search.'%', '%'.$search.'%');
}
if ($category > 0) { $where[]='p.category_id=?'; $values[]=$category; }
if (in_array($filter,['draft','published','archived'],true)) { $where[]='p.status=?'; $values[]=$filter; }
$whereSql = ' WHERE '.implode(' AND ',$where);
$perPage=6;
$page=max(1,(int)($_GET['page']??1));
$total=count_sql($pdo, 'SELECT COUNT(*) AS total FROM posts p JOIN users u ON u.user_id=p.created_by'.$whereSql, $values);
$page=min($page,max(1,(int)ceil($total/$perPage)));
$offset=($page-1)*$perPage;

$postsQuery = 'SELECT p.*,c.category_name,CONCAT(u.first_name," ",u.last_name) AS author
    FROM posts p JOIN users u ON u.user_id=p.created_by JOIN categories c ON c.category_id=p.category_id'.$whereSql.
    ' ORDER BY p.updated_at DESC,p.post_id DESC LIMIT ? OFFSET ?';

$queryParams = array_merge($values, [$perPage, $offset]);
$stmtPosts = $pdo->prepare($postsQuery);
$i = 1;
foreach ($queryParams as $val) {
    if (is_int($val)) {
        $stmtPosts->bindValue($i, $val, PDO::PARAM_INT);
    } else {
        $stmtPosts->bindValue($i, $val, PDO::PARAM_STR);
    }
    $i++;
}
$stmtPosts->execute();
$posts = $stmtPosts->fetchAll(PDO::FETCH_ASSOC);

$stats = rows($pdo, 'SELECT COUNT(*) AS total,SUM(p.status="published") AS published,SUM(p.status="draft") AS draft,SUM(p.status="archived") AS archived
    FROM posts p JOIN users u ON u.user_id=p.created_by WHERE p.club_id IS NULL AND u.role="admin"')[0];
$categories = rows($pdo,'SELECT category_id,category_name FROM categories ORDER BY category_name');
$selectedId = (int)($_GET['edit'] ?? $_GET['view'] ?? ($posts[0]['post_id']??0));
$selected = (!isset($_GET['new']) && $selectedId>0)
    ? (rows($pdo, 'SELECT p.*,c.category_name,CONCAT(u.first_name," ",u.last_name) AS author FROM posts p JOIN users u ON p.created_by=u.user_id JOIN categories c ON c.category_id=p.category_id WHERE p.post_id=? AND p.club_id IS NULL AND u.role="admin"', [$selectedId])[0]??null)
    : null;
admin_header('Official news', 'Publish trusted university stories and academic updates for the campus community.', 'news', 'admin_officialnews.css');
?>
<div class="page-actions"><a class="btn btn-green" href="admin_officialnews.php?new=1#post-form">＋ Create official post</a></div>
<?php if ($error): ?><div class="message error"><?= e($error) ?></div><?php endif; ?>
<?php if (isset($_GET['ok'])): ?><div class="message"><?= e($_GET['ok']) ?></div><?php endif; ?>
<div class="info"><strong>Official university content</strong><br>These are administrator-created posts not assigned to clubs. Club leaders manage their own club's posts.</div>
<div class="tabs">
<?php foreach([''=>'All official posts','published'=>'Published','draft'=>'Drafts','archived'=>'Archived'] as $key=>$label): ?>
<a href="?<?= http_build_query(['status'=>$key]) ?>" class="<?= $filter===$key?'active':'' ?>"><?= e($label) ?> <?= (int)($key===''?$stats['total']:$stats[$key]) ?></a>
<?php endforeach; ?>
</div>
<form method="get" class="panel filters">
    <div class="field field-grow"><label>Search official news</label><input name="search" placeholder="Search by title, summary or author" value="<?= e($search) ?>"></div>
    <div class="field field-select"><label>Category</label><select name="category"><option value="0">All categories</option><?php foreach($categories as $cat): ?><option value="<?= (int)$cat['category_id'] ?>" <?= $category===(int)$cat['category_id']?'selected':'' ?>><?= e($cat['category_name']) ?></option><?php endforeach; ?></select></div>
    <div class="field field-select"><label>Status</label><select name="status"><option value="">All statuses</option><?php foreach(['published','draft','archived'] as $s): ?><option value="<?= e($s) ?>" <?= $filter===$s?'selected':'' ?>><?= ucfirst($s) ?></option><?php endforeach; ?></select></div>
    <button class="btn btn-green">Search</button>
</form>
<div class="toolbar"><span><?= $total ?> official posts matching filters</span><span>Sort by: Recently updated first</span></div>
<div class="table-scroll"><table>
<thead><tr><th>Article</th><th>Category</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead><tbody>
<?php foreach($posts as $post): ?>
<tr>
    <td><div class="news-cell"><?php if($post['cover_image']): ?><img class="news-thumb" src="<?= e($post['cover_image']) ?>" alt="Cover of <?= e($post['title']) ?>"><?php else: ?><div class="news-thumb placeholder">NSBM<br>360</div><?php endif; ?><div><div class="table-title"><?= e($post['title']) ?></div><div class="table-subtitle"><?= e($post['author']) ?></div></div></div></td>
    <td><?= e($post['category_name']) ?></td>
    <td><span class="badge <?= $post['status'] === 'published' ? 'green' : ($post['status'] === 'draft' ? 'gray' : 'blue') ?>"><?= e(ucfirst($post['status'])) ?></span></td>
    <td><?= e(display_date($post['published_at']?:$post['created_at'])) ?></td>
    <td class="actions"><a href="?view=<?= (int)$post['post_id'] ?>#post-preview">Read</a> · <a href="?edit=<?= (int)$post['post_id'] ?>#post-form">Edit</a>
    <?php if($post['status']==='draft'): ?> · <form method="post" class="inline-form"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="post_id" value="<?= (int)$post['post_id'] ?>"><button name="action" value="publish_post">Publish</button></form><?php endif; ?> ·
    <form method="post" class="inline-form" onsubmit="return confirm('Delete this post and its linked images/events?');"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="post_id" value="<?= (int)$post['post_id'] ?>"><button class="danger" name="action" value="delete_post">Delete</button></form></td>
</tr>
<?php endforeach; ?>
<?php if(!$posts): ?><tr><td colspan="5" class="empty">No official posts found.</td></tr><?php endif; ?>
</tbody></table></div>
<div class="pagination-bar"><span>Showing <?= $total?$offset+1:0 ?>–<?= min($offset+$perPage,$total) ?> of <?= $total ?> official posts</span><?php pagination($page,$total,$perPage); ?></div>
<?php if($selected && isset($_GET['view'])): ?><section class="panel" id="post-preview"><h2><?= e($selected['title']) ?></h2><p class="small">By <?= e($selected['author']) ?> · <?= e($selected['category_name']) ?></p><p><?= nl2br(e($selected['content'])) ?></p></section><?php endif; ?>
<div class="two-cols wide-left">
<section class="panel" id="post-form"><h2><?= $selected?'Edit official post':'Create official post' ?></h2><p class="small">Only fields present in the `posts` table are used.</p>
<?php if(!$categories): ?><div class="message error">No categories exist yet. Add a category to the `categories` table before creating posts.</div><?php endif; ?>
<form method="post"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="save_post"><input type="hidden" name="post_id" value="<?= (int)($selected['post_id']??0) ?>">
    <div class="field"><label>Article title *</label><input required maxlength="255" name="title" value="<?= e($selected['title']??'') ?>"></div>
    <div class="field"><label>Category *</label><select name="category_id" required><option value="">Select category</option><?php foreach($categories as $cat): ?><option value="<?= (int)$cat['category_id'] ?>" <?= (int)($selected['category_id']??0)===(int)$cat['category_id']?'selected':'' ?>><?= e($cat['category_name']) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label>Summary (optional)</label><textarea name="summary" maxlength="500" rows="2"><?= e($selected['summary']??'') ?></textarea></div>
    <div class="field"><label>Article content *</label><textarea rows="7" name="content" required><?= e($selected['content']??'') ?></textarea></div>
    <div class="field"><label>Cover image path (optional)</label><input maxlength="255" name="cover_image" placeholder="uploads/news/cover.jpg" value="<?= e($selected['cover_image']??'') ?>"></div>
    <div class="field"><label>Status</label><select name="status"><?php foreach(['draft','published','archived'] as $s): ?><option value="<?= e($s) ?>" <?= ($selected['status']??'draft')===$s?'selected':'' ?>><?= ucfirst($s) ?></option><?php endforeach; ?></select></div>
    <div class="form-buttons"><button class="btn" name="submit_status" value="draft">Save draft</button><button class="btn btn-green" name="submit_status" value="published">Publish post</button><button class="btn" type="submit">Save selected status</button></div>
</form>
</section>
<aside class="panel"><h2>Official publishing checklist</h2>
    <ul class="checklist"><li>Confirm facts and university attribution</li><li>Include a clear summary and cover image</li><li>Check dates, links and contact details</li><li>Review before publishing</li></ul>
    <?php if($selected): ?><hr><p class="small">Author: <?= e($selected['author']) ?><br>Created: <?= e(display_date($selected['created_at'])) ?><br>Last updated: <?= e(display_date($selected['updated_at'])) ?></p><?php endif; ?>
</aside>
</div>
<?php admin_footer(); ?>
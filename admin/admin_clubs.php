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
    <link rel="stylesheet" href="../assets/css/admin_clubs.css">
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

<?php
include '..\includes\footer.php';
?>

<?php
}
/* Clubs management: create, edit, delete and assign club leaders. */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $postAllowed) {
    try {
        $action = $_POST['action'] ?? '';
        if ($action === 'save_club') {
            $id = (int) ($_POST['club_id'] ?? 0);
            $name = trim($_POST['club_name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $logo = trim($_POST['logo'] ?? '');
            $status = $_POST['status'] ?? 'active';
            if ($name === '' || !in_array($status, ['active', 'inactive'], true)) {
                throw new Exception('Enter a club name and valid status.');
            }
            if ($id > 0) {
                run_sql($pdo, 'UPDATE clubs SET club_name=?, description=?, logo=?, status=? WHERE club_id=?',
                    [$name, $description, $logo ?: null, $status, $id]);
            } else {
                run_sql($pdo, 'INSERT INTO clubs (club_name, description, logo, status) VALUES (?, ?, ?, ?)',
                    [$name, $description, $logo ?: null, $status]);
                $id = (int) $pdo->lastInsertId();
            }
            goto_page('admin_clubs.php', 'Club saved successfully.', ['edit' => $id]);
        }
        if ($action === 'delete_club') {
            $id = (int) ($_POST['club_id'] ?? 0);
            if ($id < 1) throw new Exception('Invalid club.');
            // Related posts/role requests can prevent deletion due to foreign keys.
            run_sql($pdo, 'DELETE FROM clubs WHERE club_id=?', [$id]);
            goto_page('admin_clubs.php', 'Club deleted.');
        }
        if ($action === 'save_leaders' || $action === 'clear_leaders') {
            $clubId = (int) ($_POST['club_id'] ?? 0);
            if (!rows($pdo, 'SELECT club_id FROM clubs WHERE club_id=?', [$clubId])) {
                throw new Exception('Select an existing club.');
            }
            $presidentId = (int) ($_POST['president_id'] ?? 0);
            $viceId = (int) ($_POST['vice_id'] ?? 0);
            if ($action === 'save_leaders') {
                if ($presidentId < 1 || $presidentId === $viceId) {
                    throw new Exception('Choose a President and a different Vice President (or leave Vice President empty).');
                }
                foreach ([$presidentId, $viceId] as $userId) {
                    if ($userId < 1) continue;
                    $valid = rows($pdo, 'SELECT user_id FROM users WHERE user_id=? AND role="user" AND email_verified=1', [$userId]);
                    if (!$valid) throw new Exception('Leadership can only be assigned to verified student accounts.');
                }
            }
            // Replacing all active club leadership maintains one person per position.
            $pdo->beginTransaction();
            try {
                // Lock this club while replacing its two leaders.
                rows($pdo, 'SELECT club_id FROM clubs WHERE club_id=? FOR UPDATE', [$clubId]);
                run_sql($pdo, 'DELETE FROM club_memberships WHERE club_id=?', [$clubId]);
                if ($action === 'save_leaders') {
                    run_sql($pdo, 'INSERT INTO club_memberships (user_id, club_id, club_role) VALUES (?, ?, "president")',
                        [$presidentId, $clubId]);
                    if ($viceId > 0) {
                        run_sql($pdo, 'INSERT INTO club_memberships (user_id, club_id, club_role) VALUES (?, ?, "vice_president")',
                            [$viceId, $clubId]);
                    }
                }
                $pdo->commit();
            } catch (Throwable $exception) {
                $pdo->rollBack();
                throw $exception;
            }
            goto_page('admin_clubs.php', $action === 'save_leaders' ? 'Leadership assignments saved.' : 'Leadership assignments removed.', ['assign' => $clubId]);
        }
    } catch (Throwable $exception) {
        $error = $exception instanceof PDOException
            ? 'Database could not complete this action. The club may still be linked to posts, members or requests.'
            : $exception->getMessage();
        error_log($exception->getMessage());
    }
}

// Search only the fields that exist: club name, leaders and status.
$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';
$where = [];
$params = [];
if ($search !== '') {
    $where[] = '(c.club_name LIKE ? OR EXISTS (SELECT 1 FROM club_memberships m JOIN users u ON u.user_id=m.user_id WHERE m.club_id=c.club_id AND CONCAT(u.first_name, " ", u.last_name) LIKE ?))';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
if (in_array($statusFilter, ['active', 'inactive'], true)) {
    $where[] = 'c.status=?';
    $params[] = $statusFilter;
}
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 6;
$total = count_sql($pdo, 'SELECT COUNT(*) AS total FROM clubs c' . $whereSql, $params);
$page = min($page, max(1, (int) ceil($total / $perPage)));
$offset = ($page - 1) * $perPage;

$clubsQuery = 'SELECT c.*,
    (SELECT CONCAT(u.first_name, " ", u.last_name) FROM club_memberships m JOIN users u ON u.user_id=m.user_id WHERE m.club_id=c.club_id AND m.club_role="president" AND m.membership_status="active" LIMIT 1) AS president,
    (SELECT CONCAT(u.first_name, " ", u.last_name) FROM club_memberships m JOIN users u ON u.user_id=m.user_id WHERE m.club_id=c.club_id AND m.club_role="vice_president" AND m.membership_status="active" LIMIT 1) AS vice_president
    FROM clubs c' . $whereSql . ' ORDER BY c.created_at DESC, c.club_id DESC LIMIT ? OFFSET ?';

$queryParams = array_merge($params, [$perPage, $offset]);
$stmtClubs = $pdo->prepare($clubsQuery);
$i = 1;
foreach ($queryParams as $val) {
    if (is_int($val)) {
        $stmtClubs->bindValue($i, $val, PDO::PARAM_INT);
    } else {
        $stmtClubs->bindValue($i, $val, PDO::PARAM_STR);
    }
    $i++;
}
$stmtClubs->execute();
$clubs = $stmtClubs->fetchAll(PDO::FETCH_ASSOC);

$allClubs = count_sql($pdo, 'SELECT COUNT(*) AS total FROM clubs');
$needsLeadership = count_sql($pdo, 'SELECT COUNT(*) AS total FROM clubs c WHERE c.status="active" AND (
    NOT EXISTS(SELECT 1 FROM club_memberships m WHERE m.club_id=c.club_id AND m.club_role="president" AND m.membership_status="active")
    OR NOT EXISTS(SELECT 1 FROM club_memberships m WHERE m.club_id=c.club_id AND m.club_role="vice_president" AND m.membership_status="active"))');

// Load the selected club into the edit/assignment forms.
$selectedId = (int) ($_GET['edit'] ?? $_GET['assign'] ?? $_GET['view'] ?? ($clubs[0]['club_id'] ?? 0));
$isNew = isset($_GET['new']);
$selected = (!$isNew && $selectedId > 0) ? (rows($pdo, 'SELECT * FROM clubs WHERE club_id=?', [$selectedId])[0] ?? null) : null;
$currentPresident = 0;
$currentVice = 0;
if ($selected) {
    foreach (rows($pdo, 'SELECT user_id, club_role FROM club_memberships WHERE club_id=? AND membership_status="active"', [$selectedId]) as $member) {
        if ($member['club_role'] === 'president') $currentPresident = (int) $member['user_id'];
        if ($member['club_role'] === 'vice_president') $currentVice = (int) $member['user_id'];
    }
}
$verifiedUsers = rows($pdo, 'SELECT user_id, first_name, last_name, email FROM users WHERE email_verified=1 AND role="user" ORDER BY first_name, last_name');
admin_header('Clubs & leadership', 'Maintain club profiles and assign one President and one Vice President per club.', 'clubs', 'admin_clubs.css');
?>
<div class="page-actions"><a class="btn btn-green" href="admin_clubs.php?new=1#club-form">＋ Create club</a></div>
<?php if ($error): ?><div class="message error"><?= e($error) ?></div><?php endif; ?>
<?php if (isset($_GET['ok'])): ?><div class="message"><?= e($_GET['ok']) ?></div><?php endif; ?>
<form class="panel filters" method="get">
    <div class="field field-grow"><label for="search">Search clubs or committee members</label><input id="search" name="search" placeholder="Search club name or leader" value="<?= e($search) ?>"></div>
    <div class="field field-select"><label for="status">Status</label><select id="status" name="status"><option value="">All statuses</option><option value="active" <?= $statusFilter==='active'?'selected':'' ?>>Active</option><option value="inactive" <?= $statusFilter==='inactive'?'selected':'' ?>>Inactive</option></select></div>
    <button class="btn btn-green" type="submit">Search</button>
</form>
<div class="toolbar"><span><?= $allClubs ?> clubs · <?= $needsLeadership ?> active clubs require leadership review</span><span>Sort by: Newest first</span></div>
<div class="table-scroll"><table>
    <thead><tr><th>Club</th><th>President</th><th>Vice President</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($clubs as $club): ?>
    <tr>
        <td><div class="table-title"><?= e($club['club_name']) ?></div><div class="table-subtitle"><?= e(substr((string)$club['description'], 0, 85)) ?></div></td>
        <td><?= e($club['president'] ?: 'Unassigned') ?></td><td><?= e($club['vice_president'] ?: 'Unassigned') ?></td>
        <td><?php if ($club['status'] === 'inactive'): ?><span class="badge gray">Inactive</span><?php elseif (!$club['president'] || !$club['vice_president']): ?><span class="badge yellow">Needs leadership</span><?php else: ?><span class="badge">Active</span><?php endif; ?></td>
        <td class="actions"><a href="?view=<?= (int)$club['club_id'] ?>#club-profile">View</a> · <a href="?edit=<?= (int)$club['club_id'] ?>#club-form">Edit</a> · <a href="?assign=<?= (int)$club['club_id'] ?>#assign-form">Assign</a> ·
            <form class="inline-form" method="post" onsubmit="return confirm('Delete this club? Linked posts or requests can prevent deletion.');"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="club_id" value="<?= (int)$club['club_id'] ?>"><button name="action" value="delete_club" class="danger">Delete</button></form>
        </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$clubs): ?><tr><td colspan="5" class="empty">No clubs found.</td></tr><?php endif; ?>
    </tbody>
</table></div>
<div class="pagination-bar"><span>Showing <?= $total ? $offset+1 : 0 ?>–<?= min($offset+$perPage, $total) ?> of <?= $total ?> clubs</span><?php pagination($page, $total, $perPage); ?></div>
<?php if ($selected && isset($_GET['view'])): ?>
<section class="panel" id="club-profile"><h2><?= e($selected['club_name']) ?></h2><p class="muted"><?= nl2br(e($selected['description'])) ?></p><span class="badge <?= $selected['status']==='inactive'?'gray':'' ?>"><?= e($selected['status']) ?></span></section>
<?php endif; ?>
<div class="two-cols">
    <section class="panel" id="club-form"><h2><?= $selected ? 'Edit club profile' : 'Create club profile' ?></h2><p class="small"><?= $selected ? 'Selected club: '.e($selected['club_name']) : 'Add a new university club.' ?></p>
        <form method="post"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="club_id" value="<?= (int)($selected['club_id'] ?? 0) ?>">
            <div class="field"><label>Club name *</label><input required maxlength="150" name="club_name" value="<?= e($selected['club_name'] ?? '') ?>"></div>
            <div class="field"><label>Logo image path (optional)</label><input maxlength="255" name="logo" placeholder="uploads/clubs/logo.jpg" value="<?= e($selected['logo'] ?? '') ?>"></div>
            <div class="field"><label>Description</label><textarea name="description" rows="5"><?= e($selected['description'] ?? '') ?></textarea></div>
            <div class="field"><label>Club status</label><select name="status"><option value="active" <?= ($selected['status']??'active')==='active'?'selected':'' ?>>Active</option><option value="inactive" <?= ($selected['status']??'')==='inactive'?'selected':'' ?>>Inactive</option></select></div>
            <div class="form-buttons"><button class="btn btn-green" name="action" value="save_club">Save club</button><a class="btn" href="admin_clubs.php">Cancel</a></div>
        </form>
    </section>
    <section class="panel" id="assign-form"><h2>Assign President / Vice President</h2>
        <?php if ($selected): ?>
        <p class="small">Publishing access applies only to <?= e($selected['club_name']) ?>.</p>
        <form method="post"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="club_id" value="<?= (int)$selected['club_id'] ?>">
            <div class="field"><label>President *</label><select name="president_id" required><option value="">Select President</option><?php foreach($verifiedUsers as $user): ?><option value="<?= (int)$user['user_id'] ?>" <?= $currentPresident===(int)$user['user_id']?'selected':'' ?>><?= e($user['first_name'].' '.$user['last_name'].' ('.$user['email'].')') ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>Vice President (optional)</label><select name="vice_id"><option value="">Not assigned</option><?php foreach($verifiedUsers as $user): ?><option value="<?= (int)$user['user_id'] ?>" <?= $currentVice===(int)$user['user_id']?'selected':'' ?>><?= e($user['first_name'].' '.$user['last_name'].' ('.$user['email'].')') ?></option><?php endforeach; ?></select></div>
            <div class="info"><strong>Assignment rules</strong><br>Only email-verified students are selectable. A person cannot hold both positions in the same club.</div>
            <div class="form-buttons"><button class="btn btn-green" name="action" value="save_leaders">Save assignments</button><button class="btn btn-red" name="action" value="clear_leaders" formnovalidate onclick="return confirm('Remove both leadership assignments?')">Clear assignments</button></div>
        </form>
        <?php else: ?><p class="muted">Create or select a club first.</p><?php endif; ?>
    </section>
</div>
<p class="small">If a club has related published posts or role requests, deactivate it instead of deleting it.</p>
<?php admin_footer(); ?>
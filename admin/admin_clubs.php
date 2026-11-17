<?php
/**
 * NSBM 360 - Clubs & Leadership Administration
 * Manages campus clubs, societies, club logos/banners, social/external links, 
 * and President/Vice President assignments.
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

function rows(PDO $pdo, string $sql, array $values = []): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($values);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function run_sql(PDO $pdo, string $sql, array $values = []): void
{
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
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
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
    <link rel="stylesheet" href="../assets/css/admin_clubs.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<?php
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<div class="app-shell">
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

function admin_footer(): void
{
    ?>
    </main>
</div>

<footer class="site-footer">
    <?php include __DIR__ . '/../includes/footer.php'; ?>
</footer>

<script src="../assets/js/admin_clubs.js"></script>
</body>
</html>
<?php
}

// ==========================================================================
// POST Action Handlers (Save Club, Links, Assign Leaders)
// ==========================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $postAllowed) {
    try {
        $action = $_POST['action'] ?? '';

        // Create or Update Club Details, Assets, and Social Links
        if ($action === 'save_club') {
            $id          = (int) ($_POST['club_id'] ?? 0);
            $clubName    = trim($_POST['club_name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $status      = $_POST['status'] ?? 'active';

            if ($clubName === '' || !in_array($status, ['active', 'inactive'], true)) {
                throw new Exception('Club name and a valid status are required.');
            }

            $logoPath   = null;
            $bannerPath = null;
            if ($id > 0) {
                $existing   = rows($pdo, 'SELECT logo, banner FROM clubs WHERE club_id = ?', [$id])[0] ?? null;
                $logoPath   = $existing['logo'] ?? null;
                $bannerPath = $existing['banner'] ?? null;
            }

            // Dedicated subfolders inside uploads/clubs/
            $logoDir   = __DIR__ . '/../uploads/clubs/logo/';
            $bannerDir = __DIR__ . '/../uploads/clubs/banner/';

            if (!is_dir($logoDir)) {
                mkdir($logoDir, 0755, true);
            }
            if (!is_dir($bannerDir)) {
                mkdir($bannerDir, 0755, true);
            }

            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];

            // Handle Logo Upload (saved into uploads/clubs/logo/)
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $fileExtension = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
                if (!in_array($fileExtension, $allowedExtensions, true) || $_FILES['logo']['size'] > 5 * 1024 * 1024) {
                    throw new Exception('Invalid logo file. Use JPG, PNG, or WEBP under 5MB.');
                }

                $newFileName = 'logo_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $fileExtension;
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $logoDir . $newFileName)) {
                    $logoPath = '../uploads/clubs/logo/' . $newFileName;
                } else {
                    throw new Exception('Failed to upload club logo.');
                }
            }

            // Handle Banner Upload (saved into uploads/clubs/banner/)
            if (isset($_FILES['banner']) && $_FILES['banner']['error'] === UPLOAD_ERR_OK) {
                $fileExtension = strtolower(pathinfo($_FILES['banner']['name'], PATHINFO_EXTENSION));
                if (!in_array($fileExtension, $allowedExtensions, true) || $_FILES['banner']['size'] > 5 * 1024 * 1024) {
                    throw new Exception('Invalid banner file. Use JPG, PNG, or WEBP under 5MB.');
                }

                $newFileName = 'banner_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $fileExtension;
                if (move_uploaded_file($_FILES['banner']['tmp_name'], $bannerDir . $newFileName)) {
                    $bannerPath = '../uploads/clubs/banner/' . $newFileName;
                } else {
                    throw new Exception('Failed to upload club banner.');
                }
            }

            $pdo->beginTransaction();

            if ($id > 0) {
                run_sql(
                    $pdo,
                    'UPDATE clubs SET club_name = ?, description = ?, logo = ?, banner = ?, status = ? WHERE club_id = ?',
                    [$clubName, $description, $logoPath, $bannerPath, $status, $id]
                );
            } else {
                run_sql(
                    $pdo,
                    'INSERT INTO clubs (club_name, description, logo, banner, status) VALUES (?, ?, ?, ?, ?)',
                    [$clubName, $description, $logoPath, $bannerPath, $status]
                );
                $id = (int) $pdo->lastInsertId();
            }

            // --- HANDLE CLUB LINKS INSERTION / UPDATE ---
            run_sql($pdo, 'DELETE FROM club_links WHERE club_id = ?', [$id]);
            if (isset($_POST['link_platforms']) && is_array($_POST['link_platforms'])) {
                foreach ($_POST['link_platforms'] as $index => $platform) {
                    $platformName = trim($platform);
                    $url = trim($_POST['link_urls'][$index] ?? '');

                    if ($platformName !== '' && filter_var($url, FILTER_VALIDATE_URL)) {
                        run_sql(
                            $pdo,
                            'INSERT INTO club_links (club_id, platform, url) VALUES (?, ?, ?)',
                            [$id, $platformName, $url]
                        );
                    }
                }
            }

            $pdo->commit();
            // Clean redirect without sticky edit IDs left in the URL
            goto_page('admin_clubs.php', 'Club and links saved successfully.');
        }

        // Assign President and Vice President
        if ($action === 'save_leaders') {
            $clubId      = (int) ($_POST['club_id'] ?? 0);
            $presidentId = (int) ($_POST['president_id'] ?? 0);
            $viceId      = (int) ($_POST['vice_id'] ?? 0);

            if ($clubId < 1 || $presidentId < 1) {
                throw new Exception('Club and President selection are required.');
            }
            if ($viceId > 0 && $presidentId === $viceId) {
                throw new Exception('The President and Vice President cannot be the same person.');
            }

            run_sql(
                $pdo,
                'DELETE FROM club_memberships WHERE club_id = ? AND club_role IN ("president", "vice_president")',
                [$clubId]
            );

            run_sql(
                $pdo,
                'INSERT INTO club_memberships (user_id, club_id, club_role, membership_status) VALUES (?, ?, "president", "active")',
                [$presidentId, $clubId]
            );

            if ($viceId > 0) {
                run_sql(
                    $pdo,
                    'INSERT INTO club_memberships (user_id, club_id, club_role, membership_status) VALUES (?, ?, "vice_president", "active")',
                    [$viceId, $clubId]
                );
            }

            goto_page('admin_clubs.php', 'Leadership assignments updated successfully.', ['edit' => $clubId]);
        }

        // Remove Both Leadership Assignments
        if ($action === 'clear_leaders') {
            $clubId = (int) ($_POST['club_id'] ?? 0);
            if ($clubId > 0) {
                run_sql(
                    $pdo,
                    'DELETE FROM club_memberships WHERE club_id = ? AND club_role IN ("president", "vice_president")',
                    [$clubId]
                );
                goto_page('admin_clubs.php', 'Leadership assignments cleared.', ['edit' => $clubId]);
            }
        }
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = 'DB ERROR: ' . $exception->getMessage();
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
    $where[]  = '(club_name LIKE ? OR description LIKE ?)';
    $values[] = '%' . $search . '%';
    $values[] = '%' . $search . '%';
}

if (in_array($filter, ['active', 'inactive'], true)) {
    $where[]  = 'status = ?';
    $values[] = $filter;
}

$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$perPage = 6;
$page    = max(1, (int) ($_GET['page'] ?? 1));
$total   = count_sql($pdo, 'SELECT COUNT(*) AS total FROM clubs' . $whereSql, $values);
$page    = min($page, max(1, (int) ceil($total / $perPage)));
$offset  = ($page - 1) * $perPage;

$clubsQuery  = 'SELECT * FROM clubs' . $whereSql . ' ORDER BY club_name ASC LIMIT ? OFFSET ?';
$queryParams = array_merge($values, [$perPage, $offset]);
$stmtClubs   = $pdo->prepare($clubsQuery);

foreach ($queryParams as $idx => $val) {
    $stmtClubs->bindValue($idx + 1, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmtClubs->execute();
$clubs = $stmtClubs->fetchAll(PDO::FETCH_ASSOC);

// Attach President, Vice President, and Links to each club row
foreach ($clubs as &$club) {
    $leaders = rows(
        $pdo,
        'SELECT cm.club_role, CONCAT(u.first_name, " ", u.last_name) AS full_name 
         FROM club_memberships cm 
         JOIN users u ON cm.user_id = u.user_id 
         WHERE cm.club_id = ? AND cm.club_role IN ("president", "vice_president")',
        [$club['club_id']]
    );

    $club['president']      = 'Not assigned';
    $club['vice_president'] = 'Not assigned';

    foreach ($leaders as $leader) {
        if ($leader['club_role'] === 'president') {
            $club['president'] = $leader['full_name'];
        } elseif ($leader['club_role'] === 'vice_president') {
            $club['vice_president'] = $leader['full_name'];
        }
    }

    $club['links'] = rows($pdo, 'SELECT * FROM club_links WHERE club_id = ?', [$club['club_id']]);
}
unset($club);

$stats = rows($pdo, 'SELECT COUNT(*) AS total, SUM(status = "active") AS active, SUM(status = "inactive") AS inactive FROM clubs')[0];

// Clean state initialization (Only loads selected club if explicitly requested via GET)
$selectedId = (int) ($_GET['edit'] ?? $_GET['view'] ?? 0);
$selected   = (!isset($_GET['new']) && $selectedId > 0)
    ? (rows($pdo, 'SELECT * FROM clubs WHERE club_id = ?', [$selectedId])[0] ?? null)
    : null;

$selectedClubLinks = $selected ? rows($pdo, 'SELECT * FROM club_links WHERE club_id = ?', [$selected['club_id']]) : [];

$verifiedUsers = rows($pdo, 'SELECT user_id, first_name, last_name, email FROM users WHERE email_verified = 1 ORDER BY first_name ASC');

$currentPresident = 0;
$currentVice      = 0;
if ($selected) {
    $currentLeaders = rows($pdo, 'SELECT user_id, club_role FROM club_memberships WHERE club_id = ?', [$selected['club_id']]);
    foreach ($currentLeaders as $cl) {
        if ($cl['club_role'] === 'president') {
            $currentPresident = (int) $cl['user_id'];
        }
        if ($cl['club_role'] === 'vice_president') {
            $currentVice = (int) $cl['user_id'];
        }
    }
}

admin_header('Clubs & leadership', 'Manage campus clubs, societies and assigned leadership.', 'clubs');
?>

<!-- Top Create Club Action -->
<div class="page-actions">
    <a class="btn btn-green" href="admin_clubs.php?new=1#club-form">＋ Create club</a>
</div>

<?php if ($error): ?>
    <div class="message error"><?= e($error) ?></div>
<?php endif; ?>

<?php if (isset($_GET['ok'])): ?>
    <div class="message"><?= e($_GET['ok']) ?></div>
<?php endif; ?>

<!-- Status Filter Tabs -->
<div class="tabs">
    <?php foreach (['' => 'All clubs', 'active' => 'Active', 'inactive' => 'Inactive'] as $key => $label): ?>
        <?php $number = ($key === '') ? $stats['total'] : $stats[$key]; ?>
        <a class="<?= $filter === $key ? 'active' : '' ?>" href="?<?= http_build_query(array_merge($_GET, ['status' => $key, 'page' => 1])) ?>">
            <?= e($label) ?> <?= (int) $number ?>
        </a>
    <?php endforeach; ?>
</div>

<!-- Search & Filter Form -->
<form class="panel filters" method="get">
    <div class="field field-grow">
        <label>Search clubs</label>
        <input name="search" placeholder="Search club name or description" value="<?= e($search) ?>">
    </div>

    <div class="field field-select">
        <label>Status</label>
        <select name="status">
            <option value="">All statuses</option>
            <?php foreach (['active', 'inactive'] as $s): ?>
                <option value="<?= e($s) ?>" <?= $filter === $s ? 'selected' : '' ?>>
                    <?= ucfirst($s) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <button class="btn btn-green">Search</button>
</form>

<div class="toolbar">
    <span><?= $total ?> clubs matching filters</span>
    <span>Sort by: Name</span>
</div>

<!-- Clubs Data Table -->
<div class="table-scroll">
    <table>
        <thead>
            <tr>
                <th>Logo</th>
                <th>Club Name</th>
                <th>President</th>
                <th>Vice President</th>
                <th>Links</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($clubs as $club): ?>
                <tr>
                    <td>
                        <?php if (!empty($club['logo'])): ?>
                            <img src="<?= e($club['logo']) ?>" alt="Logo" class="table-thumb">
                        <?php else: ?>
                            <span class="no-thumb">No logo</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="table-title"><?= e($club['club_name']) ?></div>
                    </td>
                    <td><?= e($club['president']) ?></td>
                    <td><?= e($club['vice_president']) ?></td>
                    <td>
                        <?php if (!empty($club['links'])): ?>
                            <div class="table-links-list">
                                <?php foreach ($club['links'] as $lnk): ?>
                                    <a href="<?= e($lnk['url']) ?>" target="_blank" title="<?= e($lnk['platform']) ?>">
                                        <i class="fa-solid fa-link"></i> <?= e($lnk['platform']) ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <span class="muted small">No links</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge <?= ($club['status'] ?? '') === 'active' ? 'green' : 'gray' ?>">
                            <?= e($club['status']) ?>
                        </span>
                    </td>
                    <td class="actions">
                        <a href="?view=<?= (int) $club['club_id'] ?>#club-preview">View</a> ·
                        <a href="?edit=<?= (int) $club['club_id'] ?>#club-form">Edit</a>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if (!$clubs): ?>
                <tr>
                    <td class="empty" colspan="7">No clubs found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="pagination-bar">
    <span>Showing <?= $total ? $offset + 1 : 0 ?>–<?= min($offset + $perPage, $total) ?> of <?= $total ?> clubs</span>
    <?php pagination($page, $total, $perPage); ?>
</div>

<!-- Club Profile Preview Section -->
<?php if ($selected && isset($_GET['view'])): ?>
    <section class="panel club-preview-panel" id="club-preview">
        <div class="club-preview-banner">
            <?php if (!empty($selected['banner'])): ?>
                <img src="<?= e($selected['banner']) ?>" alt="Club banner">
            <?php endif; ?>
        </div>

        <div class="club-preview-body">
            <div class="club-preview-logo-wrap">
                <?php if (!empty($selected['logo'])): ?>
                    <img src="<?= e($selected['logo']) ?>" alt="Club logo" class="circle-logo">
                <?php else: ?>
                    <div class="circle-logo circle-logo-placeholder">No logo</div>
                <?php endif; ?>
            </div>

            <div class="club-preview-details">
                <h2 class="club-preview-title"><?= e($selected['club_name']) ?></h2>
                <p class="club-preview-status">
                    <span class="badge <?= ($selected['status'] ?? 'active') === 'inactive' ? 'gray' : 'green' ?>">
                        <?= e($selected['status'] ?? 'active') ?>
                    </span>
                </p>
                <div class="club-preview-desc">
                    <?= nl2br(e($selected['description'])) ?>
                </div>

                <?php if (!empty($selectedClubLinks)): ?>
                    <div class="club-preview-links">
                        <strong>Official Links:</strong>
                        <div class="preview-links-row">
                            <?php foreach ($selectedClubLinks as $sLink): ?>
                                <a href="<?= e($sLink['url']) ?>" target="_blank" class="btn btn-small">
                                    <i class="fa-solid fa-globe"></i> <?= e($sLink['platform']) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<!-- Two-Column Management Forms -->
<div class="two-cols wide-left">
    <!-- Left Column: Create / Edit Club Details & Dynamic Links -->
    <section class="panel" id="club-form">
        <h2><?= $selected ? 'Edit club' : 'Create club' ?></h2>
        <p class="small"><?= $selected ? 'Selected: ' . e($selected['club_name']) : 'Add a new campus club.' ?></p>

        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="action" value="save_club">
            <input type="hidden" name="club_id" value="<?= (int) ($selected['club_id'] ?? 0) ?>">

            <div class="field">
                <label>Club Name *</label>
                <input name="club_name" required maxlength="150" value="<?= e($selected['club_name'] ?? '') ?>">
            </div>

            <div class="field">
                <label>Description</label>
                <textarea rows="5" name="description"><?= e($selected['description'] ?? '') ?></textarea>
            </div>

            <!-- Dynamic Club Links Section -->
            <div class="field">
                <label>Club Links / Social Profiles</label>
                <div id="club-links-container">
                    <?php if (!empty($selectedClubLinks)): ?>
                        <?php foreach ($selectedClubLinks as $link): ?>
                            <div class="club-link-row">
                                <input type="text" name="link_platforms[]" placeholder="Platform (e.g., Website, Facebook, LinkedIn)" value="<?= e($link['platform']) ?>">
                                <input type="url" name="link_urls[]" placeholder="URL (https://...)" value="<?= e($link['url']) ?>">
                                <button type="button" class="btn btn-red btn-remove-link" aria-label="Remove link">×</button>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="club-link-row">
                            <input type="text" name="link_platforms[]" placeholder="Platform (e.g., Website, Facebook, LinkedIn)">
                            <input type="url" name="link_urls[]" placeholder="URL (https://...)">
                            <button type="button" class="btn btn-red btn-remove-link" aria-label="Remove link">×</button>
                        </div>
                    <?php endif; ?>
                </div>
                <button type="button" class="btn btn-small" id="add-link-btn" style="margin-top: 8px;">＋ Add another link</button>
            </div>

            <div class="field">
                <label>Club Logo (optional)</label>
                <?php if (!empty($selected['logo'])): ?>
                    <div class="current-asset-preview">
                        <img src="<?= e($selected['logo']) ?>" alt="Current logo" class="circle-logo circle-logo-sm">
                        <span class="small">Current file: <?= e(basename($selected['logo'])) ?></span>
                    </div>
                <?php endif; ?>
                <input type="file" name="logo" accept="image/png, image/jpeg, image/webp">
            </div>

            <div class="field">
                <label>Club Banner Image (optional)</label>
                <?php if (!empty($selected['banner'])): ?>
                    <div class="current-asset-preview">
                        <img src="<?= e($selected['banner']) ?>" alt="Current banner" class="current-banner-thumb">
                        <span class="small">Current file: <?= e(basename($selected['banner'])) ?></span>
                    </div>
                <?php endif; ?>
                <input type="file" name="banner" accept="image/png, image/jpeg, image/webp">
            </div>

            <div class="field">
                <label>Status</label>
                <select name="status">
                    <?php foreach (['active', 'inactive'] as $state): ?>
                        <option value="<?= e($state) ?>" <?= ($selected['status'] ?? 'active') === $state ? 'selected' : '' ?>>
                            <?= ucfirst($state) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-buttons">
                <button class="btn btn-green" type="submit">Save club details</button>
            </div>
        </form>
    </section>

    <!-- Right Column: Assign Club Leadership -->
    <section class="panel" id="assign-form">
        <h2>Assign President / Vice President</h2>

        <?php if ($selected): ?>
            <p class="small">Leadership assignments for <?= e($selected['club_name']) ?>.</p>
            <form method="post">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="club_id" value="<?= (int) $selected['club_id'] ?>">

                <div class="field">
                    <label>President *</label>
                    <select name="president_id" required>
                        <option value="">Select President</option>
                        <?php foreach ($verifiedUsers as $user): ?>
                            <option value="<?= (int) $user['user_id'] ?>" <?= $currentPresident === (int) $user['user_id'] ? 'selected' : '' ?>>
                                <?= e($user['first_name'] . ' ' . $user['last_name'] . ' (' . $user['email'] . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label>Vice President (optional)</label>
                    <select name="vice_id">
                        <option value="">Not assigned</option>
                        <?php foreach ($verifiedUsers as $user): ?>
                            <option value="<?= (int) $user['user_id'] ?>" <?= $currentVice === (int) $user['user_id'] ? 'selected' : '' ?>>
                                <?= e($user['first_name'] . ' ' . $user['last_name'] . ' (' . $user['email'] . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="info">
                    <strong>Assignment rules</strong><br>
                    Only email-verified students are selectable. A person cannot hold both positions in the same club.
                </div>

                <div class="form-buttons">
                    <button class="btn btn-green" name="action" value="save_leaders">Save assignments</button>
                    <button class="btn btn-red" name="action" value="clear_leaders" formnovalidate onclick="return confirm('Remove both leadership assignments?')">
                        Clear assignments
                    </button>
                </div>
            </form>
        <?php else: ?>
            <p class="muted">Create or select a club first.</p>
        <?php endif; ?>
    </section>
</div>

<p class="small">To retire a club from active campus use, set its status to inactive.</p>

<?php admin_footer(); ?>
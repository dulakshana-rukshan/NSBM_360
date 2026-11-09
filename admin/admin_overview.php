<?php
// Include your team's shared database connection (provides $pdo)
require_once __DIR__ . '/../config/database.php';

// Session setup
session_start();

// Helper function for safe HTML escaping
function esc($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

// Only a logged-in administrator may access these pages.
$adminId = (int) ($_SESSION['user_id'] ?? 0);
if ($adminId < 1) {
    http_response_code(401);
    exit('Please sign in as an administrator.');
}

// Session or admin name fallback
$adminName = 'Dilini';
$error = '';

// Safe database metrics retrieval with zero-fallbacks for empty tables
try {
    $totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() ?: 0;
    $newUsers = $pdo->query("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn() ?: 0;
    
    $activeClubs = $pdo->query("SELECT COUNT(*) FROM clubs WHERE status = 'active'")->fetchColumn() ?: 0;
    
    $publishedPosts = $pdo->query("SELECT COUNT(*) FROM posts WHERE status = 'published'")->fetchColumn() ?: 0;
    $newPosts = $pdo->query("SELECT COUNT(*) FROM posts WHERE status = 'published' AND published_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn() ?: 0;
    
    $pendingCount = $pdo->query("SELECT COUNT(*) FROM role_requests WHERE request_status = 'pending'")->fetchColumn() ?: 0;

    // Fetch pending requests safely
    $stmtPending = $pdo->query("
        SELECT rr.*, u.first_name, u.last_name, u.student_id, c.club_name 
        FROM role_requests rr
        JOIN users u ON rr.user_id = u.user_id 
        JOIN clubs c ON rr.club_id = c.club_id
        WHERE rr.request_status = 'pending' 
        ORDER BY rr.requested_at ASC LIMIT 5
    ");
    $pending = $stmtPending->fetchAll(PDO::FETCH_ASSOC);

    // Fetch chart data (last 7 days)
    $weekly = $pdo->query("
        SELECT DATE(published_at) AS publish_day, COUNT(*) AS total 
        FROM posts
        WHERE status = 'published' AND published_at IS NOT NULL AND published_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        GROUP BY DATE(published_at)
    ")->fetchAll(PDO::FETCH_ASSOC);

    $weeklyMap = [];
    foreach($weekly as $day) {
        $weeklyMap[$day['publish_day']] = (int)$day['total'];
    }
    
    $chart = [];
    for($i = 6; $i >= 0; $i--) {
        $key = date('Y-m-d', strtotime("-$i days"));
        $chart[] = [
            'label' => date('D', strtotime($key)),
            'count' => $weeklyMap[$key] ?? 0
        ];
    }
    $maxCount = max(1, ...array_column($chart, 'count'));
    $weekTotal = array_sum(array_column($chart, 'count'));

    // Recent activity feed
    $activity = $pdo->query("
        SELECT CONCAT(u.first_name, ' ', u.last_name) AS actor,
        CONCAT('published post: ', p.title) AS description, p.published_at AS happened_at
        FROM posts p JOIN users u ON p.created_by = u.user_id WHERE p.status = 'published' AND p.published_at IS NOT NULL
        UNION ALL
        SELECT CONCAT(u.first_name, ' ', u.last_name) AS actor,
        CONCAT('requested role for ', c.club_name) AS description, rr.requested_at AS happened_at
        FROM role_requests rr JOIN users u ON rr.user_id = u.user_id JOIN clubs c ON rr.club_id = c.club_id
        ORDER BY happened_at DESC LIMIT 6
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    // Fallbacks if tables are not fully populated or structure is missing
    $totalUsers = 0; $newUsers = 0; $activeClubs = 0; $publishedPosts = 0;
    $newPosts = 0; $pendingCount = 0; $pending = []; $chart = []; $maxCount = 1; $weekTotal = 0; $activity = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Campus platform overview | NSBM 360 Admin</title>
    <!-- Specific CSS files linked -->
    <link rel="stylesheet" href="../assets/css/admin_overview.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<?php 
include '..\includes\header.php';
include '..\includes\navbar.php'; 
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
            <a class="active" href="admin_overview.php">Overview</a>
            <a href="admin_users&role_requests.php">Users & role requests</a>
            <a href="admin_clubs.php">Clubs & leadership</a>
            <a href="admin_officialnews.php">Official news</a>
            <a href="admin_announcements.php">Announcements</a>
        </nav>
        <div class="sidebar-bottom">
            <p>Manage platform accounts, club assignments and official campus content.</p>
            <a href="index.php">↗ View public site</a>
            <form method="post">
                <button type="submit" class="signout" name="action" value="logout">Sign out</button>
            </form>
        </div>
    </aside>

    <!-- MAIN CONTENT AREA -->
    <main class="main-content">
        <div class="breadcrumbs">Home / Administration / Campus platform overview</div>
        <div class="page-heading">
            <div>
                <h1>Campus platform overview</h1>
                <p>Welcome back, keep the community connected and the platform running smoothly.</p>
            </div>
        </div>

        <?php if(!empty($error)): ?>
            <div class="message error"><?= esc($error) ?></div>
        <?php endif; ?>

        <div class="toolbar overview-date">
            <span>Database overview</span>
            <span><?= date('d F Y') ?> · Last 30 days where available</span>
        </div>

        <!-- STAT CARDS GRID -->
        <div class="stat-grid">
            <div class="stat-card">
                <div class="stat-title">Registered users <span>♙</span></div>
                <div class="stat-number"><?= number_format($totalUsers) ?></div>
                <div class="stat-foot">+<?= $newUsers ?> registered in 30 days</div>
            </div>
            <div class="stat-card">
                <div class="stat-title">Active clubs <span>♧</span></div>
                <div class="stat-number"><?= number_format($activeClubs) ?></div>
                <div class="stat-foot">Active community spaces</div>
            </div>
            <div class="stat-card">
                <div class="stat-title">Published posts <span>▣</span></div>
                <div class="stat-number"><?= number_format($publishedPosts) ?></div>
                <div class="stat-foot">+<?= $newPosts ?> published in 30 days</div>
            </div>
            <div class="stat-card">
                <div class="stat-title">Pending requests <span>♧</span></div>
                <div class="stat-number"><?= number_format($pendingCount) ?></div>
                <div class="stat-foot">Awaiting administrator review</div>
            </div>
        </div>

        <!-- PENDING ROLE REQUESTS TABLE -->
        <section class="panel">
            <h2>Pending role requests</h2>
            <p class="small">Verify committee appointments before approving publishing access.</p>
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Applicant</th>
                            <th>Requested club</th>
                            <th>Role</th>
                            <th>Submitted</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(!empty($pending)): ?>
                            <?php foreach($pending as $request): ?>
                            <tr>
                                <td>
                                    <div class="table-title"><?= esc($request['first_name'].' '.$request['last_name']) ?></div>
                                    <div class="table-subtitle"><?= esc($request['student_id']) ?></div>
                                </td>
                                <td><?= esc($request['club_name']) ?></td>
                                <td><?= esc(ucwords(str_replace('_',' ',$request['requested_role']))) ?></td>
                                <td><?= esc($request['requested_at']) ?></td>
                                <td class="actions">
                                    <a href="admin_users&role_requests.php?review=<?= (int)$request['request_id'] ?>">Review</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="empty">No pending requests.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <p class="manage-link"><a href="admin_users&role_requests.php">Manage users & role requests →</a></p>
        </section>

        <!-- TWO COLUMNS SECTION (CHARTS & ACTIVITY) -->
        <div class="two-cols">
            <section class="panel">
                <h2>Publishing activity</h2>
                <p class="small">Published posts with publication dates · Last 7 days</p>
                <div class="bar-chart" role="img" aria-label="Posts published during the past seven days">
                    <?php foreach($chart as $bar): ?>
                    <div class="bar-column">
                        <span class="bar-count"><?= $bar['count'] ?></span>
                        <div class="bar-track">
                            <div class="bar" style="height:<?= max($bar['count'] ? 8 : 0, round($bar['count'] / $maxCount * 100)) ?>%"></div>
                        </div>
                        <span class="bar-day"><?= esc(strtoupper($bar['label'])) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <p class="stat-foot"><?= $weekTotal ?> posts with publication dates in the last 7 days</p>
            </section>

            <section class="panel">
                <h2>Recent activity</h2>
                <p class="small">Recent publications and requests</p>
                <?php if(!empty($activity)): ?>
                    <?php foreach($activity as $item): ?>
                    <div class="activity">
                        <strong><?= esc($item['actor']) ?></strong> <?= esc($item['description']) ?>
                        <div class="table-subtitle"><?= esc(date('d M Y, h:i A', strtotime($item['happened_at']))) ?></div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="muted">No recent activity recorded.</p>
                <?php endif; ?>
            </section>
        </div>

        <!-- MANAGEMENT SHORTCUTS -->
        <section class="panel">
            <h2>Management shortcuts</h2>
            <div class="shortcut-grid">
                <a class="shortcut" href="admin_users&role_requests.php"><strong>Users & requests</strong><span>Review access and accounts</span><b>Manage users →</b></a>
                <a class="shortcut" href="admin_clubs.php"><strong>Clubs & leadership</strong><span>Assign Presidents and VPs</span><b>Manage clubs →</b></a>
                <a class="shortcut" href="admin_officialnews.php"><strong>Official news</strong><span>Publish university updates</span><b>Manage news →</b></a>
                <a class="shortcut" href="admin_announcements.php"><strong>Announcements</strong><span>Keep campus informed</span><b>Manage notices →</b></a>
            </div>
        </section>
    </main>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
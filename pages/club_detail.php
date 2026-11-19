<?php

declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'Club details | NSBM 360';
$activePage = 'clubs';
$pageCSS = '../assets/css/club_detail.css?v=20261114-3';

// Escape untrusted content before displaying it in HTML.
function clubE($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

// Convert image paths stored relative to the project root into URLs from /pages.
function clubImageUrl($value): string {
    $path = trim(str_replace('\\', '/', (string)($value ?? '')));
    if ($path === '') return '';
    if (preg_match('~^https?://~i', $path)) return $path;
    if (str_starts_with($path, '//') || preg_match('~^[a-z][a-z0-9+.-]*:~i', $path)) return '';
    while (str_starts_with($path, '../') || str_starts_with($path, './')) {
        $path = substr($path, str_starts_with($path, '../') ? 3 : 2);
    }
    if (str_starts_with($path, '/NSBM_360/')) $path = substr($path, strlen('/NSBM_360/'));
    $path = ltrim($path, '/');
    if (!preg_match('~^(uploads/(clubs|posts)/|assets/image/)~', $path) ||
        str_contains($path, '..') || preg_match('/[?#\x00-\x1F\x7F]/', $path)) return '';
    return '/NSBM_360/' . $path;
}

// Allow only safe HTTP(S) registration URLs from the database.
function clubWebUrl($value): string {
    $url = trim((string)($value ?? ''));
    return filter_var($url, FILTER_VALIDATE_URL) && preg_match('~^https?://~i', $url) ? $url : '';
}

// Work with UTF-8 text even if the optional mbstring extension is disabled.
function clubLength(string $text): int {
    if (function_exists('mb_strlen')) return mb_strlen($text, 'UTF-8');
    if (function_exists('iconv_strlen')) {
        $length = iconv_strlen($text, 'UTF-8');
        if ($length !== false) return $length;
    }
    return strlen($text);
}

function clubExcerpt(string $text, int $maxLength = 160): string {
    $text = trim(strip_tags($text));
    if (clubLength($text) <= $maxLength) return $text;
    if (function_exists('mb_substr')) return mb_substr($text, 0, $maxLength, 'UTF-8') . '…';
    if (function_exists('iconv_substr')) return (string)iconv_substr($text, 0, $maxLength, 'UTF-8') . '…';
    return substr($text, 0, $maxLength) . '…';
}

function clubDate($value): string {
    if (!$value) return 'Date not available';
    $time = strtotime((string)$value);
    return $time ? date('d M Y', $time) : 'Date not available';
}

// Resolve user profile photos without changing club banner/logo handling.
function clubProfileImageUrl($value): string {
    $path = trim(str_replace('\\', '/', (string)($value ?? '')));
    if ($path === '') return '';
    if (preg_match('~^https?://~i', $path)) return filter_var($path, FILTER_VALIDATE_URL) ? $path : '';
    if (str_starts_with($path, '//') || preg_match('~^[a-z][a-z0-9+.-]*:~i', $path)) return '';
    while (str_starts_with($path, '../') || str_starts_with($path, './')) {
        $path = substr($path, str_starts_with($path, '../') ? 3 : 2);
    }
    if (str_starts_with($path, '/NSBM_360/')) $path = substr($path, strlen('/NSBM_360/'));
    $path = ltrim($path, '/');
    if (str_contains($path, '..') || preg_match('/[?#\x00-\x1F\x7F]/', $path)) return '';
    if (!preg_match('~^(uploads/|assets/image/)~i', $path)) return '';
    return '/NSBM_360/' . $path;
}

function clubLeaderAvatar(?array $leader): void {
    $initials = $leader
        ? strtoupper(substr((string)$leader['first_name'], 0, 1) . substr((string)$leader['last_name'], 0, 1))
        : '—';
    $photo = $leader ? clubProfileImageUrl($leader['profile_image'] ?? '') : '';
    echo '<span class="club-leader-initials" style="position:relative;overflow:hidden;flex-shrink:0">';
    echo clubE($initials);
    if ($photo !== '') {
        echo '<img src="' . clubE($photo) . '" alt="" loading="lazy" onerror="this.remove()" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;border-radius:50%;display:block">';
    }
    echo '</span>';
}

function clubFullName(array $person): string {
    return trim((string)($person['first_name'] ?? '') . ' ' . (string)($person['last_name'] ?? ''));
}

function clubUrl(int $clubId, string $tab = 'overview', array $extra = []): string {
    return 'club_detail.php?' . http_build_query(array_merge(['id' => $clubId, 'tab' => $tab], $extra));
}

// Select the current club using a verified integer ID, not a fixed club name.
$clubId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$clubId) {
    http_response_code(400);
    echo '<p style="font:16px Arial;padding:40px">Invalid club ID. <a href="clubs.php">Back to clubs</a></p>';
    exit;
}
$clubId = (int)$clubId;

$stmt = $pdo->prepare('SELECT * FROM clubs WHERE club_id = :id LIMIT 1');
$stmt->execute(['id' => $clubId]);
$club = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$club) {
    http_response_code(404);
    echo '<p style="font:16px Arial;padding:40px">Club not found. <a href="clubs.php">Back to clubs</a></p>';
    exit;
}
$pageTitle = $club['club_name'] . ' | NSBM 360';

$tab = (string)($_GET['tab'] ?? 'overview');
// Contact details are private to signed-in users; all other tabs are public.
// Set this to your project's actual login page if it differs.
$clubLoginUrl = '/NSBM_360/auth/login.php';
$validTabs = ['overview', 'news', 'leadership', 'contact'];
if (!in_array($tab, $validTabs, true)) $tab = 'overview';
$newsFilter = (string)($_GET['filter'] ?? 'all');
if (!in_array($newsFilter, ['all', 'events', 'news'], true)) $newsFilter = 'all';

// Read verified user's name and email from session ID, not from user input.
$viewerId = (int)($_SESSION['user_id'] ?? 0);
$viewer = null;
if ($viewerId > 0) {
    $stmt = $pdo->prepare('SELECT user_id, first_name, last_name, email, role, email_verified FROM users WHERE user_id = ? LIMIT 1');
    $stmt->execute([$viewerId]);
    $viewer = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!$viewer) $viewerId = 0;
}
if ($tab === 'contact' && !$viewer) {
    $returnUrl = clubUrl($clubId, 'contact');
    header('Location: ' . $clubLoginUrl . '?redirect=' . rawurlencode($returnUrl));
    exit;
}

//Read current President and Vice President from club_memberships.
$stmt = $pdo->prepare("SELECT m.club_role, u.user_id, u.first_name, u.last_name, u.email, u.email_verified, u.profile_image
                       FROM club_memberships m JOIN users u ON u.user_id = m.user_id
                       WHERE m.club_id = :club_id AND m.membership_status = 'active'
                       ORDER BY CASE m.club_role WHEN 'president' THEN 0 ELSE 1 END, m.joined_at ASC");
$stmt->execute(['club_id' => $clubId]);
$leaders = $stmt->fetchAll(PDO::FETCH_ASSOC);
$president = null;
$vicePresident = null;
foreach ($leaders as $leader) {
    if ($leader['club_role'] === 'president' && $president === null) $president = $leader;
    if ($leader['club_role'] === 'vice_president' && $vicePresident === null) $vicePresident = $leader;
}
$isLeader = false;
foreach ($leaders as $leader) {
    if ((int)$leader['user_id'] === $viewerId && $viewerId > 0) $isLeader = true;
}
// Existing requests for this viewer, used to prevent duplicate pending requests.
$myRoleRequests = [];
if ($viewerId > 0) {
    $stmt = $pdo->prepare("SELECT requested_role, request_status FROM role_requests WHERE club_id = ? AND user_id = ? ORDER BY requested_at DESC, request_id DESC");
    $stmt->execute([$clubId, $viewerId]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $request) {
        $role = $request['requested_role'];
        if (!isset($myRoleRequests[$role])) $myRoleRequests[$role] = $request['request_status'];
    }
}

// Render an action only when the position is unfilled; keep existing leader UI.
function clubRoleRequestControl(string $role, int $clubId, ?array $viewer, bool $isLeader, array $myRoleRequests, string $csrf, string $loginUrl, string $tab): void {
    $label = $role === 'president' ? 'President' : 'Vice President';
    if (!$viewer) {
        $target = clubUrl($clubId, $tab);
        echo '<a class="club-btn club-btn-outline" href="' . clubE($loginUrl . '?redirect=' . rawurlencode($target)) . '">Sign in to request</a>';
    } elseif ($isLeader) {
        echo '<span class="club-muted">Already a club leader</span>';
    } elseif (($myRoleRequests[$role] ?? '') === 'pending') {
        echo '<span class="club-muted">Request Pending</span>';
    } else {
        echo '<form method="post" action="' . clubE(clubUrl($clubId, $tab)) . '">';
        echo '<input type="hidden" name="csrf" value="' . clubE($csrf) . '">';
        echo '<input type="hidden" name="action" value="request_role">';
        echo '<input type="hidden" name="requested_role" value="' . clubE($role) . '">';
        echo '<button class="club-btn club-btn-outline" type="submit">Request as ' . clubE($label) . '</button></form>';
    }
}


// Read published club posts, including the related event's real details.
$stmt = $pdo->prepare("SELECT p.post_id, p.title, p.summary, p.content, p.cover_image, p.published_at,
                              cat.category_name, e.event_id, e.event_date, e.start_time, e.end_time,
                              e.venue, e.registration_deadline, e.registration_link
                       FROM posts p
                       JOIN categories cat ON cat.category_id = p.category_id
                       LEFT JOIN events e ON e.post_id = p.post_id
                       WHERE p.club_id = :club_id AND p.status = 'published'
                       ORDER BY COALESCE(p.published_at, p.created_at) DESC, p.post_id DESC
                       LIMIT 60");
$stmt->execute(['club_id' => $clubId]);
$allPosts = $stmt->fetchAll(PDO::FETCH_ASSOC);
$selectedPostId = filter_input(INPUT_GET, 'post', FILTER_VALIDATE_INT);
$selectedPost = null;
foreach ($allPosts as $post) {
    if ((int)$post['post_id'] === (int)$selectedPostId && $selectedPostId) {
        $selectedPost = $post;
        break;
    }
}
$filteredPosts = array_values(array_filter($allPosts, static function (array $p) use ($newsFilter): bool {
    if ($newsFilter === 'events') return !empty($p['event_id']);
    if ($newsFilter === 'news') return empty($p['event_id']);
    return true;
}));

// Create a security token for Follow and Contact forms.
if (empty($_SESSION['club_detail_csrf'])) {
    $_SESSION['club_detail_csrf'] = bin2hex(random_bytes(24));
}
$csrf = $_SESSION['club_detail_csrf'];

// 6) Followers are counted from club_follows, not from a cached counter.
$followReady = true;
$followers = 0;
$isFollowing = false;
try {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM club_follows WHERE club_id = ?');
    $stmt->execute([$clubId]);
    $followers = (int)$stmt->fetchColumn();
    if ($viewerId > 0) {
        $stmt = $pdo->prepare('SELECT 1 FROM club_follows WHERE club_id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$clubId, $viewerId]);
        $isFollowing = (bool)$stmt->fetchColumn();
    }
} catch (PDOException $exception) {
    $followReady = false;
    error_log('Club follows unavailable: ' . $exception->getMessage());
}

// Handle form submissions with POST + CSRF, then redirect to prevent resubmission.
$formError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    $redirectTab = 'overview';
    $postedToken = (string)($_POST['csrf'] ?? '');

    if (!$viewer || !hash_equals($csrf, $postedToken)) {
        $formError = 'Please sign in and try again. Refresh the page if your session has expired.';
    } else {
        try {
            if ($action === 'request_role') {
                $requestedRole = (string)($_POST['requested_role'] ?? '');
                if (!in_array($requestedRole, ['president', 'vice_president'], true)) {
                    $formError = 'Invalid leadership position.';
                } elseif ($club['status'] !== 'active') {
                    $formError = 'This club is not accepting requests.';
                } else {
                    // Lock the club record to serialize requests against other requests.
                    $pdo->beginTransaction();
                    try {
                        $lock = $pdo->prepare('SELECT club_id FROM clubs WHERE club_id = ? FOR UPDATE');
                        $lock->execute([$clubId]);
                        $filled = $pdo->prepare("SELECT COUNT(*) FROM club_memberships WHERE club_id = ? AND club_role = ? AND membership_status = 'active'");
                        $filled->execute([$clubId, $requestedRole]);
                        $alreadyLeader = $pdo->prepare("SELECT COUNT(*) FROM club_memberships WHERE club_id = ? AND user_id = ? AND membership_status = 'active'");
                        $alreadyLeader->execute([$clubId, $viewerId]);
                        $pending = $pdo->prepare("SELECT COUNT(*) FROM role_requests WHERE club_id = ? AND user_id = ? AND request_status = 'pending'");
                        $pending->execute([$clubId, $viewerId]);
                        if ((int)$filled->fetchColumn() > 0) {
                            $formError = 'This leadership position has already been filled.';
                        } elseif ((int)$alreadyLeader->fetchColumn() > 0) {
                            $formError = 'You already hold a leadership position in this club.';
                        } elseif ((int)$pending->fetchColumn() > 0) {
                            $formError = 'You already have a pending request for this club.';
                        } else {
                            $insert = $pdo->prepare("INSERT INTO role_requests (user_id, club_id, requested_role, request_status) VALUES (?, ?, ?, 'pending')");
                            $insert->execute([$viewerId, $clubId, $requestedRole]);
                        }
                        $pdo->commit();
                    } catch (Throwable $e) {
                        if ($pdo->inTransaction()) $pdo->rollBack();
                        throw $e;
                    }
                    if ($formError === '') {
                        header('Location: ' . clubUrl($clubId, 'leadership', ['notice' => 'request_sent']));
                        exit;
                    }
                }
            } elseif ($action === 'follow' && $followReady && $club['status'] === 'active') {
                // Unique (user_id, club_id) avoids duplicate follows.
                $stmt = $pdo->prepare('INSERT IGNORE INTO club_follows (club_id, user_id) VALUES (?, ?)');
                $stmt->execute([$clubId, $viewerId]);
                header('Location: ' . clubUrl($clubId, 'overview', ['notice' => 'followed']));
                exit;
            } elseif ($action === 'unfollow' && $followReady) {
                $stmt = $pdo->prepare('DELETE FROM club_follows WHERE club_id = ? AND user_id = ?');
                $stmt->execute([$clubId, $viewerId]);
                header('Location: ' . clubUrl($clubId, 'overview', ['notice' => 'unfollowed']));
                exit;
            } else {
                $formError = 'This action is not available. Please try again.';
            }
        } catch (PDOException $exception) {
            error_log('Club detail action failed: ' . $exception->getMessage());
            $formError = 'The action could not be saved. Please check your database and try again.';
        }
    }
    $tab = $redirectTab;
}

// All custom contact links are stored in the existing club_links table.
$clubLinks = [];
try {
    $stmt = $pdo->prepare('SELECT link_id, platform, url FROM club_links WHERE club_id = ? ORDER BY link_id ASC');
    $stmt->execute([$clubId]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $link) {
        $url = clubWebUrl($link['url'] ?? '');
        $name = trim((string)($link['platform'] ?? ''));
        if ($url !== '' && $name !== '') {
            $clubLinks[] = ['name' => $name, 'url' => $url];
        }
    }
} catch (PDOException $exception) {
    error_log('Club links unavailable: ' . $exception->getMessage());
}

$banner = clubImageUrl($club['banner'] ?? '');
$logo = clubImageUrl($club['logo'] ?? '');
$notices = [
    'followed' => 'You are now following this club.',
    'unfollowed' => 'You have unfollowed this club.',
    'request_sent' => 'Your leadership request has been submitted for admin review.'
];
$notice = $notices[(string)($_GET['notice'] ?? '')] ?? '';

// Reusable function for cards. Clicking any card opens its FULL post on this page.
function clubRenderCards(array $posts, int $clubId): void { ?>
    <div class="club-story-grid">
    <?php if (!$posts): ?>
        <div class="club-empty">No published posts in this section yet.</div>
    <?php endif; ?>
    <?php foreach ($posts as $post):
        $image = clubImageUrl($post['cover_image'] ?? '');
        $isEvent = !empty($post['event_id']);
        $upcoming = $isEvent && !empty($post['event_date']) && $post['event_date'] >= date('Y-m-d');
    ?>
        <a class="club-story-card" href="<?= clubE(clubUrl($clubId, 'news', ['post' => (int)$post['post_id']])) ?>">
            <div class="club-story-image">
                <?php if ($image): ?>
                    <img src="<?= clubE($image) ?>" alt="" loading="lazy">
                <?php else: ?><span aria-hidden="true">NSBM 360</span><?php endif; ?>
            </div>
            <div class="club-story-content">
                <div class="club-tags"><span><?= $isEvent ? 'EVENT' : clubE($post['category_name']) ?></span><?php if ($upcoming): ?><span class="club-tag-blue">UPCOMING</span><?php endif; ?></div>
                <h3><?= clubE($post['title']) ?></h3>
                <p><?= clubE($post['summary'] ?: clubExcerpt((string)$post['content'])) ?></p>
                <?php if ($isEvent): ?>
                    <div class="club-story-meta"><?= clubE(clubDate($post['event_date'])) ?> · <?= clubE(substr((string)$post['start_time'], 0, 5)) ?> · <?= clubE($post['venue']) ?></div>
                <?php else: ?>
                    <div class="club-story-meta"><?= clubE(clubDate($post['published_at'])) ?></div>
                <?php endif; ?>
                <span class="club-story-action"><?= $isEvent ? 'View event' : 'Read story' ?> &rarr;</span>
            </div>
        </a>
    <?php endforeach; ?>
    </div>
<?php }

// Shared site navigation/footer (same conventions as pages/clubs.php).
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>
<!-- The URL must start with ../ because THIS PHP FILE lives inside /pages. -->
<link rel="stylesheet" href="../assets/css/club_detail.css?v=20261114-3">

<main class="club-detail-main">
    <nav class="club-breadcrumb" aria-label="Breadcrumb"><a href="../index.php">Home</a><span>/</span><a href="clubs.php">Clubs</a><span>/</span><span><?= clubE($club['club_name']) ?></span></nav>

    <!-- Club cover and logo are loaded from the existing clubs table. -->
    <!-- Grid overlap keeps the round logo anchored to the banner, not the browser viewport. -->
    <section class="nsbmclub-hero" aria-label="Club profile images">
        <div class="nsbmclub-cover">
            <?php if ($banner): ?>
                <img src="<?= clubE($banner) ?>"
                     alt="<?= clubE($club['club_name']) ?> banner"
                     class="nsbmclub-cover-img">
            <?php endif; ?>
        </div>
        <div class="nsbmclub-logo">
            <span class="nsbmclub-logo-initials"><?= clubE(strtoupper(substr($club['club_name'], 0, 2))) ?></span>
            <?php if ($logo): ?>
                <img src="<?= clubE($logo) ?>"
                     alt="<?= clubE($club['club_name']) ?> logo"
                     onerror="this.remove()">
            <?php endif; ?>
        </div>
    </section>

    <!-- Club name and actions appear beside the overlapping round logo. -->
    <section class="club-identity">
        <div class="club-identity-left">
            <div>
                <h1><?= clubE($club['club_name']) ?></h1>
                <p><?= $club['status'] === 'active' ? 'An active student community at NSBM.' : 'This club is currently inactive.' ?></p>
            </div>
        </div>
        <div class="club-identity-actions">
            <?php if ($viewer && $followReady): ?>
                <?php if ($club['status'] === 'active' || $isFollowing): ?>
                    <form method="post" action="<?= clubE(clubUrl($clubId)) ?>">
                        <input type="hidden" name="csrf" value="<?= clubE($csrf) ?>">
                        <input type="hidden" name="action" value="<?= $isFollowing ? 'unfollow' : 'follow' ?>">
                        <button class="club-btn <?= $isFollowing ? 'club-btn-soft' : 'club-btn-green' ?>" type="submit"><?= $isFollowing ? '✓ Following' : '+ Follow club' ?></button>
                    </form>
                <?php endif; ?>
            <?php elseif (!$viewer): ?>
                <a class="club-btn club-btn-soft" href="<?= clubE($clubLoginUrl . '?redirect=' . rawurlencode(clubUrl($clubId))) ?>" title="Sign in to follow">+ Follow club</a>
            <?php endif; ?>
            <a class="club-btn club-btn-green" href="<?= clubE(clubUrl($clubId, 'contact')) ?>">✉ Contact & Links</a>
        </div>
    </section>

    <?php if ($notice): ?><p class="club-alert club-alert-success" role="status"><?= clubE($notice) ?></p><?php endif; ?>
    <?php if ($formError): ?><p class="club-alert club-alert-error" role="alert"><?= clubE($formError) ?></p><?php endif; ?>
    <?php if (!$followReady): ?><p class="club-alert club-alert-error">Club following is temporarily unavailable.</p><?php endif; ?>

    <!-- Each tab is a working PHP link, no JavaScript required. -->
    <nav class="club-tabs" aria-label="Club sections">
        <a class="<?= $tab === 'overview' ? 'active' : '' ?>" href="<?= clubE(clubUrl($clubId, 'overview')) ?>">Overview</a>
        <a class="<?= $tab === 'news' ? 'active' : '' ?>" href="<?= clubE(clubUrl($clubId, 'news')) ?>">News &amp; events</a>
        <a class="<?= $tab === 'leadership' ? 'active' : '' ?>" href="<?= clubE(clubUrl($clubId, 'leadership')) ?>">Leadership</a>
        <a class="<?= $tab === 'contact' ? 'active' : '' ?>" href="<?= clubE(clubUrl($clubId, 'contact')) ?>">Contact &amp; Links</a>
    </nav>

    <?php if ($tab === 'overview'): ?>
        <div class="club-layout">
            <div class="club-primary-column">
                <section class="club-panel"><h2>About our community</h2><p class="club-description"><?= nl2br(clubE($club['description'] ?: 'This club has not added a description yet.')) ?></p></section>
                <section class="club-panel">
                    <div class="club-panel-head"><h2>Meet the leadership</h2><a href="<?= clubE(clubUrl($clubId, 'leadership')) ?>">All leaders &rarr;</a></div>
                    <div class="club-leader-row">
                        <?php foreach (['President' => $president, 'Vice President' => $vicePresident] as $roleTitle => $leader): ?>
                            <div class="club-leader-item"><?php clubLeaderAvatar($leader); ?><div><small><?= clubE($roleTitle) ?></small><strong><?= $leader ? clubE(clubFullName($leader)) : 'Position Available' ?></strong><?php if (!$leader && $club['status'] === 'active'): ?><div style="margin-top:8px"><?php clubRoleRequestControl($roleTitle === 'President' ? 'president' : 'vice_president', $clubId, $viewer, $isLeader, $myRoleRequests, $csrf, $clubLoginUrl, 'overview'); ?></div><?php endif; ?></div></div>
                        <?php endforeach; ?>
                    </div>
                </section>
            </div>
            <aside class="club-side-column">
                <section class="club-panel club-follow-panel"><h2>Followers</h2><span class="club-soft-pill"><?= $club['status'] === 'active' ? 'ACTIVE CLUB' : 'INACTIVE CLUB' ?></span><div class="club-big-number"><?= $followReady ? number_format($followers) : '—' ?> <span>followers</span></div><p>Follow this club to show your support.</p></section>
            </aside>
        </div>
        <section class="club-community-section"><div class="club-section-head"><h2>From the community</h2><a href="<?= clubE(clubUrl($clubId, 'news')) ?>">All club posts &rarr;</a></div><?php clubRenderCards(array_slice($allPosts, 0, 3), $clubId); ?></section>

    <?php elseif ($tab === 'news'): ?>
        <section class="club-community-section">
            <div class="club-section-head"><div><h2>News &amp; events</h2><p>Published updates from <?= clubE($club['club_name']) ?>.</p></div><div class="club-filter-pills"><a class="<?= $newsFilter === 'all' ? 'chosen' : '' ?>" href="<?= clubE(clubUrl($clubId, 'news', ['filter' => 'all'])) ?>">All</a><a class="<?= $newsFilter === 'events' ? 'chosen' : '' ?>" href="<?= clubE(clubUrl($clubId, 'news', ['filter' => 'events'])) ?>">Events</a><a class="<?= $newsFilter === 'news' ? 'chosen' : '' ?>" href="<?= clubE(clubUrl($clubId, 'news', ['filter' => 'news'])) ?>">News</a></div></div>
            <?php if ($selectedPost): ?>
                <article class="club-panel club-expanded-story">
                    <a class="club-back-link" href="<?= clubE(clubUrl($clubId, 'news', ['filter' => $newsFilter])) ?>">&larr; Back to all club posts</a>
                    <h2><?= clubE($selectedPost['title']) ?></h2>
                    <p class="club-muted"><?= clubE($selectedPost['category_name']) ?> · <?= clubE(clubDate($selectedPost['published_at'])) ?></p>
                    <?php $expandedImage = clubImageUrl($selectedPost['cover_image'] ?? ''); if ($expandedImage): ?><img class="club-expanded-image" src="<?= clubE($expandedImage) ?>" alt="Post cover image"><?php endif; ?>
                    <p class="club-expanded-text"><?= nl2br(clubE($selectedPost['content'])) ?></p>
                    <?php if ($selectedPost['event_id']): ?>
                        <div class="club-event-details"><h3>Event information</h3><p><strong>Date:</strong> <?= clubE(clubDate($selectedPost['event_date'])) ?></p><p><strong>Time:</strong> <?= clubE(substr((string)$selectedPost['start_time'], 0, 5)) ?> – <?= clubE(substr((string)$selectedPost['end_time'], 0, 5)) ?></p><p><strong>Venue:</strong> <?= clubE($selectedPost['venue']) ?></p>
                        <?php if ($selectedPost['registration_deadline']): ?><p><strong>Registration deadline:</strong> <?= clubE(clubDate($selectedPost['registration_deadline'])) ?></p><?php endif; ?>
                        <?php $registration = clubWebUrl($selectedPost['registration_link'] ?? ''); if ($registration && (!($selectedPost['registration_deadline']) || strtotime($selectedPost['registration_deadline']) >= time())): ?><a href="<?= clubE($registration) ?>" target="_blank" rel="noopener noreferrer" class="club-btn club-btn-green">Open event registration &rarr;</a><?php endif; ?>
                        </div>
                    <?php endif; ?>
                </article>
            <?php else: ?><?php clubRenderCards($filteredPosts, $clubId); ?><?php endif; ?>
        </section>

    <?php elseif ($tab === 'leadership'): ?>
        <section class="club-panel club-narrow"><h2>Club leadership</h2><p class="club-muted">Active assignments from your NSBM 360 club membership records.</p>
            <div class="club-leader-list">
                <?php foreach (['President' => $president, 'Vice President' => $vicePresident] as $roleTitle => $leader): ?>
                    <div class="club-leader-detail"><?php clubLeaderAvatar($leader); ?><div><small><?= clubE($roleTitle) ?></small><strong><?= $leader ? clubE(clubFullName($leader)) : 'Position Available' ?></strong><?php if (!$leader && $club['status'] === 'active'): ?><div style="margin-top:8px"><?php clubRoleRequestControl($roleTitle === 'President' ? 'president' : 'vice_president', $clubId, $viewer, $isLeader, $myRoleRequests, $csrf, $clubLoginUrl, 'leadership'); ?></div><?php endif; ?><?php if ($leader): ?><p><?= (int)$leader['email_verified'] ? 'Verified account' : 'Email not verified' ?></p><?php endif; ?></div><?php if ($leader): ?><a class="club-btn club-btn-outline" href="mailto:<?= clubE($leader['email']) ?>">Email</a><?php endif; ?></div>
                <?php endforeach; ?>
            </div><p class="club-muted">Leadership assignments are maintained by NSBM 360 administrators.</p><a href="<?= clubE(clubUrl($clubId, 'contact')) ?>" class="club-text-link">Club contact links &rarr;</a>
        </section>

    <?php else: /* Contact tab: login checked before rendering */ ?>
        <section class="club-panel club-narrow">
            <h2>Contact &amp; Links</h2>
            <p class="club-muted">Official links shared by <?= clubE($club['club_name']) ?>.</p>
            <?php if (!$clubLinks): ?>
                <p class="club-muted">No contact links have been added yet.</p>
            <?php else: ?>
                <div class="club-social-list">
                    <?php foreach ($clubLinks as $link): ?>
                        <div class="club-social-item">
                            <div><strong><?= clubE($link['name']) ?></strong></div>
                            <a class="club-btn club-btn-outline" href="<?= clubE($link['url']) ?>" target="_blank" rel="noopener noreferrer">Open link &nearr;</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>

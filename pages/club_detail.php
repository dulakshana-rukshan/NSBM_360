<?php
/**
 * NSBM 360 - Public club detail page
 * Location: NSBM_360/pages/club_detail.php
 * Uses the TEAM'S UNCHANGED PDO connection: NSBM_360/config/database.php
 *
 * Tables from original database:
 *   clubs, users, club_memberships, posts, categories, events
 * Optional supporting tables:
 *   club_follows, club_messages, club_social_links
 * Images come from clubs.logo and clubs.banner_image in the database.
 */

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
    $path = trim((string)($value ?? ''));
    if ($path === '' || str_contains($path, '..') || str_contains($path, chr(92))) return '';
    if (preg_match('~^https?://~i', $path)) return $path;
    if (str_starts_with($path, '//') || str_starts_with($path, '/') || preg_match('~^[a-z][a-z0-9+.-]*:~i', $path)) return '';
    return '../' . $path;
}

// Allow only safe HTTP(S) registration URLs from the database.
function clubWebUrl($value): string {
    $url = trim((string)($value ?? ''));
    return filter_var($url, FILTER_VALIDATE_URL) && preg_match('~^https?://~i', $url) ? $url : '';
}

// Only accept actual WhatsApp group invitation and Instagram profile domains.
// Links are read from club_social_links, never hard-coded in this page.
function clubSocialUrl($value, string $network): string {
    $url = clubWebUrl($value);
    if ($url === '') return '';

    $host = strtolower((string)(parse_url($url, PHP_URL_HOST) ?: ''));
    if ($network === 'whatsapp' && $host === 'chat.whatsapp.com') {
        return $url;
    }
    if ($network === 'instagram' && in_array($host, ['instagram.com', 'www.instagram.com'], true)) {
        return $url;
    }
    return '';
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

function clubFullName(array $person): string {
    return trim((string)($person['first_name'] ?? '') . ' ' . (string)($person['last_name'] ?? ''));
}

function clubUrl(int $clubId, string $tab = 'overview', array $extra = []): string {
    return 'club_detail.php?' . http_build_query(array_merge(['id' => $clubId, 'tab' => $tab], $extra));
}

// 1) Select the current club using a verified integer ID, not a fixed club name.
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
$validTabs = ['overview', 'news', 'leadership', 'get-involved', 'contact'];
if (!in_array($tab, $validTabs, true)) $tab = 'overview';
$newsFilter = (string)($_GET['filter'] ?? 'all');
if (!in_array($newsFilter, ['all', 'events', 'news'], true)) $newsFilter = 'all';

// 2) Read verified user's name and email from session ID, not from user input.
$viewerId = (int)($_SESSION['user_id'] ?? 0);
$viewer = null;
if ($viewerId > 0) {
    $stmt = $pdo->prepare('SELECT user_id, first_name, last_name, email, role, email_verified FROM users WHERE user_id = ? LIMIT 1');
    $stmt->execute([$viewerId]);
    $viewer = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!$viewer) $viewerId = 0;
}

// 3) Read current President and Vice President from club_memberships.
$stmt = $pdo->prepare("SELECT m.club_role, u.user_id, u.first_name, u.last_name, u.email, u.email_verified
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
$canViewMessages = $viewer && ($viewer['role'] === 'admin' || $isLeader);

// 4) Read published club posts, including the related event's real details.
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

// 5) Create a security token for Follow and Contact forms.
if (empty($_SESSION['club_detail_csrf'])) {
    $_SESSION['club_detail_csrf'] = bin2hex(random_bytes(24));
}
$csrf = $_SESSION['club_detail_csrf'];

// 6) The FOLLOW and CONTACT features need the optional SQL tables.
$followReady = true;
$contactReady = true;
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
try {
    $pdo->query('SELECT message_id FROM club_messages LIMIT 0');
} catch (PDOException $exception) {
    $contactReady = false;
    error_log('Club messages unavailable: ' . $exception->getMessage());
}

// 7) Handle form submissions with POST + CSRF, then redirect to prevent resubmission.
$formError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    $redirectTab = $action === 'send_message' ? 'contact' : 'get-involved';
    $postedToken = (string)($_POST['csrf'] ?? '');

    if (!$viewer || !hash_equals($csrf, $postedToken)) {
        $formError = 'Please sign in and try again. Refresh the page if your session has expired.';
    } else {
        try {
            if ($action === 'follow' && $followReady && $club['status'] === 'active') {
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
            } elseif ($action === 'send_message' && $contactReady) {
                $subject = trim((string)($_POST['subject'] ?? ''));
                $message = trim((string)($_POST['message'] ?? ''));
                if ($subject === '' || clubLength($subject) > 150 || clubLength($message) < 10 || clubLength($message) > 2000) {
                    $formError = 'Enter a subject (up to 150 characters) and a message between 10 and 2,000 characters.';
                } else {
                    $stmt = $pdo->prepare('INSERT INTO club_messages (club_id, sender_id, subject, message) VALUES (?, ?, ?, ?)');
                    $stmt->execute([$clubId, $viewerId, $subject, $message]);
                    header('Location: ' . clubUrl($clubId, 'contact', ['notice' => 'sent']));
                    exit;
                }
            } else {
                $formError = 'This action is not available. Check whether you imported the test SQL.';
            }
        } catch (PDOException $exception) {
            error_log('Club detail action failed: ' . $exception->getMessage());
            $formError = 'The action could not be saved. Please check your database and try again.';
        }
    }
    $tab = $redirectTab;
}

// 8) Read club social links. The extra table is optional until its SQL is imported.
// No fake links are displayed when the club has not added its own URLs yet.
$whatsappUrl = '';
$instagramUrl = '';
$socialTableReady = true;
try {
    $stmt = $pdo->prepare('SELECT whatsapp_group_url, instagram_profile_url FROM club_social_links WHERE club_id = ? LIMIT 1');
    $stmt->execute([$clubId]);
    $social = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $whatsappUrl = clubSocialUrl($social['whatsapp_group_url'] ?? '', 'whatsapp');
    $instagramUrl = clubSocialUrl($social['instagram_profile_url'] ?? '', 'instagram');
} catch (PDOException $exception) {
    $socialTableReady = false;
    error_log('Club social links unavailable: ' . $exception->getMessage());
}

// 9) Only the assigned leaders and administrators can read contact messages.
$inbox = [];
if ($canViewMessages && $contactReady && $tab === 'contact') {
    $stmt = $pdo->prepare("SELECT cm.message_id, cm.subject, cm.message, cm.created_at,
                                  u.first_name, u.last_name, u.email
                           FROM club_messages cm JOIN users u ON u.user_id = cm.sender_id
                           WHERE cm.club_id = ? ORDER BY cm.created_at DESC, cm.message_id DESC LIMIT 50");
    $stmt->execute([$clubId]);
    $inbox = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$banner = clubImageUrl($club['banner_image'] ?? '');
$logo = clubImageUrl($club['logo'] ?? '');
$notices = [
    'followed' => 'You are now following this club.',
    'unfollowed' => 'You have unfollowed this club.',
    'sent' => 'Your message was saved. Club leaders can read it in their inbox.'
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
                <a class="club-btn club-btn-soft" href="../auth/login.php" title="Sign in to follow">+ Follow club</a>
            <?php endif; ?>
            <a class="club-btn club-btn-green" href="<?= clubE(clubUrl($clubId, 'contact')) ?>">✉ Contact the club</a>
        </div>
    </section>

    <?php if ($notice): ?><p class="club-alert club-alert-success" role="status"><?= clubE($notice) ?></p><?php endif; ?>
    <?php if ($formError): ?><p class="club-alert club-alert-error" role="alert"><?= clubE($formError) ?></p><?php endif; ?>
    <?php if (!$followReady || !$contactReady): ?><p class="club-alert club-alert-error">Follow and messaging require the extra database tables. Import <code>database/club_detail_support.sql</code> from the ZIP.</p><?php endif; ?>

    <!-- Each tab is a working PHP link, no JavaScript required. -->
    <nav class="club-tabs" aria-label="Club sections">
        <a class="<?= $tab === 'overview' ? 'active' : '' ?>" href="<?= clubE(clubUrl($clubId, 'overview')) ?>">Overview</a>
        <a class="<?= $tab === 'news' ? 'active' : '' ?>" href="<?= clubE(clubUrl($clubId, 'news')) ?>">News &amp; events</a>
        <a class="<?= $tab === 'leadership' ? 'active' : '' ?>" href="<?= clubE(clubUrl($clubId, 'leadership')) ?>">Leadership</a>
        <a class="<?= $tab === 'get-involved' ? 'active' : '' ?>" href="<?= clubE(clubUrl($clubId, 'get-involved')) ?>">Get involved</a>
        <a class="<?= $tab === 'contact' ? 'active' : '' ?>" href="<?= clubE(clubUrl($clubId, 'contact')) ?>">Contact</a>
    </nav>

    <?php if ($tab === 'overview'): ?>
        <div class="club-layout">
            <div class="club-primary-column">
                <section class="club-panel"><h2>About our community</h2><p class="club-description"><?= nl2br(clubE($club['description'] ?: 'This club has not added a description yet.')) ?></p></section>
                <section class="club-panel">
                    <div class="club-panel-head"><h2>Meet the leadership</h2><a href="<?= clubE(clubUrl($clubId, 'leadership')) ?>">All leaders &rarr;</a></div>
                    <div class="club-leader-row">
                        <?php foreach (['President' => $president, 'Vice President' => $vicePresident] as $roleTitle => $leader): ?>
                            <div class="club-leader-item"><span class="club-leader-initials"><?= $leader ? clubE(strtoupper(substr($leader['first_name'], 0, 1) . substr($leader['last_name'], 0, 1))) : '—' ?></span><div><small><?= clubE($roleTitle) ?></small><strong><?= $leader ? clubE(clubFullName($leader)) : 'Not assigned' ?></strong></div></div>
                        <?php endforeach; ?>
                    </div>
                </section>
            </div>
            <aside class="club-side-column">
                <section class="club-panel club-follow-panel"><h2>Get involved</h2><span class="club-soft-pill"><?= $club['status'] === 'active' ? 'ACTIVE CLUB' : 'INACTIVE CLUB' ?></span><div class="club-big-number"><?= $followReady ? number_format($followers) : '—' ?> <span>followers</span></div><p>Follow this club for a quick way to keep track of its community.</p><a class="club-btn club-btn-outline" href="<?= clubE(clubUrl($clubId, 'get-involved')) ?>">Explore opportunities &rarr;</a></section>
                <section class="club-tip"><strong>Explore this club</strong><p>Read published stories, find upcoming events, and meet your club leaders.</p></section>
                <?php if ($whatsappUrl || $instagramUrl): ?>
                    <section class="club-panel club-side-social">
                        <h2>Connect with the club</h2>
                        <?php if ($whatsappUrl): ?><a class="club-btn club-btn-social-whatsapp" href="<?= clubE($whatsappUrl) ?>" target="_blank" rel="noopener noreferrer">WhatsApp group ↗</a><?php endif; ?>
                        <?php if ($instagramUrl): ?><a class="club-btn club-btn-social-instagram" href="<?= clubE($instagramUrl) ?>" target="_blank" rel="noopener noreferrer">Instagram profile ↗</a><?php endif; ?>
                    </section>
                <?php endif; ?>
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
                    <div class="club-leader-detail"><span class="club-leader-initials"><?= $leader ? clubE(strtoupper(substr($leader['first_name'], 0, 1) . substr($leader['last_name'], 0, 1))) : '—' ?></span><div><small><?= clubE($roleTitle) ?></small><strong><?= $leader ? clubE(clubFullName($leader)) : 'Not assigned' ?></strong><?php if ($leader): ?><p><?= (int)$leader['email_verified'] ? 'Verified account' : 'Email not verified' ?></p><?php endif; ?></div><?php if ($leader): ?><a class="club-btn club-btn-outline" href="mailto:<?= clubE($leader['email']) ?>">Email</a><?php endif; ?></div>
                <?php endforeach; ?>
            </div><p class="club-muted">Leadership assignments are maintained by NSBM 360 administrators.</p><a href="<?= clubE(clubUrl($clubId, 'get-involved')) ?>" class="club-text-link">Club group links &rarr;</a>
        </section>

    <?php elseif ($tab === 'get-involved'): ?>
        <div class="club-layout">
            <section class="club-panel">
                <h2>Get involved with <?= clubE($club['club_name']) ?></h2>
                <p class="club-muted">Follow this club and join its community through the official group links.</p>

                <h3>Follow the club</h3>
                <p><strong><?= $followReady ? number_format($followers) : '—' ?></strong> followers</p>
                <?php if (!$viewer): ?>
                    <p>Sign in to follow this club.</p>
                    <a class="club-btn club-btn-green" href="../auth/login.php">Sign in to follow</a>
                <?php elseif (!$followReady): ?>
                    <p>Import the club follow table SQL to enable this feature.</p>
                <?php elseif ($club['status'] !== 'active' && !$isFollowing): ?>
                    <p>This club is inactive and is not accepting new followers.</p>
                <?php else: ?>
                    <form method="post" action="<?= clubE(clubUrl($clubId, 'get-involved')) ?>">
                        <input type="hidden" name="csrf" value="<?= clubE($csrf) ?>">
                        <input type="hidden" name="action" value="<?= $isFollowing ? 'unfollow' : 'follow' ?>">
                        <button type="submit" class="club-btn club-btn-green"><?= $isFollowing ? 'Unfollow club' : '+ Follow this club' ?></button>
                    </form>
                <?php endif; ?>

                <hr class="club-separator">
                <h3>Club social links</h3>
                <p class="club-muted">Use these links to join the WhatsApp group or visit the club's Instagram profile.</p>

                <?php if (!$socialTableReady): ?>
                    <p class="club-muted">Social links are not configured yet. Import the included <code>database/club_detail_support.sql</code> file.</p>
                <?php endif; ?>
                <div class="club-social-list">
                    <div class="club-social-item">
                        <div><strong>WhatsApp group</strong><p>Join the club's group chat for community updates.</p></div>
                        <?php if ($whatsappUrl): ?>
                            <a class="club-btn club-btn-social-whatsapp" href="<?= clubE($whatsappUrl) ?>" target="_blank" rel="noopener noreferrer">Join WhatsApp group ↗</a>
                        <?php else: ?>
                            <span class="club-social-unavailable">Link not added yet</span>
                        <?php endif; ?>
                    </div>
                    <div class="club-social-item">
                        <div><strong>Instagram profile</strong><p>See photos, stories and club announcements.</p></div>
                        <?php if ($instagramUrl): ?>
                            <a class="club-btn club-btn-social-instagram" href="<?= clubE($instagramUrl) ?>" target="_blank" rel="noopener noreferrer">Open Instagram ↗</a>
                        <?php else: ?>
                            <span class="club-social-unavailable">Link not added yet</span>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
            <aside class="club-side-column">
                <div class="club-tip">
                    <strong>Stay connected</strong>
                    <p>Follow this club here and use its official social links. Links are different for every club.</p>
                </div>
            </aside>
        </div>

    <?php else: /* Contact tab */ ?>
        <div class="club-layout">
            <section class="club-panel"><h2>Contact <?= clubE($club['club_name']) ?></h2><p class="club-muted">Messages are saved securely in NSBM 360 and visible to this club's active leaders and platform administrators.</p>
                <?php if (!$contactReady): ?><p>Import the included SQL file to enable club messages.</p>
                <?php elseif (!$viewer): ?><p>Please sign in before sending a message.</p><a class="club-btn club-btn-green" href="../login.php">Sign in</a>
                <?php else: ?>
                    <form method="post" action="<?= clubE(clubUrl($clubId, 'contact')) ?>" class="club-stack-form"><input type="hidden" name="csrf" value="<?= clubE($csrf) ?>"><input type="hidden" name="action" value="send_message"><label for="subject">Subject</label><input id="subject" name="subject" maxlength="150" required value="<?= clubE($_POST['subject'] ?? '') ?>" placeholder="What would you like to ask?"><label for="message">Message (10–2,000 characters)</label><textarea id="message" name="message" rows="6" minlength="10" maxlength="2000" required placeholder="Write a message to club leadership"><?= clubE($_POST['message'] ?? '') ?></textarea><button class="club-btn club-btn-green" type="submit">Send message</button></form>
                <?php endif; ?>
            </section>
            <aside class="club-side-column"><div class="club-panel"><h2>Club contacts</h2><?php if ($president): ?><p><strong>President</strong><br><?= clubE(clubFullName($president)) ?></p><?php endif; ?><?php if ($vicePresident): ?><p><strong>Vice President</strong><br><?= clubE(clubFullName($vicePresident)) ?></p><?php endif; ?><?php if (!$president && !$vicePresident): ?><p>No active leaders have been assigned yet.</p><?php endif; ?></div></aside>
        </div>
        <?php if ($canViewMessages && $contactReady): ?>
            <section class="club-panel club-inbox"><h2>Club message inbox</h2><p class="club-muted">Only the club's active leaders and administrators can see these messages.</p><?php if (!$inbox): ?><p>No messages received yet.</p><?php endif; ?><?php foreach ($inbox as $item): ?><article class="club-inbox-item"><div><strong><?= clubE($item['subject']) ?></strong><span><?= clubE(clubFullName($item)) ?> · <?= clubE(clubDate($item['created_at'])) ?></span></div><p><?= nl2br(clubE($item['message'])) ?></p><a class="club-text-link" href="mailto:<?= clubE($item['email']) ?>?subject=<?= rawurlencode('Re: ' . $item['subject']) ?>">Reply by email &rarr;</a></article><?php endforeach; ?></section>
        <?php endif; ?>
    <?php endif; ?>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>

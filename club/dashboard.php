
<?php

// --------------------------------------------------
// 1. PRESIDENT AUTHENTICATION
// --------------------------------------------------

require_once __DIR__ . '/president_auth.php';
require_once __DIR__ . '/president_functions.php';

$pageTitle = 'President Dashboard';
$pageCSS = '/NSBM_360/club/president.css';
$isPresidentPage = true;


// --------------------------------------------------
// SOCIAL MEDIA ICON HELPER
// --------------------------------------------------

$getSocialIcon = static function ($platform) {

    $platform = strtolower(trim($platform));

    $icons = [
        'facebook'  => 'bi bi-facebook',
        'instagram' => 'bi bi-instagram',
        'whatsapp'  => 'bi bi-whatsapp',
        'youtube'   => 'bi bi-youtube',
        'tiktok'    => 'bi bi-tiktok',
        'linkedin'  => 'bi bi-linkedin',
        'twitter'   => 'bi bi-twitter-x',
        'x'         => 'bi bi-twitter-x',
        'website'   => 'bi bi-globe',
        'telegram'  => 'bi bi-telegram',
        'discord'   => 'bi bi-discord'
    ];

    return $icons[$platform] ?? 'bi bi-link-45deg';
};


// --------------------------------------------------
// 2. CLUB PROFILE AND SOCIAL LINKS EDITING
// --------------------------------------------------

if (empty($_SESSION['dashboard_club_csrf'])) {
    $_SESSION['dashboard_club_csrf'] = bin2hex(random_bytes(32));
}

$profileErrors = [];
$profileSuccess = '';


// --------------------------------------------------
// HANDLE SAVE CHANGES
// --------------------------------------------------

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['save_club_profile'])
) {

    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['dashboard_club_csrf'], $token)
    ) {
        $profileErrors[] = 'Invalid request. Please refresh and try again.';
    }

    $description = trim($_POST['description'] ?? '');

    if (mb_strlen($description, 'UTF-8') > 500) {
        $profileErrors[] = 'Description cannot exceed 500 characters.';
    }


    // --------------------------------------------------
    // VALIDATE SOCIAL MEDIA LINKS
    // --------------------------------------------------

    $submittedIds = $_POST['link_id'] ?? [];
    $submittedPlatforms = $_POST['platform'] ?? [];
    $submittedUrls = $_POST['url'] ?? [];
    $deletedLinks = $_POST['delete_link'] ?? [];

    if (
        !is_array($submittedIds) ||
        !is_array($submittedPlatforms) ||
        !is_array($submittedUrls) ||
        !is_array($deletedLinks)
    ) {
        $profileErrors[] = 'Invalid social media link information.';
    }

    $validatedLinks = [];
    $deletedLinkIds = [];

    if (empty($profileErrors)) {

        foreach ($deletedLinks as $deleteId) {

            if (
                filter_var($deleteId, FILTER_VALIDATE_INT) === false ||
                (int) $deleteId <= 0
            ) {
                $profileErrors[] = 'Invalid social media link selected.';
                break;
            }

            $deletedLinkIds[] = (int) $deleteId;
        }


        if (empty($profileErrors)) {

            $rowCount = max(
                count($submittedIds),
                count($submittedPlatforms),
                count($submittedUrls)
            );

            for ($i = 0; $i < $rowCount; $i++) {

                $linkId = $submittedIds[$i] ?? '';
                $platform = trim($submittedPlatforms[$i] ?? '');
                $url = trim($submittedUrls[$i] ?? '');

                $linkId = ($linkId === '') ? null : (int) $linkId;


                if ($linkId === null && $platform === '' && $url === '') {
                    continue;
                }


                if (
                    $linkId !== null &&
                    in_array($linkId, $deletedLinkIds, true)
                ) {
                    continue;
                }


                if ($linkId !== null && $platform === '' && $url === '') {
                    $deletedLinkIds[] = $linkId;
                    continue;
                }


                if ($platform === '' || $url === '') {
                    $profileErrors[] = 'Please enter both platform name and URL for every social media link.';
                    break;
                }

                if (mb_strlen($platform, 'UTF-8') > 50) {
                    $profileErrors[] = 'Platform name cannot exceed 50 characters.';
                    break;
                }

                if (mb_strlen($url, 'UTF-8') > 500) {
                    $profileErrors[] = 'Social media URL cannot exceed 500 characters.';
                    break;
                }

                if (!filter_var($url, FILTER_VALIDATE_URL)) {
                    $profileErrors[] = 'Please enter a valid URL for ' . $platform . '.';
                    break;
                }

                $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

                if (!in_array($scheme, ['http', 'https'], true)) {
                    $profileErrors[] = 'Social media URLs must start with http:// or https://.';
                    break;
                }


                $validatedLinks[] = [
                    'link_id' => $linkId,
                    'platform' => $platform,
                    'url' => $url
                ];
            }
        }
    }


    $newLogo = null;
    $newBanner = null;
    $currentClub = null;


    // --------------------------------------------------
    // SAVE PROFILE AND SOCIAL LINKS
    // --------------------------------------------------

    if (empty($profileErrors)) {

        try {

            $currentStmt = $pdo->prepare("
                SELECT logo, banner
                FROM clubs
                WHERE club_id = ?
                LIMIT 1
            ");

            $currentStmt->execute([$clubId]);
            $currentClub = $currentStmt->fetch(PDO::FETCH_ASSOC);

            if (!$currentClub) {
                throw new Exception('Club not found.');
            }


            $newLogo = save_club_image(
                $_FILES['logo'] ?? [],
                'logos'
            );

            $newBanner = save_club_image(
                $_FILES['banner'] ?? [],
                'banner'
            );


            $logo = $newLogo ?? $currentClub['logo'];
            $banner = $newBanner ?? $currentClub['banner'];


            $pdo->beginTransaction();


            $updateStmt = $pdo->prepare("
                UPDATE clubs
                SET
                    description = :description,
                    logo = :logo,
                    banner = :banner
                WHERE club_id = :club_id
            ");

            $updateStmt->execute([
                ':description' => $description,
                ':logo' => $logo,
                ':banner' => $banner,
                ':club_id' => $clubId
            ]);


            // DELETE SELECTED SOCIAL LINKS

            $deleteStmt = $pdo->prepare("
                DELETE FROM club_links
                WHERE link_id = ?
                  AND club_id = ?
            ");

            foreach (array_unique($deletedLinkIds) as $deleteId) {

                $deleteStmt->execute([
                    $deleteId,
                    $clubId
                ]);
            }


            // UPDATE OR INSERT SOCIAL LINKS

            $checkLinkStmt = $pdo->prepare("
                SELECT link_id
                FROM club_links
                WHERE link_id = ?
                  AND club_id = ?
                LIMIT 1
            ");

            $updateLinkStmt = $pdo->prepare("
                UPDATE club_links
                SET platform = ?, url = ?
                WHERE link_id = ?
                  AND club_id = ?
            ");

            $insertLinkStmt = $pdo->prepare("
                INSERT INTO club_links (club_id, platform, url)
                VALUES (?, ?, ?)
            ");


            foreach ($validatedLinks as $link) {

                if ($link['link_id'] !== null) {

                    $checkLinkStmt->execute([
                        $link['link_id'],
                        $clubId
                    ]);

                    if (!$checkLinkStmt->fetchColumn()) {
                        throw new Exception('Invalid social media link.');
                    }

                    $updateLinkStmt->execute([
                        $link['platform'],
                        $link['url'],
                        $link['link_id'],
                        $clubId
                    ]);

                } else {

                    $insertLinkStmt->execute([
                        $clubId,
                        $link['platform'],
                        $link['url']
                    ]);
                }
            }


            $pdo->commit();


            if ($newLogo) {
                delete_club_image($currentClub['logo']);
            }

            if ($newBanner) {
                delete_club_image($currentClub['banner']);
            }


            $_SESSION['club_profile_success'] =
            'Club profile and social links updated successfully!';

            header('Location: dashboard.php');
            exit;


        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if ($newLogo) {
                delete_club_image($newLogo);
            }

            if ($newBanner) {
                delete_club_image($newBanner);
            }

            if ($e instanceof RuntimeException) {
                $profileErrors[] = $e->getMessage();
            } else {
                $profileErrors[] = 'Unable to update club profile. Please try again.';
            }
        }
    }
}


// --------------------------------------------------
// 3. GET LATEST CLUB PROFILE
// --------------------------------------------------

$profileStmt = $pdo->prepare("
    SELECT description, logo, banner
    FROM clubs
    WHERE club_id = ?
    LIMIT 1
");

$profileStmt->execute([$clubId]);
$clubProfile = $profileStmt->fetch(PDO::FETCH_ASSOC);

if (!$clubProfile) {
    exit('Club profile not found.');
}


// --------------------------------------------------
// 4. GET CLUB SOCIAL LINKS
// --------------------------------------------------

$linksStmt = $pdo->prepare("
    SELECT link_id, platform, url
    FROM club_links
    WHERE club_id = ?
    ORDER BY link_id ASC
");

$linksStmt->execute([$clubId]);
$clubLinks = $linksStmt->fetchAll(PDO::FETCH_ASSOC);


if (isset($_SESSION['club_profile_success'])) {
    $profileSuccess = $_SESSION['club_profile_success'];
    unset($_SESSION['club_profile_success']);
}


// --------------------------------------------------
// 5. GET POST STATISTICS
// --------------------------------------------------

$statsQuery = "
    SELECT
        COUNT(*) AS total_posts,
        COALESCE(SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END), 0) AS published_posts,
        COALESCE(SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END), 0) AS draft_posts
    FROM posts
    WHERE club_id = :club_id
";

$statsStmt = $pdo->prepare($statsQuery);
$statsStmt->execute(['club_id' => $clubId]);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);


// --------------------------------------------------
// 6. GET TOTAL EVENTS
// --------------------------------------------------

$eventCountQuery = "
    SELECT COUNT(*)
    FROM events e
    INNER JOIN posts p ON e.post_id = p.post_id
    WHERE p.club_id = :club_id
";

$eventCountStmt = $pdo->prepare($eventCountQuery);
$eventCountStmt->execute(['club_id' => $clubId]);
$totalEvents = (int) $eventCountStmt->fetchColumn();


// --------------------------------------------------
// 7. GET MONTHLY ACTIVITY
// --------------------------------------------------

$monthlyQuery = "
    SELECT
        COUNT(*) AS monthly_posts,
        COALESCE(SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END), 0) AS monthly_published
    FROM posts
    WHERE club_id = :club_id
      AND created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
      AND created_at < DATE_ADD(
          DATE_FORMAT(CURDATE(), '%Y-%m-01'),
          INTERVAL 1 MONTH
      )
";

$monthlyStmt = $pdo->prepare($monthlyQuery);
$monthlyStmt->execute(['club_id' => $clubId]);
$monthlyActivity = $monthlyStmt->fetch(PDO::FETCH_ASSOC);


// --------------------------------------------------
// 8. GET CATEGORY OVERVIEW
// --------------------------------------------------

$categoryQuery = "
    SELECT
        c.category_name,
        COUNT(p.post_id) AS post_count
    FROM categories c
    INNER JOIN posts p ON c.category_id = p.category_id
    WHERE p.club_id = :club_id
    GROUP BY c.category_id, c.category_name
    ORDER BY post_count DESC
";

$categoryStmt = $pdo->prepare($categoryQuery);
$categoryStmt->execute(['club_id' => $clubId]);
$categoryOverview = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);


// --------------------------------------------------
// 9. GET LATEST POSTS
// --------------------------------------------------

$postsQuery = "
    SELECT
        p.post_id,
        p.title,
        p.status,
        p.created_at,
        c.category_name
    FROM posts p
    LEFT JOIN categories c ON p.category_id = c.category_id
    WHERE p.club_id = :club_id
    ORDER BY p.created_at DESC
    LIMIT 5
";

$postsStmt = $pdo->prepare($postsQuery);
$postsStmt->execute(['club_id' => $clubId]);
$latestPosts = $postsStmt->fetchAll(PDO::FETCH_ASSOC);


// --------------------------------------------------
// 10. GET NEXT UPCOMING EVENT
// --------------------------------------------------

$eventQuery = "
    SELECT
        e.event_id,
        e.event_date,
        e.start_time,
        e.venue,
        p.post_id,
        p.title
    FROM events e
    INNER JOIN posts p ON e.post_id = p.post_id
    WHERE p.club_id = :club_id
      AND p.status = 'published'
      AND e.event_date >= CURDATE()
    ORDER BY e.event_date ASC, e.start_time ASC
    LIMIT 1
";

$eventStmt = $pdo->prepare($eventQuery);
$eventStmt->execute(['club_id' => $clubId]);
$upcomingEvent = $eventStmt->fetch(PDO::FETCH_ASSOC);


// --------------------------------------------------
// 11. INCLUDE HEADER
// --------------------------------------------------

require_once __DIR__ . '/president_header.php';

?>


<div class="page-shell">

    <?php require_once __DIR__ . '/president_sidebar.php'; ?>

    <main class="main-content">

        <!-- PAGE HEADER -->

        <div class="page-header">

            <div>
                <h1>Dashboard</h1>
                <p>Welcome back, <?= e($userName) ?>!</p>
            </div>

            <div class="page-header-actions">
                <a href="create-post.php" class="button button-primary">
                    <i class="bi bi-plus-circle"></i>
                    Create New Post
                </a>
            </div>

        </div>


        <!-- CLUB PROFILE -->

        <form
            method="POST"
            enctype="multipart/form-data"
            class="club-profile-section"
            id="clubProfileForm"
        >

            <input type="hidden" name="csrf_token"
                   value="<?= e($_SESSION['dashboard_club_csrf']) ?>">

            <input type="hidden" name="save_club_profile" value="1">


            <?php if ($profileSuccess): ?>
                <div class="club-inline-message success">
                    <?= e($profileSuccess) ?>
                </div>
            <?php endif; ?>


            <?php if (!empty($profileErrors)): ?>
                <div class="club-inline-message error">
                    <?php foreach ($profileErrors as $error): ?>
                        <p><?= e($error) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>


            <!-- BANNER -->

            <div class="club-profile-banner" id="clubBannerPreview">

                <?php if (!empty($clubProfile['banner'])): ?>
                    <img
                        src="/NSBM_360/<?= e(ltrim($clubProfile['banner'], '/')) ?>"
                        id="clubBannerImage"
                        alt="Club Banner"
                    >
                <?php else: ?>
                    <img src="" id="clubBannerImage"
                         alt="Club Banner" hidden>
                <?php endif; ?>

                <label for="clubBannerInput"
                       class="club-image-edit-overlay"
                       title="Change club banner">
                    <i class="bi bi-camera"></i>
                    Change Banner
                </label>

            </div>


            <input type="file" name="banner" id="clubBannerInput"
                   accept="image/jpeg,image/png,image/webp" hidden>


            <!-- LOGO AND CLUB DETAILS -->

            <div class="club-profile-content">

                <div class="club-profile-logo">

                    <label for="clubLogoInput"
                           class="club-logo-edit"
                           title="Change club logo">

                        <?php if (!empty($clubProfile['logo'])): ?>

                            <img
                                id="clubLogoPreview"
                                src="/NSBM_360/<?= e(ltrim($clubProfile['logo'], '/')) ?>"
                                alt="<?= e($clubName) ?> Logo"
                            >

                            <div class="club-logo-placeholder"
                                 id="clubLogoPlaceholder" hidden>
                                <i class="bi bi-people-fill"></i>
                            </div>

                        <?php else: ?>

                            <img id="clubLogoPreview" src=""
                                 alt="<?= e($clubName) ?> Logo" hidden>

                            <div class="club-logo-placeholder"
                                 id="clubLogoPlaceholder">
                                <i class="bi bi-people-fill"></i>
                            </div>

                        <?php endif; ?>

                        <span class="club-logo-edit-overlay">
                            <i class="bi bi-camera"></i>
                        </span>

                    </label>

                </div>


                <input type="file" name="logo" id="clubLogoInput"
                       accept="image/jpeg,image/png,image/webp" hidden>


                <div class="club-profile-details">

                    <span class="club-label">CLUB PRESIDENT WORKSPACE</span>


                    <div class="club-profile-heading-row">

                        <h2><?= e($clubName) ?></h2>

                        <span class="club-role-label">
                            <i class="bi bi-shield-check"></i>
                            <?= e(ucfirst(str_replace('_', ' ', $clubRole))) ?>
                        </span>

                    </div>


                    <span class="club-profile-type">
                        <?= ($clubType === 'sports') ? 'Sports Club' : 'General Club' ?>
                    </span>


                    <!-- DESCRIPTION DISPLAY -->

                    <button type="button"
                            class="club-description-display"
                            id="clubDescriptionDisplay">

                        <?= !empty($clubProfile['description'])
                            ? e($clubProfile['description'])
                            : 'Click here to add a club description.' ?>

                        <i class="bi bi-pencil"></i>

                    </button>


                    <!-- DESCRIPTION EDITOR -->

                    <div class="club-description-editor"
                         id="clubDescriptionEditor" hidden>

                        <textarea name="description"
                                  id="clubDescriptionInput"
                                  maxlength="500"
                                  rows="4"
                                  placeholder="Write a short description about your club..."><?= e($clubProfile['description'] ?? '') ?></textarea>

                        <small>Maximum 500 characters.</small>

                    </div>


                    <!-- OFFICIAL LINKS -->

                    <div class="club-social-section">

                        <div class="club-social-heading">

                            <div>
                                <h3>Official Links</h3>
                                <p>Connect with our club on social media</p>
                            </div>

                            <button
                                type="button"
                                class="club-social-edit-toggle"
                                id="editSocialLinksBtn"
                                aria-label="Edit official links"
                                title="Edit official links"
                            >
                                <i class="bi bi-pencil-square"></i>
                            </button>

                        </div>


                        <!-- PUBLIC LINKS: ICONS ONLY -->

                        <div class="official-links-display">

                            <?php
                            $hasVisibleLinks = false;
                            ?>

                            <?php foreach ($clubLinks as $link): ?>

                                <?php
                                $linkUrl = trim($link['url']);
                                $linkScheme = strtolower(
                                    (string) parse_url($linkUrl, PHP_URL_SCHEME)
                                );

                                $validLink = filter_var($linkUrl, FILTER_VALIDATE_URL)
                                    && in_array($linkScheme, ['http', 'https'], true);
                                ?>

                                <?php if ($validLink): ?>

                                    <?php $hasVisibleLinks = true; ?>

                                    <a
                                        href="<?= e($linkUrl) ?>"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="official-link-icon"
                                        title="<?= e($link['platform']) ?>"
                                        aria-label="<?= e($link['platform']) ?>"
                                        data-platform="<?= e(strtolower(trim($link['platform']))) ?>"
                                    >
                                        <i class="<?= e($getSocialIcon($link['platform'])) ?>"></i>
                                    </a>

                                <?php endif; ?>

                            <?php endforeach; ?>


                            <?php if (!$hasVisibleLinks): ?>
                                <p class="official-links-empty">
                                    No official links added yet.
                                </p>
                            <?php endif; ?>

                        </div>


                        <!-- HIDDEN EDIT PANEL -->

                        <div
                            class="club-social-edit-panel"
                            id="clubSocialEditPanel"
                            hidden
                        >

                            <div class="club-social-edit-heading">

                                <div>
                                    <h4>Edit Official Links</h4>
                                    <p>Add, update or remove your club's links.</p>
                                </div>

                                <button
                                    type="button"
                                    class="club-social-add-btn"
                                    id="addSocialLink"
                                    title="Add another link"
                                >
                                    <i class="bi bi-plus-lg"></i>
                                    Add Link
                                </button>

                            </div>


                            <div class="club-social-list" id="clubSocialList">

                                <?php foreach ($clubLinks as $link): ?>

                                    <div class="club-social-row">

                                        <input type="hidden"
                                               name="link_id[]"
                                               value="<?= (int) $link['link_id'] ?>">

                                        <div class="club-social-icon">
                                            <i class="<?= e($getSocialIcon($link['platform'])) ?>"></i>
                                        </div>


                                        <div class="club-social-inputs">

                                            <input
                                                type="text"
                                                name="platform[]"
                                                maxlength="50"
                                                placeholder="Platform name (e.g. Facebook)"
                                                value="<?= e($link['platform']) ?>"
                                            >

                                            <input
                                                type="url"
                                                name="url[]"
                                                maxlength="500"
                                                placeholder="https://..."
                                                value="<?= e($link['url']) ?>"
                                            >

                                        </div>


                                        <label class="club-social-delete">

                                            <input
                                                type="checkbox"
                                                name="delete_link[]"
                                                value="<?= (int) $link['link_id'] ?>"
                                            >

                                            <span title="Delete this link">
                                                <i class="bi bi-trash"></i>
                                            </span>

                                        </label>

                                    </div>

                                <?php endforeach; ?>

                            </div>

                        </div>

                    </div>


                    <!-- SAVE CHANGES -->

                    <div class="club-profile-edit-actions">

                        <button type="submit" class="club-profile-save">
                            <i class="bi bi-check-circle"></i>
                            Save Changes
                        </button>

                    </div>

                </div>

            </div>

        </form>


        <!-- STATISTICS -->

        <section class="stats-grid">

            <div class="stat-card">
                <div class="stat-icon total-icon">
                    <i class="bi bi-file-earmark-text"></i>
                </div>
                <div>
                    <p>Total Posts</p>
                    <h2><?= (int) ($stats['total_posts'] ?? 0) ?></h2>
                </div>
            </div>


            <div class="stat-card">
                <div class="stat-icon published-icon">
                    <i class="bi bi-check-circle"></i>
                </div>
                <div>
                    <p>Published Posts</p>
                    <h2><?= (int) ($stats['published_posts'] ?? 0) ?></h2>
                </div>
            </div>


            <div class="stat-card">
                <div class="stat-icon draft-icon">
                    <i class="bi bi-pencil-square"></i>
                </div>
                <div>
                    <p>Draft Posts</p>
                    <h2><?= (int) ($stats['draft_posts'] ?? 0) ?></h2>
                </div>
            </div>


            <div class="stat-card">
                <div class="stat-icon archived-icon">
                    <i class="bi bi-calendar-event"></i>
                </div>
                <div>
                    <p>Total Events</p>
                    <h2><?= $totalEvents ?></h2>
                </div>
            </div>

        </section>


        <!-- MONTHLY ACTIVITY -->

        <section class="content-card">

            <div class="section-heading">

                <div>
                    <h2>Monthly Activity</h2>
                    <p>Your club's activity this month</p>
                </div>

                <span class="month-label"><?= e(date('F Y')) ?></span>

            </div>


            <div class="monthly-activity-grid">

                <div class="activity-item">
                    <div class="activity-icon">
                        <i class="bi bi-file-earmark-plus"></i>
                    </div>
                    <div>
                        <p>Posts Created</p>
                        <h3><?= (int) ($monthlyActivity['monthly_posts'] ?? 0) ?></h3>
                    </div>
                </div>


                <div class="activity-item">
                    <div class="activity-icon">
                        <i class="bi bi-send-check"></i>
                    </div>
                    <div>
                        <p>Published This Month</p>
                        <h3><?= (int) ($monthlyActivity['monthly_published'] ?? 0) ?></h3>
                    </div>
                </div>

            </div>

        </section>


        <!-- CATEGORY OVERVIEW -->

        <section class="content-card">

            <div class="section-heading">
                <div>
                    <h2>Post Category Overview</h2>
                    <p>Posts grouped by category</p>
                </div>
            </div>


            <?php if (!empty($categoryOverview)): ?>

                <div class="category-overview-list">

                    <?php foreach ($categoryOverview as $category): ?>

                        <div class="category-overview-item">

                            <div class="category-overview-info">
                                <span><?= e($category['category_name']) ?></span>
                                <strong><?= (int) $category['post_count'] ?></strong>
                            </div>

                            <div class="category-progress">
                                <div
                                    class="category-progress-fill"
                                    style="width: <?= min(100, ((int) $category['post_count'] / max(1, (int) $stats['total_posts'])) * 100) ?>%;"
                                ></div>
                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>

            <?php else: ?>

                <div class="empty-state">
                    <i class="bi bi-pie-chart"></i>
                    <h3>No category data yet</h3>
                    <p>Create posts to see your category overview.</p>
                </div>

            <?php endif; ?>

        </section>


        <!-- QUICK ACTIONS -->

        <section class="content-card">

            <div class="section-heading">
                <div>
                    <h2>Quick Actions</h2>
                    <p>Manage your club efficiently</p>
                </div>
            </div>


            <div class="quick-actions-grid">

                <a href="create-post.php" class="quick-action-item">
                    <i class="bi bi-file-earmark-plus"></i>
                    <span>Create New Post</span>
                </a>

                <a href="create-event.php" class="quick-action-item">
                    <i class="bi bi-calendar-plus"></i>
                    <span>Create Event</span>
                </a>

                <a href="manage-posts.php" class="quick-action-item">
                    <i class="bi bi-folder2-open"></i>
                    <span>Manage Posts</span>
                </a>

                <a href="/NSBM_360/profile.php" class="quick-action-item">
                    <i class="bi bi-person-circle"></i>
                    <span>My Profile</span>
                </a>

            </div>

        </section>


        <!-- RECENT POSTS -->

        <section class="content-card">

            <div class="section-heading">

                <div>
                    <h2>Recent Posts</h2>
                    <p>Your club's latest posts</p>
                </div>

                <a href="manage-posts.php" class="text-link">View All</a>

            </div>


            <?php if (!empty($latestPosts)): ?>

                <div class="table-responsive">

                    <table class="data-table">

                        <thead>
                            <tr>
                                <th>Post Title</th>
                                <th>Category</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>


                        <tbody>

                            <?php foreach ($latestPosts as $post): ?>

                                <tr>

                                    <td><?= e($post['title']) ?></td>

                                    <td><?= e($post['category_name'] ?? '-') ?></td>

                                    <td><?= e(display_date($post['created_at'])) ?></td>

                                    <td>
                                        <span class="status-badge <?= e(post_status_class($post['status'])) ?>">
                                            <?= e(ucfirst($post['status'])) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <a
                                            href="view-post.php?id=<?= (int) $post['post_id'] ?>"
                                            class="table-link"
                                        >
                                            View
                                        </a>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="empty-state">
                    <i class="bi bi-file-earmark"></i>
                    <h3>No posts yet</h3>
                    <p>Create your first club post.</p>
                    <a href="create-post.php" class="button button-primary">
                        Create Post
                    </a>
                </div>

            <?php endif; ?>

        </section>


        <!-- UPCOMING EVENT -->

        <section class="content-card">

            <div class="section-heading">
                <div>
                    <h2>Upcoming Event</h2>
                    <p>Your next scheduled club event</p>
                </div>
            </div>


            <?php if ($upcomingEvent): ?>

                <div class="event-preview">

                    <div class="event-date-icon">
                        <i class="bi bi-calendar-event"></i>
                    </div>


                    <div class="event-details">

                        <h3><?= e($upcomingEvent['title']) ?></h3>

                        <p>
                            <i class="bi bi-calendar"></i>
                            <?= e(display_date($upcomingEvent['event_date'])) ?>
                        </p>

                        <p>
                            <i class="bi bi-clock"></i>
                            <?= e(date('h:i A', strtotime($upcomingEvent['start_time']))) ?>
                        </p>

                        <p>
                            <i class="bi bi-geo-alt"></i>
                            <?= e($upcomingEvent['venue']) ?>
                        </p>

                    </div>


                    <a
                        href="view-event.php?id=<?= (int) $upcomingEvent['event_id'] ?>"
                        class="table-link"
                    >
                        View Event
                    </a>

                </div>

            <?php else: ?>

                <div class="empty-state">
                    <i class="bi bi-calendar-x"></i>
                    <h3>No upcoming events</h3>
                    <p>There are no upcoming published events.</p>
                    <a href="create-post.php" class="button button-primary">
                        Create Event
                    </a>
                </div>

            <?php endif; ?>

        </section>


    </main>

</div>


<!-- LOAD EXISTING DASHBOARD JAVASCRIPT -->
<script src="/NSBM_360/assets/js/president-dashboard.js" defer></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

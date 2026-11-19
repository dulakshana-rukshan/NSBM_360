
<?php
// --- NEWS DETAILS PAGE ---

//  Connect to database using PDO
require_once __DIR__ . '/../config/database.php';

$pageCSS = '../assets/css/news_details.css';

function post_image_url(?string $path): string
{
    $fallback = 'https://placehold.co/800x450?text=No+Image';

    if (empty(trim($path ?? ''))) {
        return $fallback;
    }

    $path = trim(str_replace('\\', '/', $path));

    // Full external URL
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }

    // Remove relative directory prefixes
    $path = preg_replace('#^(\.\./|\./)+#', '', $path);

    // Already has project folder
    if (str_starts_with($path, '/NSBM_360/')) {
        return $path;
    }

    if (str_starts_with($path, 'NSBM_360/')) {
        return '/' . $path;
    }

    // Standard project-relative path
    return '/NSBM_360/' . ltrim($path, '/');
}

// Get the post ID from the URL safely
$post_id = isset($_GET['id']) ? intval($_GET['id']) : 1;

// Fetch article data using PDO prepared statements
$sql = "SELECT p.*, 
               c.category_name, 
               CONCAT(u.first_name, ' ', u.last_name) AS author_name
        FROM posts p
        LEFT JOIN categories c ON p.category_id = c.category_id
        LEFT JOIN users u ON p.created_by = u.user_id
        WHERE p.post_id = :post_id
        AND TRIM(LOWER(p.status)) = 'published'";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['post_id' => $post_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Article Query Error: " . $e->getMessage());
    $row = false;
}

// Fallback if post is not found or unpublished
if (!$row) {
    $row = [
        "title" => "Story not found",
        "summary" => "The requested story could not be found.",
        "category_name" => "CAMPUS NEWS",
        "author_name" => "NSBM Editorial",
        "published_at" => date("Y-m-d H:i:s"),
        "created_at" => date("Y-m-d H:i:s"),
        "cover_image" => "https://via.placeholder.com/800x450",
        "content" => "<p>Please check the article link or try again later.</p>"
    ];
}

// Configure Navbar Active Page based on category
$categoryId = (int)($row['category_id'] ?? 0);

switch ($categoryId) {
    case 1: // News
        $activePage = 'news';
        break;

    case 2: // Events
        $activePage = 'events';
        break;

    case 3: // Sports
        $activePage = 'sports';
        break;

    case 6: // Academic
        $activePage = 'academic';
        break;

    default:
        $activePage = 'news';
        break;
}

$pageCSS = '../assets/css/news_details.css';
$pageTitle = htmlspecialchars($row['title']) . ' | NSBM 360';

// Include modular header and navbar
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';

// Fetch Upcoming Event for Sidebar dynamically
$event = null;

try {
    $eventSql = "
    SELECT
        e.*,
        p.title AS post_title,
        p.cover_image
    FROM events e
    INNER JOIN posts p ON e.post_id = p.post_id
    WHERE e.event_date >= CURDATE()
      AND TRIM(LOWER(p.status)) = 'published'
      AND p.post_id != :current_post_id
    ORDER BY e.event_date ASC
    LIMIT 1
";

$eventStmt = $pdo->prepare($eventSql);
$eventStmt->execute([
    'current_post_id' => $post_id
]);

$event = $eventStmt->fetch(PDO::FETCH_ASSOC);
    

} catch (PDOException $e) {
    error_log("Event Query Error: " . $e->getMessage());
    $event = null;
}


 // FETCH ADDITIONAL POST IMAGES

$additionalImages = [];

if ($row && !empty($row['post_id'])) {

    try {

        $imagesStmt = $pdo->prepare(
            "SELECT image_id, image_path, alt_text
             FROM post_images
             WHERE post_id = :post_id
             ORDER BY display_order ASC, image_id ASC"
        );

        $imagesStmt->execute([
            'post_id' => (int) $row['post_id']
        ]);

        $additionalImages = $imagesStmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {

        error_log("Additional Images Query Error: " . $e->getMessage());
        $additionalImages = [];

    }
}


// Safe fallbacks for display fields
$authorName = !empty(trim($row['author_name'] ?? ''))
    ? $row['author_name']
    : 'NSBM Editorial';

$displayDate = !empty($row['published_at'])
    ? $row['published_at']
    : ($row['created_at'] ?? date("Y-m-d H:i:s"));

?>

<div class="page-wrapper">

    <!-- Breadcrumb -->
    <div class="breadcrumb">
        HOME / NEWS /
        <?= htmlspecialchars(strtoupper(substr($row['title'], 0, 25))) ?>...
    </div>

    <div class="content-grid">

        <!-- Left Article Column -->
        <article class="article-main">

            <div class="article-category">
                <?= htmlspecialchars(strtoupper($row['category_name'] ?? 'CAMPUS NEWS')) ?>
            </div>

            <h1 class="article-title">
                <?= nl2br(htmlspecialchars($row['title'])) ?>
            </h1>

            <p class="article-subtitle">
                <?= htmlspecialchars($row['summary'] ?? '') ?>
            </p>

            <!-- Author Info -->
            <div class="author-meta">

                <div class="author-avatar">
                    <?= htmlspecialchars(strtoupper(substr($authorName, 0, 2))) ?>
                </div>

                <div class="author-details">

                    <span class="author-name">
                        <?= htmlspecialchars($authorName) ?> 
                    </span>

                    <span class="post-date">
                        <?= date('d M Y', strtotime($displayDate)) ?>
                    </span>

                </div>

                <div class="share-actions">
                    <button
                        type="button"
                        onclick="navigator.clipboard.writeText(window.location.href).then(() => alert('Link copied to clipboard!')).catch(() => alert('Unable to copy link.'));"
                    >
                        Share ↗️
                    </button>
                </div>

            </div>

            <!-- Cover Image -->
            <div class="featured-image-container">

                <img
                    src="<?= htmlspecialchars(post_image_url($row['cover_image'] ?? null), ENT_QUOTES, 'UTF-8') ?>"
                    alt="<?= htmlspecialchars($row['title'], ENT_QUOTES, 'UTF-8') ?>"
                >

            </div>

            <!-- Additional Post Images -->

            <?php if (!empty($additionalImages)): ?>

                <div class="additional-images-section">

                    <h3 class="additional-images-title">
                        Photo Gallery
                    </h3>

                    <div class="additional-images-grid">

                        <?php foreach ($additionalImages as $image): ?>

                            <div class="additional-image-item">

                                <img
                                    src="<?= htmlspecialchars(
                                        post_image_url($image['image_path']),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>"
                                    alt="<?= htmlspecialchars(
                                        $image['alt_text'] ?: $row['title'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>"
                                    loading="lazy"
                                >

                            </div>

                        <?php endforeach; ?>

                    </div>

                </div>

            <?php endif; ?>

            <!-- Article Body Content -->
            <div class="article-body">
                <?= $row['content'] ?>
            </div>

            <!-- Tags -->
            <div class="tags-row">

                <span class="tag">SUSTAINABILITY</span>
                <span class="tag">CAMPUS LIFE</span>
                <span class="tag">STUDENT VOICES</span>

            </div>

        </article>


        <!-- Right Sidebar Column -->
        <aside class="sidebar">

            <!-- Event Widget Card (Dynamic) -->
            <div class="sidebar-card event-card">

                <?php if ($event): ?>

                    <?php
                        $eventName = htmlspecialchars(
                            $event['post_title'] ?? 'Upcoming Event'
                        );

                        $eventDate = !empty($event['event_date'])
                            ? date('d F Y', strtotime($event['event_date']))
                            : 'TBA';

                        $eventTime = (
                            !empty($event['start_time']) &&
                            !empty($event['end_time'])
                        )
                            ? date('g:i A', strtotime($event['start_time'])) .
                              ' – ' .
                              date('g:i A', strtotime($event['end_time']))
                            : '';

                        $eventLocation = htmlspecialchars(
                            $event['venue'] ?? 'Venue TBA'
                        );

                        $eventImage = post_image_url($event['cover_image'] ?? null);

                        $eventId = (int)($event['event_id'] ?? 1);
                    ?>

                    <div class="event-card-img">

                        <img
                            src="<?= htmlspecialchars($eventImage) ?>"
                            alt="<?= $eventName ?>"
                        >

                    </div>

                    <div class="event-card-content">

                        <span class="be-part-title">
                            Be part of the story
                        </span>

                        <span class="upcoming-badge">
                            UPCOMING EVENT
                        </span>

                        <h3><?= $eventName ?></h3>

                        <p>

                            <strong><?= $eventDate ?></strong>

                            <?php if ($eventTime): ?>
                                <br><?= $eventTime ?>
                            <?php endif; ?>

                            <br><?= $eventLocation ?>

                        </p>

                        <a
                            href="news_details.php?id=<?= (int)$event['post_id'] ?>"
                            class="btn-green"
                        >
                            View event details
                        </a>

                    </div>

                <?php else: ?>

                    <div class="event-card-content">

                        <span class="upcoming-badge">
                            UPCOMING EVENT
                        </span>

                        <h3>No other upcoming events right now</h3>

                        <p>
                            Stay tuned for campus happenings and announcements.
                        </p>

                    </div>

                <?php endif; ?>

            </div>


            <!-- About Widget Card -->
            <div class="sidebar-card about-card">

                <h4>About NSBM 360</h4>

                <p>
                    Your Campus. Your World. Independent student perspectives
                    and official updates, in one place.
                </p>

                <a href="../index.php" >
                    Have a campus story? Get in touch →
                </a>

            </div>

        </aside>

    </div>


    <!-- Keep Exploring Section (Dynamically Loaded from Database) -->

    <?php

    try {

        $exploreSql = "SELECT posts.*,
                              COALESCE(categories.category_name, 'General') AS category_name
                       FROM posts
                       LEFT JOIN categories
                       ON posts.category_id = categories.category_id
                       WHERE posts.post_id != :current_id
                       AND TRIM(LOWER(posts.status)) = 'published'
                       ORDER BY RAND()
                       LIMIT 3";

        $exploreStmt = $pdo->prepare($exploreSql);

        $exploreStmt->execute([
            'current_id' => $post_id
        ]);

        $explorePosts = $exploreStmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {

        error_log("Explore Posts Query Error: " . $e->getMessage());
        $explorePosts = [];

    }

    ?>

    <section class="keep-exploring mb-5">

        <div class="section-header d-flex justify-content-between align-items-center mb-4">

            <h2 class="fw-bold text-dark mb-0">
                Keep exploring
            </h2>

            <a
                href="news.php"
                class="text-success text-decoration-none fw-semibold"
            >
                All news &rarr;
            </a>

        </div>


        <div class="row row-cols-1 row-cols-md-3 g-4">

            <?php if (count($explorePosts) > 0): ?>

                <?php foreach ($explorePosts as $exp): ?>

                    <div class="col">

                        <div class="card h-100 shadow-sm border-0">

                            <img
                                src="<?= htmlspecialchars(post_image_url($exp['cover_image'] ?? null), ENT_QUOTES, 'UTF-8') ?>"
                                class="card-img-top"
                                alt="Cover Image"
                                style="height: 180px; object-fit: cover;"
                            >

                            <div class="card-body d-flex flex-column">

                                <div class="mb-2">

                                    <span class="badge bg-light text-primary px-2 py-1 rounded">
                                        <?= htmlspecialchars($exp['category_name']) ?>
                                    </span>

                                </div>

                                <h5 class="card-title fw-bold text-dark fs-6">
                                    <?= htmlspecialchars($exp['title']) ?>
                                </h5>

                                <p class="card-text text-muted small flex-grow-1">
                                    <?= htmlspecialchars(substr($exp['summary'] ?? '', 0, 80)) ?>...
                                </p>

                                <div class="mt-3 pt-2 border-top">

                                    <p class="text-muted small mb-2">
                                        <?= !empty($exp['created_at'])
                                            ? date('d M Y', strtotime($exp['created_at']))
                                            : 'Date unavailable'
                                        ?>
                                    </p>

                                    <a
                                        href="news_details.php?id=<?= (int)$exp['post_id'] ?>"
                                        class="read-story-link text-success text-decoration-none fw-semibold"
                                    >
                                        Read story &rarr;
                                    </a>

                                </div>

                            </div>

                        </div>

                    </div>

                <?php endforeach; ?>

            <?php else: ?>

                <div class="col-12">

                    <p class="text-muted">
                        No other stories available at the moment.
                    </p>

                </div>

            <?php endif; ?>

        </div>

    </section>

</div>


<?php

// Include footer from the /includes/ folder
include __DIR__ . '/../includes/footer.php';

?>

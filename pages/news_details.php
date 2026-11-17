<?php
// --- NEWS DETAILS PAGE ---

// 1. Connect to database using PDO (steps up from pages/ to root, then into config/)
require_once __DIR__ . '/../config/database.php';
$pageCSS = '../assets/css/news_details.css';


// 2. Get the post ID from the URL safely
$post_id = isset($_GET['id']) ? intval($_GET['id']) : 1;

// 3. Fetch article data using PDO prepared statements
$sql = "SELECT p.*, c.category_name, CONCAT(u.first_name, ' ', u.last_name) AS author_name 
        FROM posts p 
        LEFT JOIN categories c ON p.category_id = c.category_id 
        LEFT JOIN users u ON p.created_by = u.user_id 
        WHERE p.post_id = :post_id AND TRIM(LOWER(p.status)) = 'published'";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['post_id' => $post_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (\PDOException $e) {
    error_log("Article Query Error: " . $e->getMessage());
    $row = false;
}

if (!$row) {
    // Fallback if post is not found or unpublished
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

// 4. Configure Header & CSS paths relative to the pages/ folder
$categoryNameLower = strtolower(trim($row['category_name'] ?? 'news'));

// ඩේටාබේස් එකේ කැටගරි එක මත පදනම්ව Navbar Active Page එක ස්වයංක්‍රීයව වෙනස් වීම
if (strpos($categoryNameLower, 'sport') !== false) {
    $activePage = 'sports';
} elseif (strpos($categoryNameLower, 'academic') !== false) {
    $activePage = 'academics';
} elseif (strpos($categoryNameLower, 'club') !== false) {
    $activePage = 'clubs';
} else {
    $activePage = 'news';
}

$pageCSS = '../assets/css/news_details.css';
$pageTitle = htmlspecialchars($row['title']) . ' | NSBM 360';

// Include modular header and navbar
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';

// Fetch Upcoming Event for Sidebar dynamically
$event = null;
try {
    $eventSql = "SELECT * FROM events ORDER BY event_date DESC LIMIT 1";
    $eventStmt = $pdo->query($eventSql);
    $event = $eventStmt->fetch(PDO::FETCH_ASSOC);
} catch (\PDOException $e) {
    $event = null;
}

// Safe fallbacks for display fields
$authorName = !empty(trim($row['author_name'] ?? '')) ? $row['author_name'] : 'NSBM Editorial';
$displayDate = !empty($row['published_at']) ? $row['published_at'] : ($row['created_at'] ?? date("Y-m-d H:i:s"));
?>

<div class="page-wrapper">
    
    <!-- Breadcrumb -->
    <div class="breadcrumb">HOME / NEWS / <?= htmlspecialchars(strtoupper(substr($row['title'], 0, 25))) ?>...</div>

    <div class="content-grid">
        
        <!-- Left Article Column -->
        <article class="article-main">
            <div class="article-category"><?= htmlspecialchars(strtoupper($row['category_name'] ?? 'CAMPUS NEWS')) ?></div>
            <h1 class="article-title"><?= nl2br(htmlspecialchars($row['title'])) ?></h1>
            <p class="article-subtitle"><?= htmlspecialchars($row['summary'] ?? '') ?></p>

            <!-- Author info -->
            <div class="author-meta">
                <div class="author-avatar"><?= htmlspecialchars(strtoupper(substr($authorName, 0, 2))) ?></div>
                <div class="author-details">
                    <span class="author-name"><?= htmlspecialchars($authorName) ?> · NSBM 360 Editorial</span>
                    <span class="post-date"><?= date('d M Y', strtotime($displayDate)) ?></span>
                </div>
                <div class="share-actions">
                    <button type="button" onclick="navigator.clipboard.writeText(window.location.href); alert('Link copied to clipboard!');">Share ↗️</button>
                </div>
            </div>

            <!-- Cover Image -->
            <div class="featured-image-container">
                <img src="<?= htmlspecialchars(!empty($row['cover_image']) ? $row['cover_image'] : 'https://via.placeholder.com/800x450') ?>" alt="<?= htmlspecialchars($row['title']) ?>">
            </div>

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
                        $eventName = htmlspecialchars($event['event_name'] ?? $event['title'] ?? 'Upcoming Event');
                        $eventDate = !empty($event['event_date']) ? date('d F Y', strtotime($event['event_date'])) : 'TBA';
                        $eventTime = (!empty($event['start_time']) && !empty($event['end_time'])) ? date('g:i A', strtotime($event['start_time'])) . ' – ' . date('g:i A', strtotime($event['end_time'])) : '';
                        $eventLocation = htmlspecialchars($event['location'] ?? 'Green University Grounds');
                        $eventImage = !empty($event['image']) ? $event['image'] : 'https://via.placeholder.com/300x160';
                        $eventId = (int)($event['event_id'] ?? 1);
                    ?>
                    <div class="event-card-img">
                        <img src="<?= htmlspecialchars($eventImage) ?>" alt="<?= $eventName ?>">
                    </div>
                    <div class="event-card-content">
                        <span class="be-part-title">Be part of the story</span>
                        <span class="upcoming-badge">UPCOMING EVENT</span>
                        <h3><?= $eventName ?></h3>
                        <p>
                            <strong><?= $eventDate ?></strong>
                            <?php if ($eventTime): ?><br><?= $eventTime ?><?php endif; ?>
                            <br><?= $eventLocation ?>
                        </p>
                        <a href="event_detail.php?id=<?= $eventId ?>" class="btn-green">View event details</a>
                    </div>
                <?php else: ?>
                    <div class="event-card-content">
                        <span class="upcoming-badge">UPCOMING EVENT</span>
                        <h3>No upcoming events right now</h3>
                        <p>Stay tuned for campus happenings and announcements.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- About Widget Card -->
            <div class="sidebar-card about-card">
                <h4>About NSBM 360</h4>
                <p>Your Campus. Your World. Independent student perspectives and official updates, in one place.</p>
                <a href="#">Have a campus story? Get in touch →</a>
            </div>
        </aside>

    </div>

    <!-- Keep Exploring Section (Dynamically Loaded from Database) -->
    <?php
    try {
        $exploreSql = "SELECT posts.*, COALESCE(categories.category_name, 'General') AS category_name 
                       FROM posts 
                       LEFT JOIN categories ON posts.category_id = categories.category_id 
                       WHERE posts.post_id != :current_id AND TRIM(LOWER(posts.status)) = 'published' 
                       ORDER BY RAND() LIMIT 3";
        $exploreStmt = $pdo->prepare($exploreSql);
        $exploreStmt->execute(['current_id' => $post_id]);
        $explorePosts = $exploreStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (\PDOException $e) {
        $explorePosts = [];
    }
    ?>

    <section class="keep-exploring mb-5">
        <div class="section-header d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold text-dark mb-0">Keep exploring</h2>
            <a href="news.php" class="text-success text-decoration-none fw-semibold">All news &rarr;</a>
        </div>
        
        <div class="row row-cols-1 row-cols-md-3 g-4">
            <?php if (count($explorePosts) > 0): ?>
                <?php foreach ($explorePosts as $exp): ?>
                    <div class="col">
                        <div class="card h-100 shadow-sm border-0">
                            <img src="<?= htmlspecialchars($exp['cover_image'] ?? 'https://via.placeholder.com/350x200') ?>" class="card-img-top" alt="Cover Image" style="height: 180px; object-fit: cover;">
                            <div class="card-body d-flex flex-column">
                                <div class="mb-2">
                                    <span class="badge bg-light text-primary px-2 py-1 rounded"><?= htmlspecialchars($exp['category_name']) ?></span>
                                </div>
                                <h5 class="card-title fw-bold text-dark fs-6"><?= htmlspecialchars($exp['title']) ?></h5>
                                <p class="card-text text-muted small flex-grow-1"><?= htmlspecialchars(substr($exp['summary'] ?? '', 0, 80)) ?>...</p>
                                <div class="mt-3 pt-2 border-top">
                                    <p class="text-muted small mb-2"><?= date('d M Y', strtotime($exp['created_at'])) ?></p>
                                    <a href="news_details.php?id=<?= $exp['post_id'] ?>" class="read-story-link text-success text-decoration-none fw-semibold">Read story &rarr;</a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <p class="text-muted">No other stories available at the moment.</p>
                </div>
            <?php endif; ?>
        </div>
    </section>

</div>

<?php 
// 5. Include footer from the /includes/ folder
include __DIR__ . '/../includes/footer.php'; 
?>
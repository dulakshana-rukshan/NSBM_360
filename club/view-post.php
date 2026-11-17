
<?php

require_once __DIR__ . '/president_auth.php';
require_once __DIR__ . '/president_functions.php';

$pageTitle = 'View Post | NEWS 360';
$pageCSS = '/NSBM_360/club/president.css';
$isPresidentPage = true;


// ------------------------------------
// 1. GET POST ID
// ------------------------------------

$postId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$postId || $postId <= 0) {
    http_response_code(404);
    exit('Invalid post ID.');
}


// ------------------------------------
// 2. LOAD POST DETAILS
// ------------------------------------

$query = "
    SELECT
        p.post_id,
        p.title,
        p.summary,
        p.content,
        p.cover_image,
        p.status,
        p.created_at,
        p.updated_at,
        p.published_at,

        c.category_name,
        sc.subcategory_name,

        s.sport_name,

        e.event_id,
        e.event_date,
        e.start_time,
        e.end_time,
        e.start_at,
        e.end_at,
        e.venue,
        e.registration_deadline,
        e.registration_link

    FROM posts p

    LEFT JOIN categories c
        ON p.category_id = c.category_id

    LEFT JOIN subcategories sc
        ON p.subcategory_id = sc.subcategory_id
       AND p.category_id = sc.category_id

    LEFT JOIN sports s
        ON p.sport_id = s.sport_id

    LEFT JOIN events e
        ON p.post_id = e.post_id

    WHERE p.post_id = :post_id
      AND p.club_id = :club_id

    LIMIT 1
";

$stmt = $pdo->prepare($query);

$stmt->execute([
    'post_id' => $postId,
    'club_id' => $clubId
]);

$post = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$post) {
    http_response_code(404);
    exit('Post not found.');
}


// ------------------------------------
// 3. LOAD ADDITIONAL IMAGES
// ------------------------------------

$imageStmt = $pdo->prepare("
    SELECT image_id, image_path, alt_text, display_order
    FROM post_images
    WHERE post_id = :post_id
    ORDER BY display_order ASC, image_id ASC
");

$imageStmt->execute([
    'post_id' => $postId
]);

$additionalImages = $imageStmt->fetchAll(PDO::FETCH_ASSOC);


// ------------------------------------
// 4. LOAD RELEVANT FACULTIES
// ------------------------------------

$facultyStmt = $pdo->prepare("
    SELECT f.faculty_name
    FROM post_faculties pf
    INNER JOIN faculties f
        ON pf.faculty_id = f.faculty_id
    WHERE pf.post_id = :post_id
    ORDER BY f.faculty_name
");

$facultyStmt->execute([
    'post_id' => $postId
]);

$postFaculties = $facultyStmt->fetchAll(PDO::FETCH_COLUMN);


// ------------------------------------
// 5. IMAGE URLS
// ------------------------------------

$coverImageUrl = '';

if (!empty($post['cover_image'])) {
    $coverImageUrl = '/NSBM_360/' . ltrim($post['cover_image'], '/');
}

foreach ($additionalImages as &$image) {
    $image['image_url'] = '/NSBM_360/' . ltrim(
        $image['image_path'],
        '/'
    );
}

unset($image);


// ------------------------------------
// 6. EVENT DATE FORMATTING
// ------------------------------------

$eventStart = $post['start_at'];

$eventEnd = $post['end_at'];

// Fallback to legacy event columns.
if (empty($eventStart) && !empty($post['event_date'])) {
    $eventStart = $post['event_date'] . ' ' . $post['start_time'];
}

if (empty($eventEnd) && !empty($post['event_date'])) {
    $eventEnd = $post['event_date'] . ' ' . $post['end_time'];
}

$formatDateTime = function ($value) {

    if (!$value) {
        return '-';
    }

    $timestamp = strtotime($value);

    return $timestamp !== false
        ? date('d M Y, h:i A', $timestamp)
        : '-';
};


// Validate registration URL before displaying it.
$registrationUrl = '';

if (!empty($post['registration_link'])) {

    $parsedUrl = parse_url($post['registration_link']);

    if (
        is_array($parsedUrl) &&
        isset($parsedUrl['scheme']) &&
        in_array(
            strtolower($parsedUrl['scheme']),
            ['http', 'https'],
            true
        )
    ) {
        $registrationUrl = $post['registration_link'];
    }
}


// ------------------------------------
// 7. STATUS CLASS
// ------------------------------------

$statusClass = post_status_class($post['status']);


require_once __DIR__ . '/president_header.php';

?>

<style>

/* =========================================
   VIEW POST PAGE
========================================= */

.event-view-post-page {
    width: 100%;
    max-width: 1200px;
    margin: 0 auto;
    padding-bottom: 35px;
}


/* =========================================
   PAGE HEADER
========================================= */

.event-view-post-page .page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    flex-wrap: wrap;
    margin-bottom: 25px;
}

.event-view-post-page .page-header h1 {
    color: #002147;
    font-size: 28px;
    font-weight: 750;
    margin: 5px 0 7px;
}

.event-view-post-page .page-header p {
    color: #6B7280;
    margin: 0;
    font-size: 14px;
}

.event-view-post-page .eyebrow {
    color: #198754 !important;
    font-size: 11px !important;
    font-weight: 750;
    letter-spacing: 1.3px;
}

.event-view-post-page .page-header-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}


/* =========================================
   MAIN CARD
========================================= */

.event-view-post-page .view-post-card {
    background: #FFFFFF;
    border: 1px solid #E5E7EB;
    border-radius: 16px;
    padding: 30px;
    box-shadow: 0 4px 18px rgba(0, 33, 71, 0.045);
}


/* =========================================
   POST HERO
========================================= */

.event-view-post-page .post-hero {
    padding-bottom: 25px;
    border-bottom: 1px solid #E5E7EB;
    margin-bottom: 25px;
}

.event-view-post-page .post-labels {
    display: flex;
    align-items: center;
    gap: 9px;
    flex-wrap: wrap;
    margin-bottom: 15px;
}

.event-view-post-page .category-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #EAF8EA;
    color: #198754;
    padding: 7px 12px;
    border-radius: 7px;
    font-size: 12px;
    font-weight: 700;
}

.event-view-post-page .post-hero h2 {
    color: #002147;
    font-size: 27px;
    font-weight: 750;
    line-height: 1.4;
    margin: 0 0 10px;
    overflow-wrap: anywhere;
}

.event-view-post-page .post-category {
    display: flex;
    align-items: center;
    gap: 7px;
    flex-wrap: wrap;
    color: #6B7280;
    font-size: 13px;
    margin: 0;
}


/* =========================================
   COVER IMAGE
========================================= */

.event-view-post-page .post-cover-wrapper {
    width: 100%;
    margin-bottom: 28px;
    overflow: hidden;
    border-radius: 13px;
    background: #F5F7FA;
}

.event-view-post-page .post-cover-image {
    width: 100%;
    max-height: 440px;
    display: block;
    object-fit: cover;
}


/* =========================================
   CONTENT SECTIONS
========================================= */

.event-view-post-page .post-section {
    margin-bottom: 25px;
    padding: 23px;
    background: #FFFFFF;
    border: 1px solid #E5E7EB;
    border-radius: 12px;
}

.event-view-post-page .post-section h3,
.event-view-post-page .post-information h3 {
    display: flex;
    align-items: center;
    gap: 9px;
    color: #002147;
    font-size: 16px;
    font-weight: 750;
    margin: 0 0 17px;
}

.event-view-post-page .post-section h3 i,
.event-view-post-page .post-information h3 i {
    color: #198754;
    font-size: 17px;
}

.event-view-post-page .post-section p,
.event-view-post-page .post-content {
    color: #4B5563;
    font-size: 14px;
    line-height: 1.9;
    margin: 0;
    overflow-wrap: anywhere;
    white-space: normal;
}

.event-view-post-page .post-content {
    white-space: pre-wrap;
}


/* =========================================
   ADDITIONAL IMAGE GALLERY
========================================= */

.event-view-post-page .post-image-gallery {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 15px;
}

.event-view-post-page .post-gallery-item {
    display: block;
    height: 165px;
    overflow: hidden;
    border-radius: 10px;
    background: #F5F7FA;
    border: 1px solid #E5E7EB;
}

.event-view-post-page .post-gallery-item img {
    width: 100%;
    height: 100%;
    display: block;
    object-fit: cover;
    transition: transform 0.25s ease;
}

.event-view-post-page .post-gallery-item:hover img {
    transform: scale(1.05);
}


/* =========================================
   INFORMATION GRID
========================================= */

.event-view-post-page .post-info-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 13px;
}

.event-view-post-page .info-item {
    background: #F8FAFC;
    border: 1px solid #E5E7EB;
    border-radius: 9px;
    padding: 15px;
    min-width: 0;
}

.event-view-post-page .info-item span {
    display: block;
    color: #6B7280;
    font-size: 12px;
    font-weight: 600;
    margin-bottom: 7px;
}

.event-view-post-page .info-item strong {
    display: block;
    color: #1F2937;
    font-size: 13px;
    font-weight: 700;
    line-height: 1.6;
    overflow-wrap: anywhere;
}

.event-view-post-page .info-item a {
    color: #198754;
    text-decoration: none;
}

.event-view-post-page .info-item a:hover {
    text-decoration: underline;
}


/* =========================================
   FACULTY TAGS
========================================= */

.event-view-post-page .faculty-list {
    display: flex;
    flex-wrap: wrap;
    gap: 9px;
}

.event-view-post-page .faculty-tag {
    display: inline-flex;
    align-items: center;
    background: #EAF8EA;
    color: #198754;
    border: 1px solid #D4EED4;
    padding: 8px 13px;
    border-radius: 7px;
    font-size: 12px;
    font-weight: 650;
}


/* =========================================
   POST INFORMATION
========================================= */

.event-view-post-page .post-information {
    margin-top: 28px;
    padding: 23px;
    background: #F8FAFC;
    border: 1px solid #E5E7EB;
    border-radius: 12px;
}


/* =========================================
   BOTTOM ACTIONS
========================================= */

.event-view-post-page .post-bottom-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    padding-top: 23px;
    margin-top: 25px;
    border-top: 1px solid #E5E7EB;
}

.event-view-post-page .post-action-right {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.event-view-post-page .post-bottom-actions form {
    margin: 0;
}


/* =========================================
   BUTTONS
========================================= */

.event-view-post-page .button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 40px;
    padding: 9px 16px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 650;
    text-decoration: none;
    cursor: pointer;
    transition: 0.2s ease;
}

.event-view-post-page .button-primary {
    background: #198754;
    color: #FFFFFF;
    border: 1px solid #198754;
}

.event-view-post-page .button-primary:hover {
    background: #146C43;
    border-color: #146C43;
    color: #FFFFFF;
}

.event-view-post-page .button-clear {
    background: #FFFFFF;
    color: #374151;
    border: 1px solid #D1D5DB;
}

.event-view-post-page .button-clear:hover {
    background: #F3F4F6;
    color: #002147;
}

.event-view-post-page .button-danger {
    background: #FFFFFF;
    color: #DC3545;
    border: 1px solid #DC3545;
}

.event-view-post-page .button-danger:hover {
    background: #DC3545;
    color: #FFFFFF;
}


/* =========================================
   RESPONSIVE DESIGN
========================================= */

@media (max-width: 768px) {

    .event-view-post-page .view-post-card {
        padding: 20px;
    }

    .event-view-post-page .post-hero h2 {
        font-size: 22px;
    }

    .event-view-post-page .post-info-grid {
        grid-template-columns: 1fr;
    }

    .event-view-post-page .post-section,
    .event-view-post-page .post-information {
        padding: 18px;
    }

    .event-view-post-page .page-header {
        align-items: flex-start;
    }

    .event-view-post-page .page-header-actions {
        width: 100%;
    }

    .event-view-post-page .post-bottom-actions {
        align-items: stretch;
        flex-direction: column;
    }

    .event-view-post-page .post-action-right {
        width: 100%;
    }

    .event-view-post-page .post-action-right > * {
        flex: 1;
    }

    .event-view-post-page .post-bottom-actions > a {
        width: 100%;
    }

}

@media (max-width: 480px) {

    .event-view-post-page .view-post-card {
        padding: 14px;
    }

    .event-view-post-page .post-hero h2 {
        font-size: 20px;
    }

    .event-view-post-page .post-cover-image {
        max-height: 250px;
    }

    .event-view-post-page .post-image-gallery {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 9px;
    }

    .event-view-post-page .post-gallery-item {
        height: 125px;
    }

    .event-view-post-page .button {
        font-size: 12px;
        padding: 9px 11px;
    }

}

</style>


<div class="page-shell">

    <?php require_once __DIR__ . '/president_sidebar.php'; ?>

    <main class="main-content">

        <div class="event-view-post-page">

            <!-- PAGE HEADER -->

            <div class="page-header">

                <div>
                    <p class="eyebrow">PRESIDENT WORKSPACE</p>
                    <h1>View Post</h1>
                    <p>View and manage the complete details of your club post.</p>
                </div>

                <div class="page-header-actions">

                    <a href="manage-posts.php" class="button button-clear">
                        <i class="bi bi-arrow-left"></i>
                        Back to Posts
                    </a>

                    <a
                        href="edit-post.php?id=<?= (int) $post['post_id'] ?>"
                        class="button button-primary"
                    >
                        <i class="bi bi-pencil-square"></i>
                        Edit Post
                    </a>

                </div>

            </div>


            <!-- MAIN POST CARD -->

            <section class="content-card view-post-card">


                <!-- POST HERO -->

                <div class="post-hero">

                    <div class="post-labels">

                        <span class="status-badge <?= e($statusClass) ?>">
                            <?= e(ucfirst($post['status'])) ?>
                        </span>

                        <span class="category-badge">
                            <i class="bi bi-folder"></i>
                            <?= e($post['category_name'] ?? 'Uncategorized') ?>
                        </span>

                    </div>

                    <h2><?= e($post['title']) ?></h2>

                    <p class="post-category">

                        <i class="bi bi-folder2-open"></i>

                        <?= e($post['category_name'] ?? 'Uncategorized') ?>

                        <?php if (!empty($post['subcategory_name'])): ?>

                            <span>/</span>

                            <?= e($post['subcategory_name']) ?>

                        <?php endif; ?>

                    </p>

                </div>


                <!-- COVER IMAGE -->

                <?php if ($coverImageUrl !== ''): ?>

                    <div class="post-cover-wrapper">

                        <img
                            src="<?= e($coverImageUrl) ?>"
                            alt="<?= e($post['title']) ?>"
                            class="post-cover-image"
                        >

                    </div>

                <?php endif; ?>


                <!-- SUMMARY -->

                <?php if (!empty($post['summary'])): ?>

                    <section class="post-section">

                        <h3>
                            <i class="bi bi-card-text"></i>
                            Summary
                        </h3>

                        <p><?= nl2br(e($post['summary'])) ?></p>

                    </section>

                <?php endif; ?>


                <!-- POST CONTENT -->

                <section class="post-section">

                    <h3>
                        <i class="bi bi-file-text"></i>
                        Post Content
                    </h3>

                    <div class="post-content">
                        <?= e($post['content']) ?>
                    </div>

                </section>


                <!-- ADDITIONAL IMAGES -->

                <?php if (!empty($additionalImages)): ?>

                    <section class="post-section">

                        <h3>
                            <i class="bi bi-images"></i>
                            Additional Images
                        </h3>

                        <div class="post-image-gallery">

                            <?php foreach ($additionalImages as $image): ?>

                                <a
                                    href="<?= e($image['image_url']) ?>"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="post-gallery-item"
                                >

                                    <img
                                        src="<?= e($image['image_url']) ?>"
                                        alt="<?= e($image['alt_text'] ?: $post['title']) ?>"
                                        loading="lazy"
                                    >

                                </a>

                            <?php endforeach; ?>

                        </div>

                    </section>

                <?php endif; ?>


                <!-- SPORT INFORMATION -->

                <?php if (!empty($post['sport_name'])): ?>

                    <section class="post-section">

                        <h3>
                            <i class="bi bi-trophy"></i>
                            Sport Information
                        </h3>

                        <div class="post-info-grid">

                            <div class="info-item">

                                <span>Sport</span>

                                <strong>
                                    <?= e($post['sport_name']) ?>
                                </strong>

                            </div>

                        </div>

                    </section>

                <?php endif; ?>


                <!-- EVENT INFORMATION -->

                <?php if (strtolower($post['category_name'] ?? '') === 'events'): ?>

                    <section class="post-section">

                        <h3>
                            <i class="bi bi-calendar-event"></i>
                            Event Information
                        </h3>

                        <?php if (!empty($post['event_id'])): ?>

                            <div class="post-info-grid">

                                <div class="info-item">

                                    <span>Start Date & Time</span>

                                    <strong>
                                        <?= e($formatDateTime($eventStart)) ?>
                                    </strong>

                                </div>

                                <div class="info-item">

                                    <span>End Date & Time</span>

                                    <strong>
                                        <?= e($formatDateTime($eventEnd)) ?>
                                    </strong>

                                </div>

                                <div class="info-item">

                                    <span>Venue</span>

                                    <strong>
                                        <?= e($post['venue'] ?: '-') ?>
                                    </strong>

                                </div>

                                <div class="info-item">

                                    <span>Registration Deadline</span>

                                    <strong>
                                        <?= !empty($post['registration_deadline'])
                                            ? e($formatDateTime($post['registration_deadline']))
                                            : 'Not specified' ?>
                                    </strong>

                                </div>

                                <div class="info-item">

                                    <span>Registration Link</span>

                                    <strong>

                                        <?php if ($registrationUrl !== ''): ?>

                                            <a
                                                href="<?= e($registrationUrl) ?>"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                            >
                                                Register Here
                                                <i class="bi bi-box-arrow-up-right"></i>
                                            </a>

                                        <?php else: ?>

                                            Not available

                                        <?php endif; ?>

                                    </strong>

                                </div>

                            </div>

                        <?php else: ?>

                            <p class="post-content">
                                Event details have not been added for this post.
                            </p>

                        <?php endif; ?>

                    </section>

                <?php endif; ?>


                <!-- RELEVANT FACULTIES -->

                <?php if (!empty($postFaculties)): ?>

                    <section class="post-section">

                        <h3>
                            <i class="bi bi-building"></i>
                            Relevant Faculties
                        </h3>

                        <div class="faculty-list">

                            <?php foreach ($postFaculties as $facultyName): ?>

                                <span class="faculty-tag">
                                    <?= e($facultyName) ?>
                                </span>

                            <?php endforeach; ?>

                        </div>

                    </section>

                <?php endif; ?>


                <!-- POST INFORMATION -->

                <section class="post-information">

                    <h3>
                        <i class="bi bi-info-circle"></i>
                        Post Information
                    </h3>

                    <div class="post-info-grid">

                        <div class="info-item">

                            <span>Post ID</span>

                            <strong>
                                #<?= (int) $post['post_id'] ?>
                            </strong>

                        </div>

                        <div class="info-item">

                            <span>Status</span>

                            <strong>
                                <?= e(ucfirst($post['status'])) ?>
                            </strong>

                        </div>

                        <div class="info-item">

                            <span>Category</span>

                            <strong>
                                <?= e($post['category_name'] ?? '-') ?>
                            </strong>

                        </div>

                        <div class="info-item">

                            <span>Subcategory</span>

                            <strong>
                                <?= e($post['subcategory_name'] ?? '-') ?>
                            </strong>

                        </div>

                        <div class="info-item">

                            <span>Date Created</span>

                            <strong>
                                <?= e(display_date($post['created_at'])) ?>
                            </strong>

                        </div>

                        <div class="info-item">

                            <span>Last Updated</span>

                            <strong>
                                <?= e(display_date($post['updated_at'])) ?>
                            </strong>

                        </div>

                        <div class="info-item">

                            <span>Published Date</span>

                            <strong>
                                <?= !empty($post['published_at'])
                                    ? e(display_date($post['published_at']))
                                    : 'Not published yet' ?>
                            </strong>

                        </div>

                    </div>

                </section>


                <!-- BOTTOM ACTIONS -->

                <div class="post-bottom-actions">

                    <a href="manage-posts.php" class="button button-clear">

                        <i class="bi bi-arrow-left"></i>
                        Back to Posts

                    </a>


                    <div class="post-action-right">

                        <a
                            href="edit-post.php?id=<?= (int) $post['post_id'] ?>"
                            class="button button-primary"
                        >
                            <i class="bi bi-pencil-square"></i>
                            Edit Post
                        </a>


                        <form
                            method="POST"
                            action="delete-post.php"
                        >

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= e(csrf_token()) ?>"
                            >

                            <input
                                type="hidden"
                                name="post_id"
                                value="<?= (int) $post['post_id'] ?>"
                            >

                            <button
                                type="submit"
                                class="button button-danger"
                            >
                                <i class="bi bi-trash"></i>
                                Delete Post
                            </button>

                        </form>

                    </div>

                </div>

            </section>

        </div>

    </main>

</div>


<script src="/NSBM_360/assets/js/president-view-post.js" defer></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

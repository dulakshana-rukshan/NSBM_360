
<?php

// --------------------------------------------------
// 1. PRESIDENT AUTHENTICATION
// --------------------------------------------------

require_once __DIR__ . '/president_auth.php';
require_once __DIR__ . '/president_functions.php';

$pageTitle = 'View Event | NEWS 360';
$pageCSS = '/NSBM_360/club/president.css';
$isPresidentPage = true;
$isEventArea = true;


// --------------------------------------------------
// 2. GET EVENT ID
// --------------------------------------------------

$eventId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$eventId || $eventId <= 0) {
    header('Location: dashboard.php');
    exit;
}


// --------------------------------------------------
// 3. GET EVENT DETAILS
// --------------------------------------------------

$eventSql = "
    SELECT
        e.event_id,
        e.event_date,
        e.start_time,
        e.end_time,
        e.start_at,
        e.end_at,
        e.venue,
        e.registration_deadline,
        e.registration_link,

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
        sc.subcategory_name

    FROM events e

    INNER JOIN posts p
        ON e.post_id = p.post_id

    LEFT JOIN categories c
        ON p.category_id = c.category_id

    LEFT JOIN subcategories sc
        ON p.subcategory_id = sc.subcategory_id
       AND p.category_id = sc.category_id

    WHERE e.event_id = :event_id
      AND p.club_id = :club_id

    LIMIT 1
";

$eventStmt = $pdo->prepare($eventSql);

$eventStmt->execute([
    'event_id' => $eventId,
    'club_id' => $clubId
]);

$event = $eventStmt->fetch(PDO::FETCH_ASSOC);


// --------------------------------------------------
// 4. CHECK EVENT
// --------------------------------------------------

if (!$event) {
    $_SESSION['error_message'] =
        'Event not found or you do not have permission to view it.';

    header('Location: dashboard.php');
    exit;
}


// --------------------------------------------------
// 5. GET ADDITIONAL EVENT IMAGES
// --------------------------------------------------

$imageSql = "
    SELECT
        image_id,
        image_path,
        alt_text,
        display_order
    FROM post_images
    WHERE post_id = :post_id
    ORDER BY display_order ASC, image_id ASC
";

$imageStmt = $pdo->prepare($imageSql);

$imageStmt->execute([
    'post_id' => $event['post_id']
]);

$additionalImages = $imageStmt->fetchAll(PDO::FETCH_ASSOC);


// --------------------------------------------------
// 6. EVENT DATETIME PROCESSING
// --------------------------------------------------

$eventStart = $event['start_at'] ?? null;
$eventEnd = $event['end_at'] ?? null;

if (empty($eventStart) && !empty($event['event_date'])) {
    $eventStart = $event['event_date'] . ' ' . $event['start_time'];
}

if (empty($eventEnd) && !empty($event['event_date'])) {
    $eventEnd = $event['event_date'] . ' ' . $event['end_time'];
}

$startTimestamp = !empty($eventStart)
    ? strtotime($eventStart)
    : false;

$endTimestamp = !empty($eventEnd)
    ? strtotime($eventEnd)
    : false;


// --------------------------------------------------
// 7. EVENT DATE AND DURATION
// --------------------------------------------------

$eventDateRange = 'Not specified';
$eventDuration = 'Not specified';

$startDateFormatted = 'Not specified';
$endDateFormatted = 'Not specified';

$startTimeFormatted = 'Not specified';
$endTimeFormatted = 'Not specified';

if ($startTimestamp !== false) {

    $startDateFormatted = date('F j, Y', $startTimestamp);
    $startTimeFormatted = date('h:i A', $startTimestamp);

    $eventDateRange = $startDateFormatted;
}

if ($endTimestamp !== false) {

    $endDateFormatted = date('F j, Y', $endTimestamp);
    $endTimeFormatted = date('h:i A', $endTimestamp);

    if ($startTimestamp !== false) {

        if (
            date('Y-m-d', $startTimestamp) !==
            date('Y-m-d', $endTimestamp)
        ) {
            $eventDateRange .= ' - ' . $endDateFormatted;
        }

        $startDay = new DateTime(
            date('Y-m-d', $startTimestamp)
        );

        $endDay = new DateTime(
            date('Y-m-d', $endTimestamp)
        );

        if ($endDay >= $startDay) {

            $durationDays =
                (int) $startDay->diff($endDay)->days + 1;

            $eventDuration = $durationDays . (
                $durationDays === 1 ? ' Day' : ' Days'
            );
        }
    }
}


// --------------------------------------------------
// 8. REGISTRATION DEADLINE
// --------------------------------------------------

$deadlineFormatted = 'Not specified';

if (!empty($event['registration_deadline'])) {

    $deadlineTimestamp = strtotime(
        $event['registration_deadline']
    );

    if ($deadlineTimestamp !== false) {

        $deadlineFormatted = date(
            'F j, Y - h:i A',
            $deadlineTimestamp
        );
    }
}


// --------------------------------------------------
// 9. COVER IMAGE URL
// --------------------------------------------------

$coverImageUrl = '';

if (!empty($event['cover_image'])) {

    $storedCoverPath = ltrim(
        $event['cover_image'],
        '/'
    );

    if (strpos($storedCoverPath, 'NSBM_360/') === 0) {
        $coverImageUrl = '/' . $storedCoverPath;
    } else {
        $coverImageUrl = '/NSBM_360/' . $storedCoverPath;
    }
}


// --------------------------------------------------
// 10. ADDITIONAL IMAGE URLS
// --------------------------------------------------

foreach ($additionalImages as &$image) {

    $storedImagePath = ltrim(
        $image['image_path'],
        '/'
    );

    if (strpos($storedImagePath, 'NSBM_360/') === 0) {
        $image['image_url'] = '/' . $storedImagePath;
    } else {
        $image['image_url'] = '/NSBM_360/' . $storedImagePath;
    }
}

unset($image);


// --------------------------------------------------
// 11. INCLUDE HEADER
// --------------------------------------------------

require_once __DIR__ . '/president_header.php';

?>


<div class="page-shell">

    <?php require __DIR__ . '/president_sidebar.php'; ?>

    <main class="main-content">

        <div class="event-view-page">

            <!-- PAGE HEADER -->

            <div class="page-header">

                <div>
                    <p class="eyebrow">PRESIDENT WORKSPACE</p>
                    <h1>Event Details</h1>

                    <p class="page-subtitle">
                        Complete information about your club event.
                    </p>
                </div>

                <a href="dashboard.php" class="button button-secondary">
                    <i class="bi bi-arrow-left"></i>
                    Back to Dashboard
                </a>

            </div>


            <!-- EVENT HERO -->

            <section class="event-hero-card">

                <?php if ($coverImageUrl !== ''): ?>

                    <img
                        src="<?= e($coverImageUrl) ?>"
                        alt="<?= e($event['title']) ?>"
                        class="event-hero-image"
                    >

                <?php endif; ?>


                <div class="event-hero-content">

                    <div class="event-breadcrumb">
                        <i class="bi bi-house"></i>
                        President Workspace

                        <i class="bi bi-chevron-right"></i>

                        Events

                        <i class="bi bi-chevron-right"></i>

                        Event Details
                    </div>


                    <div class="event-title-row">

                        <div>

                            <h2 class="event-main-title">
                                <?= e($event['title']) ?>
                            </h2>

                            <p class="event-category-text">
                                <i class="bi bi-folder2"></i>

                                <?= e($event['category_name'] ?? 'Events') ?>

                                <?php if (!empty($event['subcategory_name'])): ?>
                                    / <?= e($event['subcategory_name']) ?>
                                <?php endif; ?>

                            </p>

                        </div>

                        <span class="<?= e(post_status_class($event['status'])) ?>">
                            <?= e(ucfirst($event['status'])) ?>
                        </span>

                    </div>

                </div>

            </section>


            <!-- 01 EVENT OVERVIEW -->

            <section class="event-section-card">

                <div class="event-section-heading">

                    <div class="event-heading-icon">
                        <i class="bi bi-info-circle"></i>
                    </div>

                    <div>
                        <h2>Event Overview</h2>
                        <p>Introduction and description of the event.</p>
                    </div>

                </div>


                <?php if (!empty($event['summary'])): ?>

                    <div class="event-summary-block">

                        <h3>Short Caption</h3>

                        <div class="event-description">
                            <?= nl2br(e($event['summary'])) ?>
                        </div>

                    </div>

                <?php endif; ?>


                <h3 class="event-description-title">
                    Event Description
                </h3>

                <div class="event-description">
                    <?= nl2br(e($event['content'])) ?>
                </div>

            </section>


            <!-- 02 ADDITIONAL EVENT IMAGES -->

            <?php if (!empty($additionalImages)): ?>

                <section class="event-section-card">

                    <div class="event-section-heading">

                        <div class="event-heading-icon">
                            <i class="bi bi-images"></i>
                        </div>

                        <div>
                            <h2>Additional Images</h2>
                            <p>More photos related to this event.</p>
                        </div>

                    </div>


                    <div class="event-image-gallery">

                        <?php foreach ($additionalImages as $image): ?>

                            <div class="event-gallery-item">

                                <img
                                    src="<?= e($image['image_url']) ?>"
                                    alt="<?= e($image['alt_text'] ?: 'Event Image') ?>"
                                    loading="lazy"
                                >

                            </div>

                        <?php endforeach; ?>

                    </div>

                </section>

            <?php endif; ?>


            <!-- 03 EVENT SCHEDULE -->

            <section class="event-section-card">

                <div class="event-section-heading">

                    <div class="event-heading-icon">
                        <i class="bi bi-calendar-event"></i>
                    </div>

                    <div>
                        <h2>Event Schedule</h2>
                        <p>Important dates, duration and event timings.</p>
                    </div>

                </div>


                <div class="event-schedule-grid">

                    <div class="event-detail-box primary-detail">

                        <div class="event-detail-label">
                            <i class="bi bi-calendar-range"></i>
                            Event Date
                        </div>

                        <p class="event-detail-value">
                            <?= e($eventDateRange) ?>
                        </p>

                    </div>


                    <div class="event-detail-box primary-detail">

                        <div class="event-detail-label">
                            <i class="bi bi-calendar2-check"></i>
                            Event Duration
                        </div>

                        <p class="event-detail-value">
                            <?= e($eventDuration) ?>
                        </p>

                    </div>


                    <div class="event-detail-box">

                        <div class="event-detail-label">
                            <i class="bi bi-play-circle"></i>
                            Start Date & Time
                        </div>

                        <p class="event-detail-value">
                            <?= e(
                                $startTimestamp !== false
                                    ? date('F j, Y - h:i A', $startTimestamp)
                                    : 'Not specified'
                            ) ?>
                        </p>

                    </div>


                    <div class="event-detail-box">

                        <div class="event-detail-label">
                            <i class="bi bi-stop-circle"></i>
                            End Date & Time
                        </div>

                        <p class="event-detail-value">
                            <?= e(
                                $endTimestamp !== false
                                    ? date('F j, Y - h:i A', $endTimestamp)
                                    : 'Not specified'
                            ) ?>
                        </p>

                    </div>

                </div>

            </section>


            <!-- 04 EVENT LOCATION -->

            <section class="event-section-card">

                <div class="event-section-heading">

                    <div class="event-heading-icon">
                        <i class="bi bi-geo-alt"></i>
                    </div>

                    <div>
                        <h2>Event Location</h2>
                        <p>Where the event will take place.</p>
                    </div>

                </div>


                <div class="event-venue-box">

                    <div class="event-venue-icon">
                        <i class="bi bi-geo-alt-fill"></i>
                    </div>

                    <div>

                        <div class="event-venue-label">
                            Venue
                        </div>

                        <p class="event-venue-name">
                            <?= e($event['venue'] ?: 'Not specified') ?>
                        </p>

                    </div>

                </div>

            </section>


            <!-- 05 REGISTRATION INFORMATION -->

            <section class="event-section-card">

                <div class="event-section-heading">

                    <div class="event-heading-icon">
                        <i class="bi bi-card-checklist"></i>
                    </div>

                    <div>
                        <h2>Registration Information</h2>
                        <p>Registration deadline and participant access.</p>
                    </div>

                </div>


                <div class="event-registration-list">

                    <div class="event-registration-item">

                        <i class="bi bi-calendar-x"></i>

                        <div>

                            <div class="event-registration-label">
                                Registration Deadline
                            </div>

                            <div class="event-registration-value">
                                <?= e($deadlineFormatted) ?>
                            </div>

                        </div>

                    </div>


                    <div class="event-registration-item">

                        <i class="bi bi-link-45deg"></i>

                        <div>

                            <div class="event-registration-label">
                                Registration Link
                            </div>

                            <div class="event-registration-value">

                                <?php if (!empty($event['registration_link'])): ?>

                                    <a
                                        href="<?= e($event['registration_link']) ?>"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        Open Registration Link
                                        <i class="bi bi-box-arrow-up-right"></i>
                                    </a>

                                <?php else: ?>

                                    Not available

                                <?php endif; ?>

                            </div>

                        </div>

                    </div>

                </div>

            </section>


            <!-- 06 POST INFORMATION -->

            <section class="event-section-card">

                <div class="event-section-heading">

                    <div class="event-heading-icon">
                        <i class="bi bi-file-earmark-text"></i>
                    </div>

                    <div>
                        <h2>Post Information</h2>
                        <p>Publishing and record information.</p>
                    </div>

                </div>


                <div class="event-post-info-grid">

                    <div class="event-post-info-item">
                        <span>Post ID</span>
                        <strong>
                            #<?= (int) $event['post_id'] ?>
                        </strong>
                    </div>


                    <div class="event-post-info-item">
                        <span>Created Date</span>
                        <strong>
                            <?= e(display_date($event['created_at'])) ?>
                        </strong>
                    </div>


                    <div class="event-post-info-item">
                        <span>Last Updated</span>
                        <strong>
                            <?= e(display_date($event['updated_at'])) ?>
                        </strong>
                    </div>


                    <div class="event-post-info-item">
                        <span>Published Date</span>
                        <strong>
                            <?= !empty($event['published_at'])
                                ? e(display_date($event['published_at']))
                                : 'Not published yet' ?>
                        </strong>
                    </div>


                    <div class="event-post-info-item">
                        <span>Category</span>
                        <strong>
                            <?= e($event['category_name'] ?? '-') ?>
                        </strong>
                    </div>


                    <div class="event-post-info-item">
                        <span>Status</span>
                        <strong>
                            <?= e(ucfirst($event['status'])) ?>
                        </strong>
                    </div>

                </div>

            </section>


            <!-- ACTION BUTTONS -->

            <div class="event-bottom-actions">

                <a
                    href="dashboard.php"
                    class="button button-secondary"
                >
                    <i class="bi bi-arrow-left"></i>
                    Back to Dashboard
                </a>


                <div class="event-action-right">

                    <a
                        href="edit-post.php?id=<?= (int) $event['post_id'] ?>"
                        class="button button-primary"
                    >
                        <i class="bi bi-pencil-square"></i>
                        Edit Event
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
                            value="<?= (int) $event['post_id'] ?>"
                        >

                        <button
                            type="submit"
                            class="button button-danger"
                        >
                            <i class="bi bi-trash"></i>
                            Delete Event
                        </button>

                    </form>

                </div>

            </div>


        </div>

    </main>

</div>


<script src="/NSBM_360/assets/js/president-view-event.js" defer></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

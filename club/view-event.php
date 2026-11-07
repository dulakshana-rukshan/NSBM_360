
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
        p.published_at,

        c.category_name

    FROM events e

    INNER JOIN posts p
        ON e.post_id = p.post_id

    LEFT JOIN categories c
        ON p.category_id = c.category_id

    WHERE e.event_id = :event_id
      AND p.club_id = :club_id

    LIMIT 1
";

$eventStmt = $pdo->prepare($eventSql);

$eventStmt->execute([
    'event_id' => $eventId,
    'club_id' => $clubId
]);

$event = $eventStmt->fetch();


// --------------------------------------------------
// 4. CHECK EVENT
// --------------------------------------------------

if (!$event) {
    $_SESSION['error_message'] = 'Event not found or you do not have permission to view it.';

    header('Location: dashboard.php');
    exit;
}


// --------------------------------------------------
// 5. FORMAT EVENT DETAILS
// --------------------------------------------------

$eventDateFormatted = date(
    'F j, Y',
    strtotime($event['event_date'])
);

$startTimeFormatted = date(
    'h:i A',
    strtotime($event['start_time'])
);

$endTimeFormatted = date(
    'h:i A',
    strtotime($event['end_time'])
);

$deadlineFormatted = null;

if (!empty($event['registration_deadline'])) {
    $deadlineFormatted = date(
        'F j, Y - h:i A',
        strtotime($event['registration_deadline'])
    );
}


// --------------------------------------------------
// 6. INCLUDE HEADER
// --------------------------------------------------

require_once __DIR__ . '/president_header.php';

?>


<div class="page-shell">

    <?php require __DIR__ . '/president_sidebar.php'; ?>

    <main class="main-content">


        <!-- PAGE HEADER -->

        <div class="page-header">

            <div>

                <p class="eyebrow">PRESIDENT WORKSPACE</p>

                <h1>Event Details</h1>

                <p class="page-subtitle">
                    View the complete information about this event.
                </p>

            </div>

            <a href="dashboard.php" class="button button-secondary">
                ← Back to Dashboard
            </a>

        </div>



        <!-- EVENT DETAILS CARD -->

        <section class="content-card form-card">


            <!-- EVENT TITLE -->

            <div class="section-heading">

                <div>

                    <h2><?= e($event['title']) ?></h2>

                    <p>
                        <?= e($event['category_name'] ?? 'Uncategorized') ?>
                    </p>

                </div>

                <span class="<?= e(post_status_class($event['status'])) ?>">
                    <?= e(ucfirst($event['status'])) ?>
                </span>

            </div>



            <!-- COVER IMAGE -->

            <?php if (!empty($event['cover_image'])): ?>

                <div class="event-cover-preview">

                    <img
                        src="<?= e($event['cover_image']) ?>"
                        alt="<?= e($event['title']) ?>"
                        class="event-cover-image"
                    >

                </div>

            <?php endif; ?>



            <!-- SHORT CAPTION -->

            <?php if (!empty($event['summary'])): ?>

                <div class="form-group">

                    <h3>Short Caption</h3>

                    <p>
                        <?= nl2br(e($event['summary'])) ?>
                    </p>

                </div>

            <?php endif; ?>



            <!-- EVENT DESCRIPTION -->

            <div class="form-group">

                <h3>Event Description</h3>

                <p>
                    <?= nl2br(e($event['content'])) ?>
                </p>

            </div>



            <!-- EVENT SCHEDULE -->

            <div class="section-heading">

                <div>

                    <h2>Event Schedule</h2>

                    <p>
                        Date, time and location of the event.
                    </p>

                </div>

            </div>



            <div class="form-grid">

                <div class="form-group">

                    <label>Event Date</label>

                    <p>
                        <?= e($eventDateFormatted) ?>
                    </p>

                </div>


                <div class="form-group">

                    <label>Venue</label>

                    <p>
                        <?= e($event['venue']) ?>
                    </p>

                </div>


                <div class="form-group">

                    <label>Start Time</label>

                    <p>
                        <?= e($startTimeFormatted) ?>
                    </p>

                </div>


                <div class="form-group">

                    <label>End Time</label>

                    <p>
                        <?= e($endTimeFormatted) ?>
                    </p>

                </div>

            </div>



            <!-- REGISTRATION INFORMATION -->

            <div class="section-heading">

                <div>

                    <h2>Registration Information</h2>

                    <p>
                        Registration details for participants.
                    </p>

                </div>

            </div>



            <div class="form-group">

                <label>Registration Deadline</label>

                <p>

                    <?php if ($deadlineFormatted): ?>

                        <?= e($deadlineFormatted) ?>

                    <?php else: ?>

                        Not specified

                    <?php endif; ?>

                </p>

            </div>



            <div class="form-group">

                <label>Registration Link</label>

                <p>

                    <?php if (!empty($event['registration_link'])): ?>

                        <a
                            href="<?= e($event['registration_link']) ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            Open Registration Link
                        </a>

                    <?php else: ?>

                        No registration link available.

                    <?php endif; ?>

                </p>

            </div>



            <!-- POST INFORMATION -->

            <div class="section-heading">

                <div>

                    <h2>Post Information</h2>

                </div>

            </div>



            <div class="form-grid">

                <div class="form-group">

                    <label>Created Date</label>

                    <p>
                        <?= e(display_date($event['created_at'])) ?>
                    </p>

                </div>


                <div class="form-group">

                    <label>Published Date</label>

                    <p>

                        <?php if (!empty($event['published_at'])): ?>

                            <?= e(display_date($event['published_at'])) ?>

                        <?php else: ?>

                            Not published yet

                        <?php endif; ?>

                    </p>

                </div>

            </div>



            <!-- ACTION BUTTONS -->

            <div class="form-actions">

                <a
                    href="dashboard.php"
                    class="button button-secondary"
                >
                    Back
                </a>


                <a
                    href="edit-event.php?id=<?= (int) $event['event_id'] ?>"
                    class="button button-primary"
                >
                    <i class="bi bi-pencil-square"></i>
                    Edit Event
                </a>


                <!-- DELETE EVENT FORM -->

                <form
                    method="POST"
                    action="delete-event.php"
                    onsubmit="return confirm('Are you sure you want to delete this event? This will also permanently delete its related post.');"
                >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e(csrf_token()) ?>"
                    >

                    <input
                        type="hidden"
                        name="event_id"
                        value="<?= (int) $event['event_id'] ?>"
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


        </section>


    </main>

</div>


<?php

require_once __DIR__ . '/../includes/footer.php';

?>

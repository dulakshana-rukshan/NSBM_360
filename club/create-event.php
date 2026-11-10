
<?php

require_once __DIR__ . '/president_auth.php';
require_once __DIR__ . '/president_functions.php';

$pageTitle = 'Create Event | NEWS 360';
$pageCSS = '/NSBM_360/club/president.css';
$isPresidentPage = true;


// --------------------------------------------------
// 1. GET CATEGORIES
// --------------------------------------------------

$categoryStmt = $pdo->query("
    SELECT category_id, category_name
    FROM categories
    ORDER BY category_name
");

$categories = $categoryStmt->fetchAll();

$defaultCategoryId = '';

foreach ($categories as $category) {
    if (strtolower($category['category_name']) === 'events') {
        $defaultCategoryId = $category['category_id'];
        break;
    }
}

if ($defaultCategoryId === '' && !empty($categories)) {
    $defaultCategoryId = $categories[0]['category_id'];
}


// --------------------------------------------------
// 2. FORM VALUES
// --------------------------------------------------

$errors = [];

$title = '';
$summary = '';
$content = '';
$categoryId = $defaultCategoryId;

$eventDate = '';
$startTime = '';
$endTime = '';
$venue = '';

$registrationLink = '';
$registrationDeadline = '';

$status = 'draft';


// --------------------------------------------------
// 3. HANDLE FORM SUBMISSION
// --------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $title = trim($_POST['title'] ?? '');
    $summary = trim($_POST['summary'] ?? '');
    $content = trim($_POST['content'] ?? '');

    $categoryId = filter_var(
        $_POST['category_id'] ?? '',
        FILTER_VALIDATE_INT
    );

    $eventDate = trim($_POST['event_date'] ?? '');
    $startTime = trim($_POST['start_time'] ?? '');
    $endTime = trim($_POST['end_time'] ?? '');

    $venue = trim($_POST['venue'] ?? '');

    $registrationLink = trim($_POST['registration_link'] ?? '');
    $registrationDeadline = trim($_POST['registration_deadline'] ?? '');

    $status = $_POST['status'] ?? 'draft';


    // --------------------------------------------------
    // 4. VALIDATION
    // --------------------------------------------------

    if (!csrf_is_valid()) {
        $errors[] = 'Your form session expired. Please refresh and try again.';
    }

    if ($title === '' || text_length($title) > 255) {
        $errors[] = 'Enter an event title with no more than 255 characters.';
    }

    if (text_length($summary) > 500) {
        $errors[] = 'The short caption cannot exceed 500 characters.';
    }

    if ($content === '') {
        $errors[] = 'Enter an event description.';
    }

    if (text_length($content) > 20000) {
        $errors[] = 'The event description is too long.';
    }


    // Validate category

    $validCategory = false;

    foreach ($categories as $category) {
        if (
            $categoryId !== false &&
            (int) $category['category_id'] === $categoryId
        ) {
            $validCategory = true;
            break;
        }
    }

    if (!$validCategory) {
        $errors[] = 'Choose a valid category.';
    }


    // Validate date

    $dateObject = DateTime::createFromFormat('!Y-m-d', $eventDate);

    if (
        !$dateObject ||
        $dateObject->format('Y-m-d') !== $eventDate
    ) {
        $errors[] = 'Choose a valid event date.';
    }


    // Validate times

    $validStartTime = preg_match(
        '/^(?:[01]\d|2[0-3]):[0-5]\d$/',
        $startTime
    );

    $validEndTime = preg_match(
        '/^(?:[01]\d|2[0-3]):[0-5]\d$/',
        $endTime
    );

    if (!$validStartTime) {
        $errors[] = 'Choose a valid start time.';
    }

    if (!$validEndTime) {
        $errors[] = 'Choose a valid end time.';
    } elseif ($validStartTime && $endTime <= $startTime) {
        $errors[] = 'End time must be later than start time.';
    }


    // Validate venue

    if ($venue === '' || text_length($venue) > 255) {
        $errors[] = 'Enter a venue with no more than 255 characters.';
    }


    // Validate registration link

    if ($registrationLink !== '') {

        if (
            !filter_var($registrationLink, FILTER_VALIDATE_URL) ||
            !in_array(
                strtolower((string) parse_url($registrationLink, PHP_URL_SCHEME)),
                ['http', 'https'],
                true
            )
        ) {
            $errors[] = 'Enter a valid HTTP or HTTPS registration link.';
        }
    }


    // Validate registration deadline

    $deadlineForDatabase = null;

    if ($registrationDeadline !== '') {

        $deadlineObject = DateTime::createFromFormat(
            '!Y-m-d\TH:i',
            $registrationDeadline
        );

        if (
            !$deadlineObject ||
            $deadlineObject->format('Y-m-d\TH:i') !== $registrationDeadline
        ) {
            $errors[] = 'Choose a valid registration deadline.';
        } else {

            $deadlineForDatabase = $deadlineObject->format('Y-m-d H:i:s');

            if (
                $dateObject &&
                $validStartTime &&
                $deadlineForDatabase > $eventDate . ' ' . $startTime . ':00'
            ) {
                $errors[] = 'Registration deadline must be before the event starts.';
            }
        }
    }


    // Validate status

    if (!in_array($status, ['draft', 'published'], true)) {
        $errors[] = 'Choose a valid event status.';
    }


    if (empty($categories)) {
        $errors[] = 'No categories are available. Add a category first.';
    }


    // --------------------------------------------------
    // 5. SAVE POST AND EVENT
    // --------------------------------------------------

    if (empty($errors)) {

        $newImagePath = null;

        try {

            // Upload cover image if selected

            if (
                isset($_FILES['cover_image']) &&
                $_FILES['cover_image']['error'] !== UPLOAD_ERR_NO_FILE
            ) {
                $newImagePath = save_post_image($_FILES['cover_image']);
            }


            // Published date

            $publishedAt = null;

            if ($status === 'published') {
                $publishedAt = date('Y-m-d H:i:s');
            }


            // Start transaction

            $pdo->beginTransaction();


            // INSERT INTO POSTS

            $postSql = "
                INSERT INTO posts (
                    created_by,
                    club_id,
                    category_id,
                    title,
                    summary,
                    content,
                    cover_image,
                    status,
                    published_at
                )
                VALUES (
                    :created_by,
                    :club_id,
                    :category_id,
                    :title,
                    :summary,
                    :content,
                    :cover_image,
                    :status,
                    :published_at
                )
            ";

            $postStmt = $pdo->prepare($postSql);

            $postStmt->execute([
                'created_by' => $userId,
                'club_id' => $clubId,
                'category_id' => $categoryId,
                'title' => $title,
                'summary' => $summary !== '' ? $summary : null,
                'content' => $content,
                'cover_image' => $newImagePath,
                'status' => $status,
                'published_at' => $publishedAt
            ]);


            // Get inserted post ID

            $postId = (int) $pdo->lastInsertId();


            // INSERT INTO EVENTS

            $eventSql = "
                INSERT INTO events (
                    post_id,
                    event_date,
                    start_time,
                    end_time,
                    venue,
                    registration_deadline,
                    registration_link
                )
                VALUES (
                    :post_id,
                    :event_date,
                    :start_time,
                    :end_time,
                    :venue,
                    :registration_deadline,
                    :registration_link
                )
            ";

            $eventStmt = $pdo->prepare($eventSql);

            $eventStmt->execute([
                'post_id' => $postId,
                'event_date' => $eventDate,
                'start_time' => $startTime . ':00',
                'end_time' => $endTime . ':00',
                'venue' => $venue,
                'registration_deadline' => $deadlineForDatabase,
                'registration_link' => $registrationLink !== ''
                    ? $registrationLink
                    : null
            ]);


            // Save both records

            $pdo->commit();


            // Redirect after success

            header('Location: dashboard.php?event_created=1');
            exit;


        } catch (Throwable $exception) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if ($newImagePath) {
                delete_post_image($newImagePath);
            }

            error_log(
                'NEWS 360 create event error: ' . $exception->getMessage()
            );

            $errors[] = 'The event could not be saved. Please check your details and try again.';
        }
    }
}


// --------------------------------------------------
// 6. HEADER
// --------------------------------------------------

require_once __DIR__ . '/president_header.php';

?>

<div class="page-shell">

    <?php require __DIR__ . '/president_sidebar.php'; ?>

    <main class="main-content">

        <div class="page-header">

            <div>
                <p class="eyebrow">PRESIDENT WORKSPACE</p>

                <h1>Create Event</h1>

                <p class="page-subtitle">
                    Add a new event for <?= e($clubName) ?>.
                </p>
            </div>

            <a href="dashboard.php" class="button button-secondary">
                ← Back to Dashboard
            </a>

        </div>


        <?php if (!empty($errors)): ?>

            <div class="alert alert-error">

                <strong>Please check the following:</strong>

                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>

            </div>

        <?php endif; ?>


        <section class="content-card form-card">

            <div class="section-heading">
                <div>
                    <h2>Event Information</h2>
                    <p>Enter the details of your club event.</p>
                </div>
            </div>


            <form
                method="POST"
                action="create-event.php"
                enctype="multipart/form-data"
                class="post-form"
            >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e(csrf_token()) ?>"
                >


                <div class="form-group">

                    <label for="title">Event Title *</label>

                    <input
                        type="text"
                        id="title"
                        name="title"
                        maxlength="255"
                        value="<?= e($title) ?>"
                        placeholder="Enter event title"
                        required
                    >

                </div>


                <div class="form-group">

                    <label for="summary">Short Caption</label>

                    <textarea
                        id="summary"
                        name="summary"
                        rows="3"
                        maxlength="500"
                        placeholder="Write a short introduction..."
                    ><?= e($summary) ?></textarea>

                    <small>Maximum 500 characters.</small>

                </div>


                <div class="form-group">

                    <label for="content">Event Description *</label>

                    <textarea
                        id="content"
                        name="content"
                        rows="8"
                        maxlength="20000"
                        placeholder="Provide details about your event..."
                        required
                    ><?= e($content) ?></textarea>

                </div>


                <div class="form-grid">

                    <div class="form-group">

                        <label for="category_id">Category *</label>

                        <select
                            id="category_id"
                            name="category_id"
                            required
                        >

                            <option value="">Select a category</option>

                            <?php foreach ($categories as $category): ?>

                                <option
                                    value="<?= (int) $category['category_id'] ?>"
                                    <?= (string) $categoryId === (string) $category['category_id'] ? 'selected' : '' ?>
                                >
                                    <?= e($category['category_name']) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <div class="form-group">

                        <label for="status">Event Status *</label>

                        <select id="status" name="status" required>

                            <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>
                                Draft
                            </option>

                            <option value="published" <?= $status === 'published' ? 'selected' : '' ?>>
                                Published
                            </option>

                        </select>

                    </div>

                </div>


                <div class="form-group">

                    <label for="cover_image">Event Cover Image</label>

                    <input
                        type="file"
                        id="cover_image"
                        name="cover_image"
                        accept="image/jpeg,image/png,image/webp"
                    >

                    <small>
                        Optional. JPG, PNG or WEBP. Maximum 5 MB.
                    </small>

                </div>


                <div class="section-heading">
                    <div>
                        <h2>Event Schedule</h2>
                        <p>Set the date and time of your event.</p>
                    </div>
                </div>


                <div class="form-group">

                    <label for="event_date">Event Date *</label>

                    <input
                        type="date"
                        id="event_date"
                        name="event_date"
                        value="<?= e($eventDate) ?>"
                        required
                    >

                </div>


                <div class="form-grid">

                    <div class="form-group">

                        <label for="start_time">Start Time *</label>

                        <input
                            type="time"
                            id="start_time"
                            name="start_time"
                            value="<?= e($startTime) ?>"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label for="end_time">End Time *</label>

                        <input
                            type="time"
                            id="end_time"
                            name="end_time"
                            value="<?= e($endTime) ?>"
                            required
                        >

                    </div>

                </div>


                <div class="form-group">

                    <label for="venue">Venue *</label>

                    <input
                        type="text"
                        id="venue"
                        name="venue"
                        maxlength="255"
                        value="<?= e($venue) ?>"
                        placeholder="Enter event location"
                        required
                    >

                </div>


                <div class="section-heading">
                    <div>
                        <h2>Registration Information</h2>
                        <p>Add registration details for participants.</p>
                    </div>
                </div>


                <div class="form-group">

                    <label for="registration_link">Registration Link</label>

                    <input
                        type="url"
                        id="registration_link"
                        name="registration_link"
                        value="<?= e($registrationLink) ?>"
                        placeholder="https://example.com/register"
                    >

                </div>


                <div class="form-group">

                    <label for="registration_deadline">
                        Registration Deadline
                    </label>

                    <input
                        type="datetime-local"
                        id="registration_deadline"
                        name="registration_deadline"
                        value="<?= e($registrationDeadline) ?>"
                    >

                </div>


                <div class="form-actions">

                    <a href="dashboard.php" class="button button-secondary">
                        Cancel
                    </a>

                    <button type="submit" class="button button-primary">
                        Create Event
                    </button>

                </div>

            </form>

        </section>

    </main>

</div>


<?php require_once __DIR__ . '/../includes/footer.php'; ?>

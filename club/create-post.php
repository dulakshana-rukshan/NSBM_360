
<?php

require_once __DIR__ . '/president_auth.php';

// ------------------------------------
// PAGE SETTINGS
// ------------------------------------

$pageTitle = 'Create New Post | NEWS 360';
$pageCSS = '/NSBM_360/assets/css/president.css';
$isPresidentPage = true;


// ------------------------------------
// VERIFY CLUB MEMBERSHIP
// ------------------------------------

$membershipStmt = $pdo->prepare("
    SELECT
        cm.club_id,
        cm.club_role,
        c.club_name,
        c.club_type
    FROM club_memberships cm
    INNER JOIN clubs c ON cm.club_id = c.club_id
    WHERE cm.user_id = :user_id
      AND cm.club_id = :club_id
      AND cm.membership_status = 'active'
      AND c.status = 'active'
      AND cm.club_role IN ('president', 'vice_president')
    LIMIT 1
");

$membershipStmt->execute([
    'user_id' => $userId,
    'club_id' => $clubId
]);

$membership = $membershipStmt->fetch(PDO::FETCH_ASSOC);

if (!$membership) {
    http_response_code(403);
    exit('You do not have permission to create posts for this club.');
}

$clubType = $membership['club_type'];
$clubName = $membership['club_name'];


// ------------------------------------
// DEFINE ALLOWED MAIN CATEGORIES
// ------------------------------------

if (strtolower($clubType) === 'sports') {
    $allowedMainCategoryNames = ['sports', 'events'];
} else {
    $allowedMainCategoryNames = ['academics', 'events', 'news'];
}

$categoryPlaceholders = implode(
    ',',
    array_fill(0, count($allowedMainCategoryNames), '?')
);


// ------------------------------------
// LOAD ALLOWED MAIN CATEGORIES
// ------------------------------------

$mainCategoriesStmt = $pdo->prepare("
    SELECT category_id, category_name
    FROM categories
    WHERE LOWER(TRIM(category_name)) IN ($categoryPlaceholders)
    ORDER BY category_name
");

$mainCategoriesStmt->execute($allowedMainCategoryNames);

$mainCategories = $mainCategoriesStmt->fetchAll(PDO::FETCH_ASSOC);


// ------------------------------------
// LOAD SUBCATEGORIES
// ------------------------------------

$subCategoriesStmt = $pdo->query("
    SELECT
        subcategory_id,
        category_id,
        subcategory_name
    FROM subcategories
    WHERE LOWER(TRIM(subcategory_name)) <> 'clubs'
    ORDER BY subcategory_name
");

$subCategories = $subCategoriesStmt->fetchAll(PDO::FETCH_ASSOC);


// ------------------------------------
// LOAD FACULTIES
// ------------------------------------

$facultiesStmt = $pdo->query("
    SELECT faculty_id, faculty_name
    FROM faculties
    ORDER BY faculty_name
");

$faculties = $facultiesStmt->fetchAll(PDO::FETCH_ASSOC);


// ------------------------------------
// LOAD SPORTS ASSIGNED TO THIS CLUB
// ------------------------------------

$sportsStmt = $pdo->prepare("
    SELECT s.sport_id, s.sport_name
    FROM club_sports cs
    INNER JOIN sports s ON cs.sport_id = s.sport_id
    WHERE cs.club_id = :club_id
      AND s.is_active = 1
    ORDER BY s.sport_name
");

$sportsStmt->execute([
    'club_id' => $clubId
]);

$sports = $sportsStmt->fetchAll(PDO::FETCH_ASSOC);


// ------------------------------------
// FIND MAIN CATEGORY IDs
// ------------------------------------

$categoryIds = [];

foreach ($mainCategories as $category) {
    $categoryIds[strtolower(trim($category['category_name']))] =
        (int) $category['category_id'];
}

$newsCategoryId = $categoryIds['news'] ?? null;
$eventsCategoryId = $categoryIds['events'] ?? null;
$sportsCategoryId = $categoryIds['sports'] ?? null;


// ------------------------------------
// FORM VARIABLES
// ------------------------------------

$errors = [];

$title = '';
$summary = '';
$content = '';

$mainCategoryId = '';
$subcategoryId = '';

$selectedFacultyIds = [];
$allFacultiesChecked = false;
$selectedSportId = '';

$status = 'draft';

$eventStartAt = '';
$eventEndAt = '';
$eventVenue = '';
$registrationDeadline = '';
$registrationLink = '';


// ------------------------------------
// PROCESS FORM
// ------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $title = trim($_POST['title'] ?? '');
    $summary = trim($_POST['summary'] ?? '');
    $content = trim($_POST['content'] ?? '');

    $mainCategoryId = filter_var(
        $_POST['main_category_id'] ?? '',
        FILTER_VALIDATE_INT
    );

    $subcategoryId = filter_var(
        $_POST['subcategory_id'] ?? '',
        FILTER_VALIDATE_INT
    );

    $selectedSportId = filter_var(
        $_POST['sport_id'] ?? '',
        FILTER_VALIDATE_INT
    );

    $status = $_POST['status'] ?? 'draft';

    $eventStartAt = trim($_POST['event_start_at'] ?? '');
    $eventEndAt = trim($_POST['event_end_at'] ?? '');
    $eventVenue = trim($_POST['event_venue'] ?? '');

    $registrationDeadline = trim(
        $_POST['registration_deadline'] ?? ''
    );

    $registrationLink = trim(
        $_POST['registration_link'] ?? ''
    );


    // SELECTED FACULTIES

    $allFacultiesChecked = ($_POST['all_faculties'] ?? '') === '1';

    $validFacultyIds = array_map(
        'intval',
        array_column($faculties, 'faculty_id')
    );

    $submittedFacultyIds = $_POST['faculty_ids'] ?? [];

    if (!is_array($submittedFacultyIds)) {
        $errors[] = 'Invalid faculty selection.';
        $submittedFacultyIds = [];
    }

    $selectedFacultyIds = $allFacultiesChecked
        ? $validFacultyIds
        : array_values(array_unique(array_map('intval', $submittedFacultyIds)));


    if (!csrf_is_valid()) {
        $errors[] = 'Your form session expired. Please refresh and try again.';
    }


    if ($title === '' || text_length($title) > 255) {
        $errors[] = 'Enter a title with no more than 255 characters.';
    }


    // SUMMARY VALIDATION

    if (text_length($summary) > 500) {
        $errors[] = 'The summary must contain 500 characters or fewer.';
    }


    // CONTENT VALIDATION

    if ($content === '') {
        $errors[] = 'Enter the post content.';
    }


    // MAIN CATEGORY VALIDATION

    $selectedMainCategory = null;

    if ($mainCategoryId === false || $mainCategoryId < 1) {

        $errors[] = 'Choose a main category.';

    } else {

        $mainCategoryCheck = $pdo->prepare("
        SELECT category_id, category_name
        FROM categories
        WHERE category_id = :category_id
        LIMIT 1
        ");

        $mainCategoryCheck->execute([
            'category_id' => $mainCategoryId
        ]);

        $selectedMainCategory = $mainCategoryCheck->fetch(
            PDO::FETCH_ASSOC
        );

        if (!$selectedMainCategory) {

            $errors[] = 'The selected main category is invalid.';

        } elseif (
            !in_array(
                strtolower(trim($selectedMainCategory['category_name'])),
                $allowedMainCategoryNames,
                true
            )
        ) {

            $errors[] = 'Your club is not allowed to use this main category.';
        }
    }


    // SUBCATEGORY VALIDATION

    $selectedSubcategory = null;

    if ($subcategoryId === false || $subcategoryId < 1) {

        $errors[] = 'Choose a subcategory.';

    } elseif ($mainCategoryId !== false && $mainCategoryId > 0) {

        $subcategoryCheck = $pdo->prepare("
            SELECT
                subcategory_id,
                category_id,
                subcategory_name
            FROM subcategories
            WHERE subcategory_id = :subcategory_id
              AND category_id = :category_id
              AND LOWER(TRIM(subcategory_name)) <> 'clubs'
            LIMIT 1
        ");

        $subcategoryCheck->execute([
            'subcategory_id' => $subcategoryId,
            'category_id' => $mainCategoryId
        ]);

        $selectedSubcategory = $subcategoryCheck->fetch(
            PDO::FETCH_ASSOC
        );

        if (!$selectedSubcategory) {
            $errors[] = 'Choose a valid subcategory for the selected main category.';
        }
    }


    // FACULTY VALIDATION

    if (!empty($selectedFacultyIds)) {

        $placeholders = implode(
            ',',
            array_fill(0, count($selectedFacultyIds), '?')
        );

        $facultyCheck = $pdo->prepare("
            SELECT COUNT(*)
            FROM faculties
            WHERE faculty_id IN ($placeholders)
        ");

        $facultyCheck->execute($selectedFacultyIds);

        if (
            (int) $facultyCheck->fetchColumn()
            !== count($selectedFacultyIds)
        ) {
            $errors[] = 'One or more selected faculties are invalid.';
        }
    }


    // STATUS VALIDATION

    if (!in_array($status, ['draft', 'published'], true)) {
        $errors[] = 'Choose Draft or Published.';
    }


    // SPORTS VALIDATION

    $categoryName = strtolower(
        trim($selectedMainCategory['category_name'] ?? '')
    );

    $subcategoryName = strtolower(
        trim($selectedSubcategory['subcategory_name'] ?? '')
    );

    $isSportsClub = strtolower($clubType) === 'sports';

    $isSportsPost = ($categoryName === 'sports');

    $isSportsEvent = (
        $categoryName === 'events'
        && $subcategoryName === 'sports events'
    );

    $requiresSport = $isSportsPost || $isSportsEvent;


    if ($isSportsPost && !$isSportsClub) {
        $errors[] = 'Only a Sports Club can create posts under the Sports category.';
    }

    if ($isSportsEvent && !$isSportsClub) {
        $errors[] = 'Only a Sports Club can create Sports Events.';
    }

    if ($requiresSport) {

        if ($selectedSportId === false || $selectedSportId < 1) {

            $errors[] = 'Choose a sport for this post or event.';

        } else {

            $sportCheck = $pdo->prepare("
                SELECT COUNT(*)
                FROM club_sports cs
                INNER JOIN sports s ON cs.sport_id = s.sport_id
                WHERE cs.club_id = :club_id
                  AND cs.sport_id = :sport_id
                  AND s.is_active = 1
            ");

            $sportCheck->execute([
                'club_id' => $clubId,
                'sport_id' => $selectedSportId
            ]);

            if ((int) $sportCheck->fetchColumn() === 0) {
                $errors[] = 'The selected sport is not assigned to your club.';
            }
        }
    }


    // EVENT VALIDATION

    $eventStartSql = null;
    $eventEndSql = null;
    $registrationDeadlineSql = null;

    if ($categoryName === 'events') {

        if (
            $eventStartAt === ''
            || $eventEndAt === ''
            || $eventVenue === ''
        ) {
            $errors[] = 'Enter the event start, end and venue.';
        } else {

            $startDate = DateTime::createFromFormat(
                'Y-m-d\TH:i',
                $eventStartAt
            );

            $endDate = DateTime::createFromFormat(
                'Y-m-d\TH:i',
                $eventEndAt
            );

            if (!$startDate || !$endDate) {

                $errors[] = 'Enter valid event dates and times.';

            } elseif ($endDate <= $startDate) {

                $errors[] = 'The event end date and time must be after the start date and time.';

            } else {

                $eventStartSql = $startDate->format('Y-m-d H:i:s');
                $eventEndSql = $endDate->format('Y-m-d H:i:s');
            }
        }

        if ($registrationDeadline !== '') {

            $deadlineDate = DateTime::createFromFormat(
                'Y-m-d\TH:i',
                $registrationDeadline
            );

            if (!$deadlineDate) {
                $errors[] = 'Enter a valid registration deadline.';
            } else {
                $registrationDeadlineSql = $deadlineDate->format(
                    'Y-m-d H:i:s'
                );
            }
        }

        if (
            $registrationLink !== ''
            && !filter_var($registrationLink, FILTER_VALIDATE_URL)
        ) {
            $errors[] = 'Enter a valid registration URL.';
        }
    }


    // SAVE POST AND RELATED DATA

    if (empty($errors)) {

        $uploadedImagePaths = [];
        $transactionStarted = false;
        $coverImagePath = null;

        try {

            $pdo->beginTransaction();
            $transactionStarted = true;


            // COVER IMAGE

            if (
                isset($_FILES['cover_image'])
                && $_FILES['cover_image']['error'] !== UPLOAD_ERR_NO_FILE
            ) {

                $coverImagePath = save_post_image(
                    $_FILES['cover_image']
                );

                if ($coverImagePath === null) {
                    throw new RuntimeException(
                        'The cover image could not be uploaded.'
                    );
                }

                $uploadedImagePaths[] = $coverImagePath;
            }


            // INSERT MAIN POST

            $insertPost = $pdo->prepare("
                INSERT INTO posts
                (
                    created_by,
                    club_id,
                    category_id,
                    subcategory_id,
                    sport_id,
                    title,
                    summary,
                    content,
                    cover_image,
                    status,
                    published_at
                )
                VALUES
                (
                    :created_by,
                    :club_id,
                    :category_id,
                    :subcategory_id,
                    :sport_id,
                    :title,
                    :summary,
                    :content,
                    :cover_image,
                    :status,
                    :published_at
                )
            ");

            $insertPost->execute([

                'created_by' => $userId,
                'club_id' => $clubId,

                'category_id' => $mainCategoryId,
                'subcategory_id' => $subcategoryId,

                'sport_id' => $requiresSport
                    ? $selectedSportId
                    : null,

                'title' => $title,

                'summary' => $summary !== ''
                    ? $summary
                    : null,

                'content' => $content,

                'cover_image' => $coverImagePath,

                'status' => $status,

                'published_at' => $status === 'published'
                    ? date('Y-m-d H:i:s')
                    : null
            ]);

            $postId = (int) $pdo->lastInsertId();


            // SAVE FACULTIES

            if (!empty($selectedFacultyIds)) {

                $facultyInsert = $pdo->prepare("
                    INSERT INTO post_faculties
                    (post_id, faculty_id)
                    VALUES
                    (:post_id, :faculty_id)
                ");

                foreach ($selectedFacultyIds as $facultyId) {

                    $facultyInsert->execute([
                        'post_id' => $postId,
                        'faculty_id' => $facultyId
                    ]);
                }
            }


            // SAVE EVENT DETAILS

            if ($categoryName === 'events') {

                $startDateObject = new DateTime($eventStartSql);
                $endDateObject = new DateTime($eventEndSql);

                $eventInsert = $pdo->prepare("
                    INSERT INTO events
                    (
                        post_id,
                        event_date,
                        start_time,
                        end_time,
                        start_at,
                        end_at,
                        venue,
                        registration_deadline,
                        registration_link
                    )
                    VALUES
                    (
                        :post_id,
                        :event_date,
                        :start_time,
                        :end_time,
                        :start_at,
                        :end_at,
                        :venue,
                        :registration_deadline,
                        :registration_link
                    )
                ");

                $eventInsert->execute([

                    'post_id' => $postId,

                    'event_date' => $startDateObject->format('Y-m-d'),
                    'start_time' => $startDateObject->format('H:i:s'),
                    'end_time' => $endDateObject->format('H:i:s'),

                    'start_at' => $eventStartSql,
                    'end_at' => $eventEndSql,

                    'venue' => $eventVenue,

                    'registration_deadline' => $registrationDeadlineSql,

                    'registration_link' => $registrationLink !== ''
                        ? $registrationLink
                        : null
                ]);
            }


            // ADDITIONAL IMAGES

            if (
                isset($_FILES['additional_images'])
                && is_array($_FILES['additional_images']['name'])
            ) {

                $additionalImages = $_FILES['additional_images'];

                $imageCount = 0;

                foreach ($additionalImages['error'] as $errorCode) {

                    if ($errorCode !== UPLOAD_ERR_NO_FILE) {
                        $imageCount++;
                    }
                }

                if ($imageCount > 5) {
                    throw new RuntimeException(
                        'You can upload a maximum of 5 additional images.'
                    );
                }

                $imageInsert = $pdo->prepare("
                    INSERT INTO post_images
                    (
                        post_id,
                        image_path,
                        alt_text,
                        display_order
                    )
                    VALUES
                    (
                        :post_id,
                        :image_path,
                        :alt_text,
                        :display_order
                    )
                ");

                $displayOrder = 1;

                for (
                    $i = 0;
                    $i < count($additionalImages['name']);
                    $i++
                ) {

                    if (
                        $additionalImages['error'][$i]
                        === UPLOAD_ERR_NO_FILE
                    ) {
                        continue;
                    }

                    $singleImage = [
                        'name' => $additionalImages['name'][$i],
                        'type' => $additionalImages['type'][$i],
                        'tmp_name' => $additionalImages['tmp_name'][$i],
                        'error' => $additionalImages['error'][$i],
                        'size' => $additionalImages['size'][$i]
                    ];

                    $additionalImagePath = save_post_image(
                        $singleImage
                    );

                    if ($additionalImagePath === null) {
                        throw new RuntimeException(
                            'One of the additional images could not be uploaded.'
                        );
                    }

                    $uploadedImagePaths[] = $additionalImagePath;

                    $imageInsert->execute([

                        'post_id' => $postId,

                        'image_path' => $additionalImagePath,

                        'alt_text' => null,

                        'display_order' => $displayOrder
                    ]);

                    $displayOrder++;
                }
            }


            // COMMIT

            $pdo->commit();
            $transactionStarted = false;

            header('Location: manage-posts.php?created=1');
            exit;


        } catch (RuntimeException $exception) {

            if ($transactionStarted && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            foreach ($uploadedImagePaths as $uploadedPath) {
                delete_post_image($uploadedPath);
            }

            $errors[] = $exception->getMessage();


        } catch (Throwable $exception) {

            if ($transactionStarted && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            foreach ($uploadedImagePaths as $uploadedPath) {
                delete_post_image($uploadedPath);
            }

            error_log(
                'NEWS 360 create post error: '
                . $exception->getMessage()
            );

            $errors[] = 'The post could not be saved. Please check the database and try again.';
        }
    }
}


// ------------------------------------
// INCLUDE HEADER
// ------------------------------------

require_once __DIR__ . '/president_header.php';

?>


<div class="page-shell">

    <?php require __DIR__ . '/president_sidebar.php'; ?>

    <main class="main-content">

        <div class="page-header">

            <div>

                <p class="eyebrow">PRESIDENT WORKSPACE</p>

                <h1>Create New Post</h1>

                <p class="page-subtitle">
                    Write an update for <?= e($clubName) ?>.
                </p>

            </div>

            <a
                href="manage-posts.php"
                class="button button-secondary"
            >
                <span aria-hidden="true">←</span>
                Back to My Posts
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


        <?php if (empty($mainCategories) || empty($subCategories)): ?>

            <div class="alert alert-warning">
                Main categories or subcategories are not available.
                Please check the categories and subcategories tables.
            </div>

        <?php endif; ?>


        <?php if (empty($faculties)): ?>

            <div class="alert alert-warning">
                No faculties are available.
            </div>

        <?php endif; ?>


        <section class="content-card form-card">

            <div class="section-heading">

                <div>
                    <h2>Post Information</h2>
                    <p>Fields marked with * are required.</p>
                </div>

            </div>


            <form
                method="POST"
                action="create-post.php"
                enctype="multipart/form-data"
                class="post-form"
            >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e(csrf_token()) ?>"
                >


                <!-- TITLE -->

                <div class="form-group">

                    <label for="title">Post Title *</label>

                    <input
                        type="text"
                        id="title"
                        name="title"
                        maxlength="255"
                        required
                        value="<?= e($title) ?>"
                        placeholder="Enter a clear post title"
                    >

                </div>


                <!-- CATEGORIES AND STATUS -->

                <div class="form-grid">

                    <div class="form-group">

                        <label for="main_category_id">
                            Main Category *
                        </label>

                        <select
                            id="main_category_id"
                            name="main_category_id"
                            data-club-type="<?= e($clubType) ?>"
                            required
                            <?= empty($mainCategories) ? 'disabled' : '' ?>
                        >

                            <option value="">
                                Choose a main category
                            </option>

                            <?php foreach ($mainCategories as $category): ?>

                                <option
                                    value="<?= (int) $category['category_id'] ?>"
                                    data-category-name="<?= e($category['category_name']) ?>"
                                    <?= (string) $mainCategoryId === (string) $category['category_id'] ? 'selected' : '' ?>
                                >
                                    <?= e($category['category_name']) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <div class="form-group">

                        <label for="subcategory_id">
                            Subcategory *
                        </label>

                        <select
                            id="subcategory_id"
                            name="subcategory_id"
                            required
                            <?= empty($subCategories) ? 'disabled' : '' ?>
                        >

                            <option value="">
                                Choose a subcategory
                            </option>

                            <?php foreach ($subCategories as $subcategory): ?>

                                <option
                                    value="<?= (int) $subcategory['subcategory_id'] ?>"
                                    data-parent-id="<?= (int) $subcategory['category_id'] ?>"
                                    data-subcategory-name="<?= e($subcategory['subcategory_name']) ?>"
                                    <?= (string) $subcategoryId === (string) $subcategory['subcategory_id'] ? 'selected' : '' ?>
                                >
                                    <?= e($subcategory['subcategory_name']) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <div class="form-group">

                        <label for="status">Post Status *</label>

                        <select id="status" name="status">

                            <option
                                value="draft"
                                <?= $status === 'draft' ? 'selected' : '' ?>
                            >
                                Draft
                            </option>

                            <option
                                value="published"
                                <?= $status === 'published' ? 'selected' : '' ?>
                            >
                                Published
                            </option>

                        </select>

                        <small>
                            Save as Draft or publish immediately.
                        </small>

                    </div>

                </div>


                <!-- SPORTS SECTION -->

                <div
                    class="form-group"
                    id="sport-section"
                    hidden
                >

                    <label for="sport_id">Select Sport *</label>

                    <select
                        id="sport_id"
                        name="sport_id"
                    >

                        <option value="">Choose a sport</option>

                        <?php foreach ($sports as $sport): ?>

                            <option
                                value="<?= (int) $sport['sport_id'] ?>"
                                <?= (string) $selectedSportId === (string) $sport['sport_id'] ? 'selected' : '' ?>
                            >
                                <?= e($sport['sport_name']) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                    <small>
                        Only sports assigned to your club are displayed.
                    </small>

                    <?php if (empty($sports)): ?>

                        <small>
                            No active sports are assigned to your club.
                            Please contact the administrator.
                        </small>

                    <?php endif; ?>

                </div>


                <!-- EVENT SECTION -->

                <div id="event-section" hidden>

                    <div class="section-heading">
                        <div>
                            <h2>Event Information</h2>
                            <p>Enter the details of your event.</p>
                        </div>
                    </div>


                    <div class="form-grid">

                        <div class="form-group">

                            <label for="event_start_at">
                                Event Start Date & Time *
                            </label>

                            <input
                                type="datetime-local"
                                id="event_start_at"
                                name="event_start_at"
                                value="<?= e($eventStartAt) ?>"
                            >

                        </div>


                        <div class="form-group">

                            <label for="event_end_at">
                                Event End Date & Time *
                            </label>

                            <input
                                type="datetime-local"
                                id="event_end_at"
                                name="event_end_at"
                                value="<?= e($eventEndAt) ?>"
                            >

                        </div>


                        <div class="form-group">

                            <label for="event_venue">
                                Event Venue *
                            </label>

                            <input
                                type="text"
                                id="event_venue"
                                name="event_venue"
                                maxlength="255"
                                value="<?= e($eventVenue) ?>"
                                placeholder="Enter event venue"
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

                    </div>


                    <div class="form-group">

                        <label for="registration_link">
                            Registration Link
                        </label>

                        <input
                            type="url"
                            id="registration_link"
                            name="registration_link"
                            maxlength="500"
                            value="<?= e($registrationLink) ?>"
                            placeholder="https://example.com/register"
                        >

                    </div>

                </div>


                <!-- FACULTIES -->

                <div class="form-group faculty-checkbox-group">

                    <label>Relevant Faculties</label>

                    <small>
                        Select one or more faculties, or choose All Faculties.
                    </small>

                    <div class="faculty-selection">

                        <label class="faculty-option faculty-all">
                            <input
                                type="checkbox"
                                id="all_faculties"
                                name="all_faculties"
                                value="1"
                                <?= $allFacultiesChecked ? 'checked' : '' ?>
                            >
                            <strong>All Faculties</strong>
                        </label>

                        <div id="faculty-options">

                            <?php foreach ($faculties as $faculty): ?>

                                <label class="faculty-option">
                                    <input
                                        type="checkbox"
                                        class="faculty-checkbox"
                                        name="faculty_ids[]"
                                        value="<?= (int)$faculty['faculty_id'] ?>"
                                        <?= in_array(
                                            (int)$faculty['faculty_id'],
                                            $selectedFacultyIds,
                                            true
                                        ) ? 'checked' : '' ?>
                                    >

                                    <?= e($faculty['faculty_name']) ?>
                                </label>

                            <?php endforeach; ?>

                        </div>

                    </div>

                </div>


                <!-- SUMMARY -->

                <div class="form-group">

                    <label for="summary">Short Summary</label>

                    <textarea
                        id="summary"
                        name="summary"
                        rows="3"
                        maxlength="500"
                        placeholder="Write a short summary (maximum 500 characters)"
                    ><?= e($summary) ?></textarea>

                </div>


                <!-- CONTENT -->

                <div class="form-group">

                    <label for="content">Post Content *</label>

                    <textarea
                        id="content"
                        name="content"
                        rows="9"
                        required
                        placeholder="Write the full post here..."
                    ><?= e($content) ?></textarea>

                </div>


                <!-- COVER IMAGE -->

                <div class="form-group">

                    <label for="cover_image">Cover Image</label>

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


                <!-- ADDITIONAL IMAGES -->

                <div class="form-group">

                    <label for="additional_images">
                        Additional Images
                    </label>

                    <input
                        type="file"
                        id="additional_images"
                        name="additional_images[]"
                        accept="image/jpeg,image/png,image/webp"
                        multiple
                    >

                    <small>
                        Optional. Upload up to 5 additional JPG, PNG or WEBP images.
                    </small>

                </div>


                <!-- BUTTONS -->

                <div class="form-actions">

                    <a
                        href="manage-posts.php"
                        class="button button-secondary"
                    >
                        Cancel
                    </a>

                    <button
                        type="submit"
                        class="button button-primary"
                        <?= empty($mainCategories) || empty($subCategories) ? 'disabled' : '' ?>
                    >
                        Save Post
                    </button>

                </div>

            </form>

        </section>

    </main>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const allCheckbox = document.getElementById('all_faculties');
    const facultyCheckboxes = document.querySelectorAll('.faculty-checkbox');

    if (!allCheckbox) return;

    function updateFacultySelection() {
        facultyCheckboxes.forEach(checkbox => {
            if (allCheckbox.checked) {
                checkbox.checked = true;
                checkbox.disabled = true;
            } else {
                checkbox.disabled = false;
            }
        });
    }

    allCheckbox.addEventListener('change', function () {
        if (!this.checked) {
            facultyCheckboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
        }

        updateFacultySelection();
    });

    facultyCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function () {
            const allSelected = Array.from(facultyCheckboxes)
                .every(item => item.checked);

            if (allSelected && facultyCheckboxes.length > 0) {
                allCheckbox.checked = true;
                updateFacultySelection();
            }
        });
    });

    updateFacultySelection();
});
</script>


<script src="/NSBM_360/assets/js/president-create-post.js" defer></script>


<?php require_once __DIR__ . '/../includes/footer.php'; ?>


<?php

require_once __DIR__ . '/president_auth.php';

// ------------------------------------
// 1. GET POST ID
// ------------------------------------

$postId = filter_var(
    $_GET['id'] ?? '',
    FILTER_VALIDATE_INT
);

if ($postId === false || $postId < 1) {
    show_access_message(
        400,
        'No post selected',
        'Open Manage My Posts and select the Edit post link.'
    );
    exit;
}


// ------------------------------------
// 2. GET CATEGORIES, SUBCATEGORIES,
//    FACULTIES AND CLUB SPORTS
// ------------------------------------

$isSportsClub = (
    strtolower(trim((string) $clubType)) === 'sports'
);

$allowedCategoryNames = $isSportsClub
    ? ['sports', 'events']
    : ['academics', 'events', 'news'];


// Main categories
$categoriesStmt = $pdo->query(
    "SELECT category_id, category_name
     FROM categories
     WHERE parent_category_id IS NULL
     ORDER BY category_name"
);

$allCategories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);

$categories = array_values(
    array_filter($allCategories, function ($category) use (
        $allowedCategoryNames
    ) {
        return in_array(
            strtolower(trim($category['category_name'])),
            $allowedCategoryNames,
            true
        );
    })
);

$allowedCategoryIds = array_map(
    'intval',
    array_column($categories, 'category_id')
);


// Subcategories
$subcategoriesStmt = $pdo->query(
    "SELECT subcategory_id, category_id, subcategory_name
     FROM subcategories
     ORDER BY subcategory_name"
);

$allSubcategories = $subcategoriesStmt->fetchAll(PDO::FETCH_ASSOC);

$subcategories = array_values(
    array_filter($allSubcategories, function ($subcategory) use (
        $allowedCategoryIds,
        $isSportsClub
    ) {

        if (!in_array(
            (int) $subcategory['category_id'],
            $allowedCategoryIds,
            true
        )) {
            return false;
        }

        $subcategoryName = strtolower(
            trim($subcategory['subcategory_name'])
        );

        if ($subcategoryName === 'clubs') {
            return false;
        }

        if (
            !$isSportsClub &&
            $subcategoryName === 'sports events'
        ) {
            return false;
        }

        return true;
    })
);


// Faculties
$facultiesStmt = $pdo->query(
    "SELECT faculty_id, faculty_name
     FROM faculties
     ORDER BY faculty_name"
);

$faculties = $facultiesStmt->fetchAll(PDO::FETCH_ASSOC);


// Sports assigned to this club
$sportsStmt = $pdo->prepare(
    "SELECT s.sport_id, s.sport_name
     FROM club_sports cs
     INNER JOIN sports s
        ON s.sport_id = cs.sport_id
     WHERE cs.club_id = :club_id
       AND s.is_active = 1
     ORDER BY s.sport_name"
);

$sportsStmt->execute([
    'club_id' => $clubId
]);

$clubSports = $sportsStmt->fetchAll(PDO::FETCH_ASSOC);


// ------------------------------------
// 3. GET SELECTED POST
// ------------------------------------

$postStmt = $pdo->prepare(
    "SELECT
        p.*,
        c.category_name,
        sc.subcategory_name
     FROM posts p
     INNER JOIN categories c
        ON c.category_id = p.category_id
     LEFT JOIN subcategories sc
        ON sc.subcategory_id = p.subcategory_id
       AND sc.category_id = p.category_id
     WHERE p.post_id = :post_id
       AND p.club_id = :club_id
     LIMIT 1"
);

$postStmt->execute([
    'post_id' => $postId,
    'club_id' => $clubId
]);

$post = $postStmt->fetch(PDO::FETCH_ASSOC);

if (!$post) {
    http_response_code(404);
}


// ------------------------------------
// 4. GET EXISTING EVENT DETAILS
// ------------------------------------

$event = null;

if ($post) {

    $eventStmt = $pdo->prepare(
        "SELECT *
         FROM events
         WHERE post_id = :post_id
         LIMIT 1"
    );

    $eventStmt->execute([
        'post_id' => $postId
    ]);

    $event = $eventStmt->fetch(PDO::FETCH_ASSOC);
}


// ------------------------------------
// 5. PREPARE EXISTING EVENT DATETIME
// ------------------------------------

$eventStartValue = '';
$eventEndValue = '';

if ($event) {

    if (!empty($event['start_at'])) {

        $eventStartValue = date(
            'Y-m-d\TH:i',
            strtotime($event['start_at'])
        );

    } elseif (!empty($event['event_date']) && !empty($event['start_time'])) {

        $eventStartValue = date(
            'Y-m-d\TH:i',
            strtotime($event['event_date'] . ' ' . $event['start_time'])
        );
    }


    if (!empty($event['end_at'])) {

        $eventEndValue = date(
            'Y-m-d\TH:i',
            strtotime($event['end_at'])
        );

    } elseif (!empty($event['event_date']) && !empty($event['end_time'])) {

        $eventEndValue = date(
            'Y-m-d\TH:i',
            strtotime($event['event_date'] . ' ' . $event['end_time'])
        );
    }
}


// ------------------------------------
// 6. GET EXISTING ADDITIONAL IMAGES
// ------------------------------------

$additionalImages = [];

if ($post) {

    $imagesStmt = $pdo->prepare(
        "SELECT image_id, image_path, alt_text
         FROM post_images
         WHERE post_id = :post_id
         ORDER BY display_order, image_id"
    );

    $imagesStmt->execute([
        'post_id' => $postId
    ]);

    $additionalImages = $imagesStmt->fetchAll(PDO::FETCH_ASSOC);
}


// ------------------------------------
// 7. GET SELECTED FACULTIES
// ------------------------------------

$selectedFacultyIds = [];

if ($post) {

    $facultyStmt = $pdo->prepare(
        "SELECT faculty_id
         FROM post_faculties
         WHERE post_id = :post_id"
    );

    $facultyStmt->execute([
        'post_id' => $postId
    ]);

    $selectedFacultyIds = array_map(
        'intval',
        $facultyStmt->fetchAll(PDO::FETCH_COLUMN)
    );
}


$errors = [];


// ------------------------------------
// 8. HELPER: IMAGE URL
// ------------------------------------

function edit_post_image_url($path)
{
    if (empty($path)) {
        return '';
    }

    return '/NSBM_360/' . ltrim($path, '/');
}


// ------------------------------------
// 9. PROCESS FORM SUBMISSION
// ------------------------------------

if ($post && $_SERVER['REQUEST_METHOD'] === 'POST') {

    $title = trim($_POST['title'] ?? '');
    $summary = trim($_POST['summary'] ?? '');
    $content = trim($_POST['content'] ?? '');

    $categoryId = filter_var(
        $_POST['category_id'] ?? '',
        FILTER_VALIDATE_INT
    );

    $subcategoryId = filter_var(
        $_POST['subcategory_id'] ?? '',
        FILTER_VALIDATE_INT
    );

    $sportId = filter_var(
        $_POST['sport_id'] ?? '',
        FILTER_VALIDATE_INT
    );

    $status = $_POST['status'] ?? 'draft';


    // Event fields
    $eventStartAt = trim($_POST['event_start_at'] ?? '');
    $eventEndAt = trim($_POST['event_end_at'] ?? '');
    $venue = trim($_POST['venue'] ?? '');

    $registrationDeadline = trim(
        $_POST['registration_deadline'] ?? ''
    );

    $registrationLink = trim(
        $_POST['registration_link'] ?? ''
    );


    $eventStartValue = $eventStartAt;
    $eventEndValue = $eventEndAt;


    // Faculty IDs
    $facultyIds = $_POST['faculty_ids'] ?? [];

    if (!is_array($facultyIds)) {
        $facultyIds = [];
    }

    $facultyIds = array_unique(
        array_filter(
            array_map('intval', $facultyIds),
            fn($id) => $id > 0
        )
    );


    // ------------------------------------
    // 10. VALIDATION
    // ------------------------------------

    if (!csrf_is_valid()) {
        $errors[] = 'The form session expired. Refresh and try again.';
    }


    if ($title === '' || text_length($title) > 255) {
        $errors[] = 'Enter a title with no more than 255 characters.';
    }


    if (text_length($summary) > 500) {
        $errors[] = 'The summary must contain 500 characters or fewer.';
    }


    if ($content === '') {
        $errors[] = 'Enter the post content.';
    }


    // ------------------------------------
    // CATEGORY VALIDATION
    // ------------------------------------

    $categoryName = '';

    if ($categoryId === false || $categoryId < 1) {

        $errors[] = 'Choose a category.';

    } else {

        $categoryCheck = $pdo->prepare(
            "SELECT category_name
             FROM categories
             WHERE category_id = :category_id
               AND parent_category_id IS NULL
             LIMIT 1"
        );

        $categoryCheck->execute([
            'category_id' => $categoryId
        ]);

        $categoryName = $categoryCheck->fetchColumn();

        if (!$categoryName) {

            $errors[] = 'Choose a valid main category.';
            $categoryName = '';

        } else {

            if (!in_array(
                strtolower(trim($categoryName)),
                $allowedCategoryNames,
                true
            )) {
                $errors[] = 'This category is not available for your club.';
                $categoryName = '';
            }
        }
    }


    // ------------------------------------
    // SUBCATEGORY VALIDATION
    // ------------------------------------

    $subcategoryName = '';

    if ($subcategoryId === false || $subcategoryId < 1) {

        $errors[] = 'Choose a subcategory.';

    } else {

        $subcategoryCheck = $pdo->prepare(
            "SELECT subcategory_name
             FROM subcategories
             WHERE subcategory_id = :subcategory_id
               AND category_id = :category_id
               AND LOWER(TRIM(subcategory_name)) <> 'clubs'
             LIMIT 1"
        );

        $subcategoryCheck->execute([
            'subcategory_id' => $subcategoryId,
            'category_id' => $categoryId
        ]);

        $subcategoryName = $subcategoryCheck->fetchColumn();

        if (!$subcategoryName) {

            $errors[] = 'The selected subcategory does not belong to this category or is not available.';

        } elseif (
            !$isSportsClub &&
            strtolower(trim($subcategoryName)) === 'sports events'
        ) {

            $errors[] = 'Sports Events are only available for Sports Clubs.';
            $subcategoryName = '';
        }
    }


    // ------------------------------------
    // STATUS VALIDATION
    // ------------------------------------

    if (!in_array($status, ['draft', 'published', 'archived'], true)) {
        $errors[] = 'Choose a valid post status.';
    }


    // ------------------------------------
    // CATEGORY TYPE CHECKS
    // ------------------------------------

    $isSportsCategory = (
        strtolower(trim($categoryName)) === 'sports'
    );

    $isEventCategory = (
        strtolower(trim($categoryName)) === 'events'
    );

    $isSportsEvent = (
        $isEventCategory &&
        strtolower(trim($subcategoryName)) === 'sports events'
    );

    $requiresSport = (
        $isSportsCategory || $isSportsEvent
    );


    // ------------------------------------
    // SPORTS VALIDATION
    // ------------------------------------

    if ($requiresSport) {

        if (!$isSportsClub) {
            $errors[] = 'Only sports clubs can publish Sports posts or Sports Events.';
        }

        if ($sportId === false || $sportId < 1) {

            $errors[] = 'Choose a sport for this post.';

        } else {

            $validSportIds = array_map(
                'intval',
                array_column($clubSports, 'sport_id')
            );

            if (!in_array($sportId, $validSportIds, true)) {
                $errors[] = 'The selected sport is not assigned to your club.';
            }
        }

    } else {

        $sportId = null;
    }


    // ------------------------------------
    // EVENT VALIDATION
    // ------------------------------------

    if ($isEventCategory) {

        $startObject = null;
        $endObject = null;

        if (
            $eventStartAt === '' ||
            $eventEndAt === '' ||
            $venue === ''
        ) {
            $errors[] = 'Enter the event start date, end date and venue.';

        } else {

            $startObject = DateTime::createFromFormat(
                '!Y-m-d\TH:i',
                $eventStartAt
            );

            $endObject = DateTime::createFromFormat(
                '!Y-m-d\TH:i',
                $eventEndAt
            );

            if (
                !$startObject ||
                $startObject->format('Y-m-d\TH:i') !== $eventStartAt
            ) {
                $errors[] = 'Enter a valid event start date and time.';
            }

            if (
                !$endObject ||
                $endObject->format('Y-m-d\TH:i') !== $eventEndAt
            ) {
                $errors[] = 'Enter a valid event end date and time.';
            }

            if (
                $startObject &&
                $endObject &&
                $endObject <= $startObject
            ) {
                $errors[] = 'The event end date and time must be later than the start.';
            }
        }


        if ($registrationDeadline !== '') {

            $deadlineObject = DateTime::createFromFormat(
                '!Y-m-d\TH:i',
                $registrationDeadline
            );

            if (
                !$deadlineObject ||
                $deadlineObject->format('Y-m-d\TH:i') !== $registrationDeadline
            ) {
                $errors[] = 'Enter a valid registration deadline.';

            } elseif (
                $startObject &&
                $deadlineObject > $startObject
            ) {
                $errors[] = 'The registration deadline cannot be after the event starts.';
            }
        }


        if ($registrationLink !== '') {

            if (
                !filter_var($registrationLink, FILTER_VALIDATE_URL) ||
                !in_array(
                    strtolower((string) parse_url($registrationLink, PHP_URL_SCHEME)),
                    ['http', 'https'],
                    true
                )
            ) {
                $errors[] = 'Enter a valid registration URL starting with http:// or https://.';
            }
        }
    }


    // ------------------------------------
    // VALIDATE FACULTY IDS
    // ------------------------------------

    if (!empty($facultyIds)) {

        $placeholders = implode(
            ',',
            array_fill(0, count($facultyIds), '?')
        );

        $facultyCheck = $pdo->prepare(
            "SELECT COUNT(*)
             FROM faculties
             WHERE faculty_id IN ($placeholders)"
        );

        $facultyCheck->execute(array_values($facultyIds));

        if (
            (int) $facultyCheck->fetchColumn() !== count($facultyIds)
        ) {
            $errors[] = 'One or more selected faculties are invalid.';
        }
    }


    // ------------------------------------
    // VALIDATE IMAGE REMOVALS
    // AND MAXIMUM TOTAL IMAGE COUNT
    // ------------------------------------

    $removeImageIds = $_POST['remove_image_ids'] ?? [];

    if (!is_array($removeImageIds)) {
        $removeImageIds = [];
    }

    $removeImageIds = array_unique(
        array_filter(
            array_map('intval', $removeImageIds),
            fn($id) => $id > 0
        )
    );


    // Get existing image IDs for this post.
    $existingImagesStmt = $pdo->prepare(
        "SELECT image_id
         FROM post_images
         WHERE post_id = :post_id"
    );

    $existingImagesStmt->execute([
        'post_id' => $postId
    ]);

    $existingImageIds = array_map(
        'intval',
        $existingImagesStmt->fetchAll(PDO::FETCH_COLUMN)
    );


    // Ensure selected removal IDs belong to this post.
    foreach ($removeImageIds as $removeId) {

        if (!in_array($removeId, $existingImageIds, true)) {
            $errors[] = 'One or more selected images are invalid.';
            break;
        }
    }


    // Count existing images that will remain.
    $remainingExistingCount = count(
        array_diff($existingImageIds, $removeImageIds)
    );


    // Count newly selected files.
    $newAdditionalImageCount = 0;

    if (
        isset($_FILES['additional_images']) &&
        is_array($_FILES['additional_images']['error'])
    ) {

        foreach ($_FILES['additional_images']['error'] as $fileError) {

            if ($fileError !== UPLOAD_ERR_NO_FILE) {
                $newAdditionalImageCount++;
            }
        }
    }


    // Maximum 5 images total.
    if (
        $remainingExistingCount + $newAdditionalImageCount > 5
    ) {
        $errors[] =
            'You can keep or upload a maximum of 5 additional images total. Remove existing images before adding more.';
    }


    // ------------------------------------
    // 11. UPDATE DATABASE
    // ------------------------------------

    if (empty($errors)) {

        $newImagePath = null;
        $newAdditionalImagePaths = [];

        $oldAdditionalImagePathsToDelete = [];

        try {

            // --------------------------------
            // UPLOAD NEW COVER IMAGE
            // --------------------------------

            if (
                isset($_FILES['cover_image']) &&
                $_FILES['cover_image']['error'] !== UPLOAD_ERR_NO_FILE
            ) {

                $newImagePath = save_post_image($_FILES['cover_image']);

                if (!$newImagePath) {
                    throw new RuntimeException('The cover image could not be uploaded.');
                }
            }

            $imageToSave = $newImagePath ?? $post['cover_image'];


            // --------------------------------
            // UPLOAD ADDITIONAL IMAGES
            // --------------------------------

            if (
                isset($_FILES['additional_images']) &&
                is_array($_FILES['additional_images']['name'])
            ) {

                $additionalFiles = $_FILES['additional_images'];

                foreach ($additionalFiles['error'] as $index => $fileError) {

                    if ($fileError === UPLOAD_ERR_NO_FILE) {
                        continue;
                    }

                    if ($fileError !== UPLOAD_ERR_OK) {
                        throw new RuntimeException(
                            'One of the additional images could not be uploaded.'
                        );
                    }

                    $singleFile = [
                        'name' => $additionalFiles['name'][$index],
                        'type' => $additionalFiles['type'][$index],
                        'tmp_name' => $additionalFiles['tmp_name'][$index],
                        'error' => $additionalFiles['error'][$index],
                        'size' => $additionalFiles['size'][$index]
                    ];

                    $uploadedPath = save_post_image($singleFile);

                    if (!$uploadedPath) {
                        throw new RuntimeException(
                            'One of the additional images could not be uploaded.'
                        );
                    }

                    $newAdditionalImagePaths[] = $uploadedPath;
                }
            }


            // --------------------------------
            // PUBLISHED DATE
            // --------------------------------

            $publishedAt = $post['published_at'];

            if ($status === 'published' && !$publishedAt) {

                $publishedAt = date('Y-m-d H:i:s');

            } elseif ($status === 'draft') {

                $publishedAt = null;
            }


            // --------------------------------
            // BEGIN TRANSACTION
            // --------------------------------

            $pdo->beginTransaction();


            // --------------------------------
            // UPDATE POSTS TABLE
            // --------------------------------

            $updatePost = $pdo->prepare(
                "UPDATE posts
                 SET category_id = :category_id,
                     subcategory_id = :subcategory_id,
                     sport_id = :sport_id,
                     title = :title,
                     summary = :summary,
                     content = :content,
                     cover_image = :cover_image,
                     status = :status,
                     published_at = :published_at
                 WHERE post_id = :post_id
                   AND club_id = :club_id"
            );

            $updatePost->execute([
                'category_id' => $categoryId,
                'subcategory_id' => $subcategoryId,
                'sport_id' => $sportId,
                'title' => $title,
                'summary' => $summary !== '' ? $summary : null,
                'content' => $content,
                'cover_image' => $imageToSave,
                'status' => $status,
                'published_at' => $publishedAt,
                'post_id' => $postId,
                'club_id' => $clubId
            ]);


            // --------------------------------
            // UPDATE EVENTS TABLE
            // --------------------------------

            if ($isEventCategory) {

                $startDateTime = str_replace(
                    'T',
                    ' ',
                    $eventStartAt
                ) . ':00';

                $endDateTime = str_replace(
                    'T',
                    ' ',
                    $eventEndAt
                ) . ':00';


                $eventDate = substr($eventStartAt, 0, 10);

                $startTime = substr($eventStartAt, 11, 5) . ':00';

                $endTime = substr($eventEndAt, 11, 5) . ':00';


                $deadlineToSave = null;

                if ($registrationDeadline !== '') {

                    $deadlineToSave = str_replace(
                        'T',
                        ' ',
                        $registrationDeadline
                    ) . ':00';
                }


                if ($event) {

                    $updateEvent = $pdo->prepare(
                        "UPDATE events
                         SET event_date = :event_date,
                             start_time = :start_time,
                             end_time = :end_time,
                             start_at = :start_at,
                             end_at = :end_at,
                             venue = :venue,
                             registration_deadline = :registration_deadline,
                             registration_link = :registration_link
                         WHERE post_id = :post_id"
                    );

                    $updateEvent->execute([
                        'event_date' => $eventDate,
                        'start_time' => $startTime,
                        'end_time' => $endTime,
                        'start_at' => $startDateTime,
                        'end_at' => $endDateTime,
                        'venue' => $venue,
                        'registration_deadline' => $deadlineToSave,
                        'registration_link' => $registrationLink !== ''
                            ? $registrationLink
                            : null,
                        'post_id' => $postId
                    ]);

                } else {

                    $insertEvent = $pdo->prepare(
                        "INSERT INTO events (
                            post_id,
                            event_date,
                            start_time,
                            end_time,
                            start_at,
                            end_at,
                            venue,
                            registration_deadline,
                            registration_link
                        ) VALUES (
                            :post_id,
                            :event_date,
                            :start_time,
                            :end_time,
                            :start_at,
                            :end_at,
                            :venue,
                            :registration_deadline,
                            :registration_link
                        )"
                    );

                    $insertEvent->execute([
                        'post_id' => $postId,
                        'event_date' => $eventDate,
                        'start_time' => $startTime,
                        'end_time' => $endTime,
                        'start_at' => $startDateTime,
                        'end_at' => $endDateTime,
                        'venue' => $venue,
                        'registration_deadline' => $deadlineToSave,
                        'registration_link' => $registrationLink !== ''
                            ? $registrationLink
                            : null
                    ]);
                }

            } else {

                if ($event) {

                    $deleteEvent = $pdo->prepare(
                        "DELETE FROM events
                         WHERE post_id = :post_id"
                    );

                    $deleteEvent->execute([
                        'post_id' => $postId
                    ]);
                }
            }


            // --------------------------------
            // UPDATE POST FACULTIES
            // --------------------------------

            $deleteFaculties = $pdo->prepare(
                "DELETE FROM post_faculties
                 WHERE post_id = :post_id"
            );

            $deleteFaculties->execute([
                'post_id' => $postId
            ]);


            if (!empty($facultyIds)) {

                $insertFaculty = $pdo->prepare(
                    "INSERT INTO post_faculties (
                        post_id,
                        faculty_id
                    ) VALUES (
                        :post_id,
                        :faculty_id
                    )"
                );

                foreach ($facultyIds as $facultyId) {

                    $insertFaculty->execute([
                        'post_id' => $postId,
                        'faculty_id' => $facultyId
                    ]);
                }
            }


            // --------------------------------
            // REMOVE SELECTED ADDITIONAL IMAGES
            // --------------------------------

            if (!empty($removeImageIds)) {

                $placeholders = implode(
                    ',',
                    array_fill(0, count($removeImageIds), '?')
                );

                $removeStmt = $pdo->prepare(
                    "SELECT image_id, image_path
                     FROM post_images
                     WHERE post_id = ?
                       AND image_id IN ($placeholders)"
                );

                $removeStmt->execute(
                    array_merge(
                        [$postId],
                        array_values($removeImageIds)
                    )
                );

                $imagesToRemove = $removeStmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($imagesToRemove as $image) {
                    $oldAdditionalImagePathsToDelete[] = $image['image_path'];
                }


                $deleteImages = $pdo->prepare(
                    "DELETE FROM post_images
                     WHERE post_id = ?
                       AND image_id IN ($placeholders)"
                );

                $deleteImages->execute(
                    array_merge(
                        [$postId],
                        array_values($removeImageIds)
                    )
                );
            }


            // --------------------------------
            // INSERT NEW ADDITIONAL IMAGES
            // --------------------------------

            if (!empty($newAdditionalImagePaths)) {

                $nextOrderStmt = $pdo->prepare(
                    "SELECT COALESCE(MAX(display_order), 0)
                     FROM post_images
                     WHERE post_id = :post_id"
                );

                $nextOrderStmt->execute([
                    'post_id' => $postId
                ]);

                $displayOrder = (int) $nextOrderStmt->fetchColumn();


                $insertImage = $pdo->prepare(
                    "INSERT INTO post_images (
                        post_id,
                        image_path,
                        alt_text,
                        display_order
                    ) VALUES (
                        :post_id,
                        :image_path,
                        :alt_text,
                        :display_order
                    )"
                );

                foreach ($newAdditionalImagePaths as $imagePath) {

                    $displayOrder++;

                    $insertImage->execute([
                        'post_id' => $postId,
                        'image_path' => $imagePath,
                        'alt_text' => $title,
                        'display_order' => $displayOrder
                    ]);
                }
            }


            // --------------------------------
            // COMMIT DATABASE CHANGES
            // --------------------------------

            $pdo->commit();


            // --------------------------------
            // DELETE OLD FILES AFTER COMMIT
            // --------------------------------

            if ($newImagePath && !empty($post['cover_image'])) {
                delete_post_image($post['cover_image']);
            }

            foreach ($oldAdditionalImagePathsToDelete as $oldPath) {
                delete_post_image($oldPath);
            }


            header('Location: manage-posts.php?updated=1');
            exit;


        } catch (Throwable $exception) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }


            if ($newImagePath) {
                delete_post_image($newImagePath);
            }

            foreach ($newAdditionalImagePaths as $newPath) {
                delete_post_image($newPath);
            }


            if ($exception instanceof RuntimeException) {

                $errors[] = $exception->getMessage();

            } else {

                error_log(
                    'NEWS 360 edit post error: ' . $exception->getMessage()
                );

                $errors[] = 'The post could not be updated. Check the database and try again.';
            }
        }
    }


    // ------------------------------------
    // PRESERVE FORM VALUES
    // ------------------------------------

    $post['title'] = $title;
    $post['summary'] = $summary;
    $post['content'] = $content;
    $post['category_id'] = $categoryId;
    $post['subcategory_id'] = $subcategoryId;
    $post['sport_id'] = $sportId;
    $post['status'] = $status;

    if (isset($newImagePath) && $newImagePath) {
        $post['cover_image'] = $newImagePath;
    }


    $selectedFacultyIds = array_values($facultyIds);


    $event = [
        'event_date' => $eventStartAt !== ''
            ? substr($eventStartAt, 0, 10)
            : '',
        'start_time' => $eventStartAt !== ''
            ? substr($eventStartAt, 11, 5)
            : '',
        'end_time' => $eventEndAt !== ''
            ? substr($eventEndAt, 11, 5)
            : '',
        'start_at' => $eventStartAt,
        'end_at' => $eventEndAt,
        'venue' => $venue,
        'registration_deadline' => $registrationDeadline,
        'registration_link' => $registrationLink
    ];
}


// ------------------------------------
// 12. PAGE SETTINGS
// ------------------------------------

$pageTitle = 'Edit Post | NEWS 360';
$pageCSS = '/NSBM_360/assets/css/president.css';
$isPresidentPage = true;

require_once __DIR__ . '/president_header.php';

?>

<div class="page-shell">

    <?php require __DIR__ . '/president_sidebar.php'; ?>

    <main class="main-content">

        <div class="page-header">

            <div>
                <p class="eyebrow">PRESIDENT WORKSPACE</p>

                <h1>Edit Post</h1>

                <p class="page-subtitle">
                    Update a post belonging to <?= e($clubName) ?>.
                </p>
            </div>

            <a class="button button-secondary" href="manage-posts.php">
                ← Back to My Posts
            </a>

        </div>


        <?php if (!$post): ?>

            <section class="content-card empty-state">

                <h2>Post not found</h2>

                <p>
                    This post does not exist or does not belong to your club.
                </p>

                <a class="button button-primary" href="manage-posts.php">
                    Return to Manage My Posts
                </a>

            </section>

        <?php else: ?>


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
                        <h2>Post Information</h2>

                        <p>
                            Post ID: <?= (int) $post['post_id'] ?>
                        </p>
                    </div>

                    <span class="status-badge <?= e(post_status_class($post['status'])) ?>">
                        <?= e(ucfirst($post['status'])) ?>
                    </span>

                </div>


                <form
                    method="POST"
                    action="edit-post.php?id=<?= (int) $postId ?>"
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
                            value="<?= e($post['title']) ?>"
                        >

                    </div>


                    <!-- CATEGORY AND SUBCATEGORY -->

                    <div class="form-grid">

                        <div class="form-group">

                            <label for="category_id">Category *</label>

                            <select
                                id="category_id"
                                name="category_id"
                                data-club-type="<?= e($clubType) ?>"
                                required
                            >

                                <option value="">Choose a category</option>

                                <?php foreach ($categories as $category): ?>

                                    <option
                                        value="<?= (int) $category['category_id'] ?>"
                                        <?= (string) $post['category_id'] === (string) $category['category_id'] ? 'selected' : '' ?>
                                    >
                                        <?= e($category['category_name']) ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <div class="form-group">

                            <label for="subcategory_id">Subcategory *</label>

                            <select
                                id="subcategory_id"
                                name="subcategory_id"
                                required
                            >

                                <option value="">Choose a subcategory</option>

                                <?php foreach ($subcategories as $subcategory): ?>

                                    <option
                                        value="<?= (int) $subcategory['subcategory_id'] ?>"
                                        data-category="<?= (int) $subcategory['category_id'] ?>"
                                        data-name="<?= e($subcategory['subcategory_name']) ?>"
                                        <?= (string) $post['subcategory_id'] === (string) $subcategory['subcategory_id'] ? 'selected' : '' ?>
                                    >
                                        <?= e($subcategory['subcategory_name']) ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                    </div>


                    <!-- STATUS -->

                    <div class="form-group">

                        <label for="status">Post Status *</label>

                        <select id="status" name="status" required>

                            <option value="draft" <?= $post['status'] === 'draft' ? 'selected' : '' ?>>
                                Draft
                            </option>

                            <option value="published" <?= $post['status'] === 'published' ? 'selected' : '' ?>>
                                Published
                            </option>

                            
                        </select>

                    </div>


                    <!-- SPORTS SECTION -->

                    <div
                        class="form-group"
                        id="sportsSection"
                        style="display:none;"
                    >

                        <label for="sport_id">Select Sport *</label>

                        <select id="sport_id" name="sport_id">

                            <option value="">Choose a sport</option>

                            <?php foreach ($clubSports as $sport): ?>

                                <option
                                    value="<?= (int) $sport['sport_id'] ?>"
                                    <?= (string) ($post['sport_id'] ?? '') === (string) $sport['sport_id'] ? 'selected' : '' ?>
                                >
                                    <?= e($sport['sport_name']) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                        <?php if (empty($clubSports)): ?>

                            <small>
                                No active sports have been assigned to this club.
                                Contact the administrator to assign a sport.
                            </small>

                        <?php endif; ?>

                    </div>


                    <!-- EVENT SECTION -->

                    <div
                        class="event-details-section"
                        id="eventSection"
                        style="display:none;"
                    >

                        <h3>Event Information</h3>

                        <p>
                            Add or update the details of this event.
                        </p>


                        <div class="form-grid">

                            <div class="form-group">

                                <label for="event_start_at">
                                    Event Start Date & Time *
                                </label>

                                <input
                                    type="datetime-local"
                                    id="event_start_at"
                                    name="event_start_at"
                                    value="<?= e($eventStartValue) ?>"
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
                                    value="<?= e($eventEndValue) ?>"
                                >

                            </div>


                            <div class="form-group">

                                <label for="venue">Venue *</label>

                                <input
                                    type="text"
                                    id="venue"
                                    name="venue"
                                    maxlength="255"
                                    value="<?= e($event['venue'] ?? '') ?>"
                                    placeholder="Enter event venue"
                                >

                            </div>

                        </div>


                        <div class="form-group">

                            <label for="registration_deadline">
                                Registration Deadline
                            </label>

                            <input
                                type="datetime-local"
                                id="registration_deadline"
                                name="registration_deadline"
                                value="<?php

                                    $deadlineValue = $event['registration_deadline'] ?? '';

                                    if ($deadlineValue !== '') {
                                        echo e(date(
                                            'Y-m-d\TH:i',
                                            strtotime($deadlineValue)
                                        ));
                                    }

                                ?>"
                            >

                        </div>


                        <div class="form-group">

                            <label for="registration_link">
                                Registration Link
                            </label>

                            <input
                                type="url"
                                id="registration_link"
                                name="registration_link"
                                value="<?= e($event['registration_link'] ?? '') ?>"
                                placeholder="https://example.com/register"
                            >

                        </div>

                    </div>


                    <!-- FACULTIES -->

                    <div class="form-group faculty-section">

                        <label class="faculty-heading">Relevant Faculties</label>

                        <small class="faculty-description">
                            Select the faculties that this post is relevant to.
                            You can select more than one.
                        </small>

                        <div class="faculty-checkbox-grid">

                            <?php foreach ($faculties as $faculty): ?>

                                <label class="faculty-checkbox">

                                    <input
                                        type="checkbox"
                                        name="faculty_ids[]"
                                        value="<?= (int) $faculty['faculty_id'] ?>"
                                        <?= in_array(
                                            (int) $faculty['faculty_id'],
                                            $selectedFacultyIds,
                                            true
                                        ) ? 'checked' : '' ?>
                                    >

                                    <?= e($faculty['faculty_name']) ?>

                                </label>

                            <?php endforeach; ?>

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
                        ><?= e($post['summary'] ?? '') ?></textarea>

                    </div>


                    <!-- CONTENT -->

                    <div class="form-group">

                        <label for="content">Post Content *</label>

                        <textarea
                            id="content"
                            name="content"
                            rows="9"
                            required
                        ><?= e($post['content']) ?></textarea>

                    </div>


                    <!-- CURRENT COVER IMAGE -->

                    <?php if (!empty($post['cover_image'])): ?>

                        <div class="form-group">

                            <label>Current Cover Image</label>

                            <img
                                class="current-cover-image"
                                src="<?= e(edit_post_image_url($post['cover_image'])) ?>"
                                alt="Current post cover"
                            >

                        </div>

                    <?php endif; ?>


                    <!-- REPLACE COVER IMAGE -->

                    <div class="form-group">

                        <label for="cover_image">Replace Cover Image</label>

                        <input
                            type="file"
                            id="cover_image"
                            name="cover_image"
                            accept="image/jpeg,image/png,image/webp"
                        >

                        <small>
                            Optional. Leave empty to keep the current image.
                            JPG, PNG or WEBP up to 5 MB.
                        </small>

                    </div>


                    <!-- EXISTING ADDITIONAL IMAGES -->

                    <?php if (!empty($additionalImages)): ?>

                        <div class="form-group">

                            <label>Current Additional Images</label>

                            <small>
                                Select the images you want to remove.
                            </small>

                            <div class="post-image-gallery">

                                <?php foreach ($additionalImages as $image): ?>

                                    <label class="edit-gallery-item">

                                        <img
                                            src="<?= e(edit_post_image_url($image['image_path'])) ?>"
                                            alt="<?= e($image['alt_text'] ?? 'Post image') ?>"
                                        >

                                        <span>
                                            <input
                                                type="checkbox"
                                                name="remove_image_ids[]"
                                                value="<?= (int) $image['image_id'] ?>"
                                            >

                                            Remove image
                                        </span>

                                    </label>

                                <?php endforeach; ?>

                            </div>

                        </div>

                    <?php endif; ?>


                    <!-- ADD NEW ADDITIONAL IMAGES -->

                    <div class="form-group">

                        <label for="additional_images">
                            Add More Images
                        </label>

                        <input
                            type="file"
                            id="additional_images"
                            name="additional_images[]"
                            accept="image/jpeg,image/png,image/webp"
                            multiple
                        >

                        <small>
                            Optional. You can keep or upload a maximum of
                            5 additional images total. JPG, PNG or WEBP,
                            up to 5 MB each.
                        </small>

                    </div>


                    <!-- ACTIONS -->

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
                        >
                            Save Changes
                        </button>

                    </div>

                </form>

            </section>

        <?php endif; ?>

    </main>

</div>


<script src="/NSBM_360/assets/js/president-edit-post.js" defer></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

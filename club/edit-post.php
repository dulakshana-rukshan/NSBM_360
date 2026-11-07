
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
        'Open Manage My Posts and select the Edit post link beside the post you want to change.'
    );

    exit;
}


// ------------------------------------
// 2. GET CATEGORIES
// ------------------------------------

$categoriesStmt = $pdo->query(
    'SELECT category_id, category_name
     FROM categories
     ORDER BY category_name'
);

$categories = $categoriesStmt->fetchAll();


// ------------------------------------
// 3. GET SELECTED CLUB POST
// ------------------------------------

$postStmt = $pdo->prepare(
    "SELECT *
     FROM posts
     WHERE post_id = :post_id
       AND club_id = :club_id
     LIMIT 1"
);

$postStmt->execute([
    'post_id' => $postId,
    'club_id' => $clubId
]);

$post = $postStmt->fetch();


if (!$post) {
    http_response_code(404);
}

$errors = [];


// ------------------------------------
// 4. PROCESS FORM SUBMISSION
// ------------------------------------

if ($post && $_SERVER['REQUEST_METHOD'] === 'POST') {

    $title = trim($_POST['title'] ?? '');
    $summary = trim($_POST['summary'] ?? '');
    $content = trim($_POST['content'] ?? '');

    $categoryId = filter_var(
        $_POST['category_id'] ?? '',
        FILTER_VALIDATE_INT
    );

    $status = $_POST['status'] ?? 'draft';


    // Validate CSRF token
    if (!csrf_is_valid()) {
        $errors[] = 'The form session expired. Refresh the page and try again.';
    }


    // Validate title
    if ($title === '' || text_length($title) > 255) {
        $errors[] = 'Enter a title with no more than 255 characters.';
    }


    // Validate summary
    if (text_length($summary) > 500) {
        $errors[] = 'The summary must contain 500 characters or fewer.';
    }


    // Validate content
    if ($content === '') {
        $errors[] = 'Enter the post content.';
    }


    // Validate category
    if ($categoryId === false || $categoryId < 1) {

        $errors[] = 'Choose a category.';

    } else {

        $categoryCheck = $pdo->prepare(
            "SELECT COUNT(*)
             FROM categories
             WHERE category_id = :category_id"
        );

        $categoryCheck->execute([
            'category_id' => $categoryId
        ]);

        if ((int) $categoryCheck->fetchColumn() === 0) {
            $errors[] = 'The selected category does not exist.';
        }
    }


    // Validate status
    if (!in_array($status, ['draft', 'published', 'archived'], true)) {
        $errors[] = 'Choose a valid post status.';
    }


    // ------------------------------------
    // 5. UPDATE POST
    // ------------------------------------

    if (empty($errors)) {

        $newImagePath = null;

        try {

            // Upload a new image only if selected
            if (
                isset($_FILES['cover_image']) &&
                $_FILES['cover_image']['error'] !== UPLOAD_ERR_NO_FILE
            ) {
                $newImagePath = save_post_image($_FILES['cover_image']);
            }


            // Keep existing image if no new image uploaded
            $imageToSave = $newImagePath ?? $post['cover_image'];


            // Handle published date
            $publishedAt = $post['published_at'];

            if ($status === 'published' && !$publishedAt) {

                $publishedAt = date('Y-m-d H:i:s');

            } elseif ($status === 'draft') {

                $publishedAt = null;
            }


            // Update post
            $updateSql = "UPDATE posts
                          SET category_id = :category_id,
                              title = :title,
                              summary = :summary,
                              content = :content,
                              cover_image = :cover_image,
                              status = :status,
                              published_at = :published_at
                          WHERE post_id = :post_id
                            AND club_id = :club_id";

            $updateStmt = $pdo->prepare($updateSql);

            $updateStmt->execute([
                'category_id' => $categoryId,
                'title' => $title,
                'summary' => $summary !== '' ? $summary : null,
                'content' => $content,
                'cover_image' => $imageToSave,
                'status' => $status,
                'published_at' => $publishedAt,
                'post_id' => $postId,
                'club_id' => $clubId
            ]);


            // Delete old image only after successful update
            if ($newImagePath && !empty($post['cover_image'])) {
                delete_post_image($post['cover_image']);
            }


            // Redirect after successful update
            header('Location: manage-posts.php?updated=1');
            exit;


        } catch (RuntimeException $exception) {

            if ($newImagePath) {
                delete_post_image($newImagePath);
            }

            $errors[] = $exception->getMessage();


        } catch (PDOException $exception) {

            if ($newImagePath) {
                delete_post_image($newImagePath);
            }

            error_log(
                'NEWS 360 edit post error: ' . $exception->getMessage()
            );

            $errors[] = 'The post could not be updated. Check the database and try again.';
        }
    }


    // Preserve submitted values if validation fails
    $post['title'] = $title;
    $post['summary'] = $summary;
    $post['content'] = $content;
    $post['category_id'] = $categoryId;
    $post['status'] = $status;
}


// ------------------------------------
// 6. PAGE SETTINGS
// ------------------------------------

$pageTitle = 'Edit Post | NEWS 360';
$pageCSS = '/NSBM-360/NSBM_360/club/president.css';
$isPresidentPage = true;


// ------------------------------------
// 7. INCLUDE PRESIDENT HEADER
// ------------------------------------

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
                    The post ID is invalid, or this post does not
                    belong to your assigned club.
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


                    <div class="form-grid">

                        <div class="form-group">

                            <label for="category_id">Category *</label>

                            <select
                                id="category_id"
                                name="category_id"
                                required
                            >

                                <option value="">
                                    Choose a category
                                </option>

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

                            <label for="status">Post Status *</label>

                            <select id="status" name="status">

                                <option
                                    value="draft"
                                    <?= $post['status'] === 'draft' ? 'selected' : '' ?>
                                >
                                    Draft
                                </option>

                                <option
                                    value="published"
                                    <?= $post['status'] === 'published' ? 'selected' : '' ?>
                                >
                                    Published
                                </option>

                                <option
                                    value="archived"
                                    <?= $post['status'] === 'archived' ? 'selected' : '' ?>
                                >
                                    Archived
                                </option>

                            </select>

                        </div>

                    </div>


                    <div class="form-group">

                        <label for="summary">Short Summary</label>

                        <textarea
                            id="summary"
                            name="summary"
                            rows="3"
                            maxlength="500"
                        ><?= e($post['summary'] ?? '') ?></textarea>

                    </div>


                    <div class="form-group">

                        <label for="content">Post Content *</label>

                        <textarea
                            id="content"
                            name="content"
                            rows="9"
                            required
                        ><?= e($post['content']) ?></textarea>

                    </div>


                    <?php if (!empty($post['cover_image'])): ?>

                        <div class="form-group">

                            <label>Current Cover Image</label>

                            <img
                                class="current-cover-image"
                                src="<?= e($post['cover_image']) ?>"
                                alt="Current post cover"
                            >

                        </div>

                    <?php endif; ?>


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


<?php require_once __DIR__ . '/../includes/footer.php'; ?>

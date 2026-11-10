
<?php

require_once __DIR__ . '/president_auth.php';


// ------------------------------------
// 1. PAGE SETTINGS
// ------------------------------------

$pageTitle = 'Create New Post | NEWS 360';
$pageCSS = '/NSBM-360/NSBM_360/club/president.css';
$isPresidentPage = true;


// ------------------------------------
// 2. GET CATEGORIES
// ------------------------------------

$categoriesSql = "
    SELECT category_id, category_name
    FROM categories
    ORDER BY category_name
";

$categoriesStmt = $pdo->query($categoriesSql);
$categories = $categoriesStmt->fetchAll();


// ------------------------------------
// 3. FORM VARIABLES
// ------------------------------------

$errors = [];

$title = '';
$summary = '';
$content = '';
$categoryId = '';
$status = 'draft';


// ------------------------------------
// 4. PROCESS FORM
// ------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

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
    if (!in_array($status, ['draft', 'published'], true)) {
        $errors[] = 'Choose Draft or Published.';
    }


    // ------------------------------------
    // 5. INSERT POST
    // ------------------------------------

    if (empty($errors)) {

        $imagePath = null;

        try {

            // Upload image only if selected
            if (
                isset($_FILES['cover_image']) &&
                $_FILES['cover_image']['error'] !== UPLOAD_ERR_NO_FILE
            ) {
                $imagePath = save_post_image($_FILES['cover_image']);
            }


            $insertSql = "
                INSERT INTO posts
                (
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
                VALUES
                (
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

            $insertStmt = $pdo->prepare($insertSql);

            $insertStmt->execute([

                'created_by' => $userId,
                'club_id' => $clubId,
                'category_id' => $categoryId,
                'title' => $title,
                'summary' => $summary !== '' ? $summary : null,
                'content' => $content,
                'cover_image' => $imagePath,
                'status' => $status,

                'published_at' => $status === 'published'
                    ? date('Y-m-d H:i:s')
                    : null

            ]);


            // Redirect after successful saving
            header('Location: manage-posts.php?created=1');
            exit;


        } catch (RuntimeException $exception) {

            if ($imagePath !== null) {
                delete_post_image($imagePath);
            }

            $errors[] = $exception->getMessage();


        } catch (PDOException $exception) {

            if ($imagePath !== null) {
                delete_post_image($imagePath);
            }

            error_log(
                'NEWS 360 create post error: ' . $exception->getMessage()
            );

            $errors[] = 'The post could not be saved. Please check the database and try again.';
        }
    }
}


// ------------------------------------
// 6. INCLUDE PRESIDENT HEADER
// ------------------------------------

require_once __DIR__ . '/president_header.php';

?>


<div class="page-shell">

    <?php require __DIR__ . '/president_sidebar.php'; ?>


    <main class="main-content">


        <!-- PAGE HEADER -->

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
                Back to My Posts
            </a>

        </div>


        <!-- VALIDATION ERRORS -->

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


        <!-- CATEGORY WARNING -->

        <?php if (empty($categories)): ?>

            <div class="alert alert-warning">

                There are no categories available.
                Please ask the administrator to add categories.

            </div>

        <?php endif; ?>


        <!-- CREATE POST FORM -->

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


                <!-- CATEGORY AND STATUS -->

                <div class="form-grid">

                    <div class="form-group">

                        <label for="category_id">Category *</label>

                        <select
                            id="category_id"
                            name="category_id"
                            required
                            <?= empty($categories) ? 'disabled' : '' ?>
                        >

                            <option value="">
                                Choose a category
                            </option>

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


                <!-- FORM BUTTONS -->

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
                        <?= empty($categories) ? 'disabled' : '' ?>
                    >
                        Save Post
                    </button>

                </div>

            </form>

        </section>

    </main>

</div>


<?php require_once __DIR__ . '/../includes/footer.php'; ?>

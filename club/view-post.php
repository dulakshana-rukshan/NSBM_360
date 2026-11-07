
<?php

require_once __DIR__ . '/president_auth.php';
require_once __DIR__ . '/president_functions.php';

$pageTitle = 'View Post';
$isPresidentPage = true;

// Get post ID
$postId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$postId || $postId <= 0) {
    http_response_code(404);
    exit('Invalid post ID.');
}

// Get post details belonging to the logged-in president's club
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
        c.category_name
    FROM posts p
    LEFT JOIN categories c
        ON p.category_id = c.category_id
    WHERE p.post_id = :post_id
      AND p.club_id = :club_id
    LIMIT 1
";

$stmt = $pdo->prepare($query);

$stmt->execute([
    'post_id' => $postId,
    'club_id' => $clubId
]);

$post = $stmt->fetch();

// Check whether post exists
if (!$post) {
    http_response_code(404);
    exit('Post not found.');
}

require_once __DIR__ . '/president_header.php';

?>

<div class="page-shell">

    <?php require_once __DIR__ . '/president_sidebar.php'; ?>

    <main class="main-content">

        <!-- Page heading -->
        <div class="page-header">

            <div>
                <h1>View Post</h1>
                <p>View the complete details of your club post.</p>
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

        <!-- Post details -->
        <section class="content-card view-post-card">

            <div class="view-post-header">

                <div>
                    <span class="status-badge <?= e(post_status_class($post['status'])) ?>">
                        <?= e(ucfirst($post['status'])) ?>
                    </span>

                    <h2><?= e($post['title']) ?></h2>

                    <p class="view-post-category">
                        <i class="bi bi-folder"></i>
                        <?= e($post['category_name'] ?? 'Uncategorized') ?>
                    </p>
                </div>

            </div>

            <!-- Cover image -->
            <?php if (!empty($post['cover_image'])): ?>

                <div class="view-post-cover">

                    <img
                        src="<?= e($post['cover_image']) ?>"
                        alt="<?= e($post['title']) ?>"
                        class="view-post-image"
                    >

                </div>

            <?php endif; ?>

            <!-- Summary -->
            <?php if (!empty($post['summary'])): ?>

                <div class="view-post-section">

                    <h3>Summary</h3>

                    <p><?= nl2br(e($post['summary'])) ?></p>

                </div>

            <?php endif; ?>

            <!-- Content -->
            <div class="view-post-section">

                <h3>Post Content</h3>

                <div class="view-post-content">
                    <?= nl2br(e($post['content'])) ?>
                </div>

            </div>

            <!-- Post information -->
            <div class="view-post-information">

                <h3>Post Information</h3>

                <div class="view-post-info-grid">

                    <div>
                        <span>Post ID</span>
                        <strong>#<?= (int) $post['post_id'] ?></strong>
                    </div>

                    <div>
                        <span>Status</span>
                        <strong><?= e(ucfirst($post['status'])) ?></strong>
                    </div>

                    <div>
                        <span>Category</span>
                        <strong><?= e($post['category_name'] ?? '-') ?></strong>
                    </div>

                    <div>
                        <span>Date Created</span>
                        <strong><?= e(display_date($post['created_at'])) ?></strong>
                    </div>

                    <div>
                        <span>Last Updated</span>
                        <strong><?= e(display_date($post['updated_at'])) ?></strong>
                    </div>

                    <div>
                        <span>Published Date</span>
                        <strong>
                            <?= !empty($post['published_at'])
                                ? e(display_date($post['published_at']))
                                : 'Not published yet' ?>
                        </strong>
                    </div>

                </div>

            </div>

            <!-- Bottom actions -->
            <div class="view-post-bottom-actions">

                <a href="manage-posts.php" class="button button-clear">
                    Back to Posts
                </a>

                <a
                    href="edit-post.php?id=<?= (int) $post['post_id'] ?>"
                    class="button button-primary"
                >
                    <i class="bi bi-pencil-square"></i>
                    Edit Post
                </a>

                <!-- Delete Post -->
                <form
                    method="POST"
                    action="delete-post.php"
                    onsubmit="return confirm('Are you sure you want to permanently delete this post? This action cannot be undone.');"
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

        </section>

    </main>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<?php
require_once __DIR__ . '/president_auth.php';
require_once __DIR__ . '/president_functions.php';

$pageTitle = 'Manage Posts';
$isPresidentPage = true;

// Search and filter values
$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? 'all';

$allowedStatuses = ['all', 'published', 'draft', 'archived'];

if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = 'all';
}

// Get statistics
$statsQuery = "
    SELECT
        COUNT(*) AS total_posts,
        SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) AS published_posts,
        SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) AS draft_posts,
        SUM(CASE WHEN status = 'archived' THEN 1 ELSE 0 END) AS archived_posts
    FROM posts
    WHERE club_id = :club_id
";

$statsStmt = $pdo->prepare($statsQuery);
$statsStmt->execute(['club_id' => $clubId]);
$stats = $statsStmt->fetch();

// Get posts with search and status filter
$postsQuery = "
    SELECT
        p.post_id,
        p.title,
        p.status,
        p.created_at,
        c.category_name
    FROM posts p
    LEFT JOIN categories c
        ON p.category_id = c.category_id
    WHERE p.club_id = :club_id
";

$params = ['club_id' => $clubId];

if ($search !== '') {
    $postsQuery .= " AND p.title LIKE :search";
    $params['search'] = '%' . $search . '%';
}

if ($statusFilter !== 'all') {
    $postsQuery .= " AND p.status = :status";
    $params['status'] = $statusFilter;
}

$postsQuery .= " ORDER BY p.created_at DESC";

$postsStmt = $pdo->prepare($postsQuery);
$postsStmt->execute($params);
$posts = $postsStmt->fetchAll();

require_once __DIR__ . '/president_header.php';
?>

<div class="page-shell">

    <?php require_once __DIR__ . '/president_sidebar.php'; ?>

    <main class="main-content">

        <!-- Page heading -->
        <div class="page-header">

            <div>
                <h1>Manage Posts</h1>
                <p>View and manage posts created by your club.</p>
            </div>

            <div class="page-header-actions">

                <a href="create-post.php" class="button button-primary">
                    <i class="bi bi-plus-circle"></i>
                    Create Post
                </a>

                <a href="create-event.php" class="button button-event">
                    <i class="bi bi-calendar-plus"></i>
                    Create Event
                </a>

            </div>

        </div>

        <!-- Statistics -->
        <section class="stats-grid">

            <div class="stat-card">
                <div class="stat-icon total-icon">
                    <i class="bi bi-file-earmark-text"></i>
                </div>
                <div>
                    <p>Total Posts</p>
                    <h2><?= (int) ($stats['total_posts'] ?? 0) ?></h2>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon published-icon">
                    <i class="bi bi-check-circle"></i>
                </div>
                <div>
                    <p>Published</p>
                    <h2><?= (int) ($stats['published_posts'] ?? 0) ?></h2>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon draft-icon">
                    <i class="bi bi-pencil-square"></i>
                </div>
                <div>
                    <p>Drafts</p>
                    <h2><?= (int) ($stats['draft_posts'] ?? 0) ?></h2>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon archived-icon">
                    <i class="bi bi-archive"></i>
                </div>
                <div>
                    <p>Archived</p>
                    <h2><?= (int) ($stats['archived_posts'] ?? 0) ?></h2>
                </div>
            </div>

        </section>

        <!-- Posts table -->
        <section class="content-card manage-posts-card">

            <div class="section-heading">
                <div>
                    <h2>All Club Posts</h2>
                    <p>Search and filter your club posts.</p>
                </div>
            </div>

            <!-- Search and filter -->
            <form method="GET" action="manage-posts.php" class="filter-bar">

                <div class="filter-search">
                    <label for="search">Search by title</label>

                    <div class="search-input-wrapper">
                        <i class="bi bi-search"></i>

                        <input
                            type="text"
                            id="search"
                            name="search"
                            placeholder="Enter post title..."
                            value="<?= e($search) ?>"
                        >
                    </div>
                </div>

                <div class="filter-status">
                    <label for="status">Filter by status</label>

                    <select name="status" id="status">

                        <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>
                            All Statuses
                        </option>

                        <option value="published" <?= $statusFilter === 'published' ? 'selected' : '' ?>>
                            Published
                        </option>

                        <option value="draft" <?= $statusFilter === 'draft' ? 'selected' : '' ?>>
                            Draft
                        </option>

                        <option value="archived" <?= $statusFilter === 'archived' ? 'selected' : '' ?>>
                            Archived
                        </option>

                    </select>
                </div>

                <button type="submit" class="button button-search">
                    <i class="bi bi-search"></i>
                    Search
                </button>

                <a href="manage-posts.php" class="button button-clear">
                    Clear
                </a>

            </form>

            <!-- Results -->
            <div class="posts-results-heading">
                <h3>Posts</h3>
                <span><?= count($posts) ?> result(s)</span>
            </div>

            <?php if (!empty($posts)): ?>

                <div class="table-responsive">

                    <table class="data-table manage-posts-table">

                        <thead>
                            <tr>
                                <th>Post Title</th>
                                <th>Category</th>
                                <th>Date Created</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($posts as $post): ?>

                                <tr>

                                    <td class="post-title-cell">
                                        <?= e($post['title']) ?>
                                    </td>

                                    <td>
                                        <?= e($post['category_name'] ?? '-') ?>
                                    </td>

                                    <td>
                                        <?= e(display_date($post['created_at'])) ?>
                                    </td>

                                    <td>
                                        <span class="status-badge <?= e(post_status_class($post['status'])) ?>">
                                            <?= e(ucfirst($post['status'])) ?>
                                        </span>
                                    </td>

                                    <td>

                                        <div class="table-actions">

                                            <a
                                                href="view-post.php?id=<?= (int) $post['post_id'] ?>"
                                                class="table-action view-action"
                                            >
                                                <i class="bi bi-eye"></i>
                                                View
                                            </a>

                                            <a
                                                href="edit-post.php?id=<?= (int) $post['post_id'] ?>"
                                                class="table-action edit-action"
                                            >
                                                <i class="bi bi-pencil-square"></i>
                                                Edit
                                            </a>

                                            <form
                                                method="POST"
                                                action="delete-post.php"
                                                onsubmit="return confirm('Are you sure you want to delete this post?');"
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
                                                    class="table-action delete-action"
                                                >
                                                    <i class="bi bi-trash"></i>
                                                    Delete
                                                </button>

                                            </form>

                                        </div>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="empty-state">
                    <i class="bi bi-search"></i>
                    <h3>No posts found</h3>
                    <p>Try another search term or change the status filter.</p>
                </div>

            <?php endif; ?>

        </section>

    </main>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
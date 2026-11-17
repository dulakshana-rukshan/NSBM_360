
<?php

require_once __DIR__ . '/president_auth.php';

$pageTitle = 'Manage Posts';
$isPresidentPage = true;


// --------------------------------------------------
// 1. GET FILTER VALUES
// --------------------------------------------------

$search = '';

if (isset($_GET['search']) && is_string($_GET['search'])) {
    $search = trim($_GET['search']);
}


$statusFilter = 'all';

if (isset($_GET['status']) && is_string($_GET['status'])) {
    $statusFilter = $_GET['status'];
}

$allowedStatuses = ['all', 'published', 'draft'];

if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = 'all';
}


// Category filter

$categoryFilter = 0;

if (isset($_GET['category']) && is_scalar($_GET['category'])) {

    $categoryValue = filter_var(
        $_GET['category'],
        FILTER_VALIDATE_INT
    );

    if ($categoryValue !== false && $categoryValue > 0) {
        $categoryFilter = $categoryValue;
    }
}


// Content type filter

$typeFilter = 'all';

if (isset($_GET['type']) && is_string($_GET['type'])) {
    $typeFilter = $_GET['type'];
}

$allowedTypes = ['all', 'posts', 'events'];

if (!in_array($typeFilter, $allowedTypes, true)) {
    $typeFilter = 'all';
}


// Sort filter

$sortFilter = 'newest';

if (isset($_GET['sort']) && is_string($_GET['sort'])) {
    $sortFilter = $_GET['sort'];
}

$allowedSorts = [
    'newest',
    'oldest',
    'az',
    'updated'
];

if (!in_array($sortFilter, $allowedSorts, true)) {
    $sortFilter = 'newest';
}


// --------------------------------------------------
// 2. PAGINATION
// --------------------------------------------------

$perPage = 10;

$currentPageNumber = 1;

if (isset($_GET['page']) && is_scalar($_GET['page'])) {

    $pageValue = filter_var(
        $_GET['page'],
        FILTER_VALIDATE_INT
    );

    if ($pageValue !== false && $pageValue > 0) {
        $currentPageNumber = $pageValue;
    }
}


// --------------------------------------------------
// 3. GET ALLOWED MAIN CATEGORIES
// --------------------------------------------------

// Sports clubs can use Sports and Events.
// General clubs can use Academics, Events and News.

if ($clubType === 'sports') {

    $allowedCategoryNames = ['Sports', 'Events'];

} else {

    $allowedCategoryNames = ['Academics', 'Events', 'News'];

}


$placeholders = implode(
    ', ',
    array_fill(0, count($allowedCategoryNames), '?')
);


$categoryQuery = "
    SELECT
        category_id,
        category_name

    FROM categories

    WHERE category_name IN ($placeholders)

    ORDER BY category_name ASC
";


$categoryStmt = $pdo->prepare($categoryQuery);

$categoryStmt->execute($allowedCategoryNames);

$categories = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);


// Check whether selected category belongs to this club's allowed categories.

$validCategoryIds = array_map(
    'intval',
    array_column($categories, 'category_id')
);


if (
    $categoryFilter > 0 &&
    !in_array($categoryFilter, $validCategoryIds, true)
) {
    $categoryFilter = 0;
}


// --------------------------------------------------
// 4. STATISTICS
// --------------------------------------------------

$statsQuery = "
    SELECT

        COUNT(p.post_id) AS total_posts,

        COALESCE(
            SUM(
                CASE
                    WHEN p.status = 'published' THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS published_posts,

        COALESCE(
            SUM(
                CASE
                    WHEN p.status = 'draft' THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS draft_posts,

        COUNT(DISTINCT ev.event_id) AS total_events

    FROM posts p

    LEFT JOIN events ev
        ON p.post_id = ev.post_id

    WHERE p.club_id = :club_id
";


$statsStmt = $pdo->prepare($statsQuery);

$statsStmt->execute([
    'club_id' => $clubId
]);

$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);


// --------------------------------------------------
// 5. BUILD FILTER CONDITIONS
// --------------------------------------------------

$whereConditions = [
    'p.club_id = :club_id'
];

$params = [
    'club_id' => $clubId
];


// Search

if ($search !== '') {

    $whereConditions[] = 'p.title LIKE :search';

    $params['search'] = '%' . $search . '%';
}


// Status

if ($statusFilter !== 'all') {

    $whereConditions[] = 'p.status = :status';

    $params['status'] = $statusFilter;
}


// Category

if ($categoryFilter > 0) {

    $whereConditions[] = 'p.category_id = :category_id';

    $params['category_id'] = $categoryFilter;
}


// Content type

if ($typeFilter === 'events') {

    $whereConditions[] = "
        EXISTS (
            SELECT 1
            FROM events AS event_check
            WHERE event_check.post_id = p.post_id
        )
    ";

} elseif ($typeFilter === 'posts') {

    $whereConditions[] = "
        NOT EXISTS (
            SELECT 1
            FROM events AS event_check
            WHERE event_check.post_id = p.post_id
        )
    ";
}


$whereSQL = implode(' AND ', $whereConditions);


// --------------------------------------------------
// 6. GET TOTAL FILTERED RESULTS
// --------------------------------------------------

$countQuery = "
    SELECT COUNT(*)

    FROM posts p

    WHERE $whereSQL
";


$countStmt = $pdo->prepare($countQuery);

$countStmt->execute($params);

$totalResults = (int) $countStmt->fetchColumn();


// --------------------------------------------------
// 7. CALCULATE PAGINATION
// --------------------------------------------------

$totalPages = max(
    1,
    (int) ceil($totalResults / $perPage)
);

if ($currentPageNumber > $totalPages) {
    $currentPageNumber = $totalPages;
}

$offset = ($currentPageNumber - 1) * $perPage;


// --------------------------------------------------
// 8. SORT OPTIONS
// --------------------------------------------------

$sortOptions = [

    'newest' => 'p.created_at DESC, p.post_id DESC',

    'oldest' => 'p.created_at ASC, p.post_id ASC',

    'az' => 'p.title ASC, p.post_id DESC',

    'updated' => 'p.updated_at DESC, p.post_id DESC'

];

$orderBy = $sortOptions[$sortFilter];


// --------------------------------------------------
// 9. GET POSTS AND EVENTS
// --------------------------------------------------

$postsQuery = "
    SELECT

        p.post_id,
        p.title,
        p.status,
        p.created_at,
        p.updated_at,
        p.category_id,

        c.category_name,

        ev.event_id,
        ev.event_date,
        ev.venue

    FROM posts p

    LEFT JOIN categories c
        ON p.category_id = c.category_id

    LEFT JOIN events ev
        ON p.post_id = ev.post_id

    WHERE $whereSQL

    ORDER BY $orderBy

    LIMIT :limit OFFSET :offset
";


$postsStmt = $pdo->prepare($postsQuery);


// Bind filter values

foreach ($params as $key => $value) {

    $parameterType = is_int($value)
        ? PDO::PARAM_INT
        : PDO::PARAM_STR;

    $postsStmt->bindValue(
        ':' . $key,
        $value,
        $parameterType
    );
}


// Bind pagination values

$postsStmt->bindValue(
    ':limit',
    $perPage,
    PDO::PARAM_INT
);

$postsStmt->bindValue(
    ':offset',
    $offset,
    PDO::PARAM_INT
);

$postsStmt->execute();

$posts = $postsStmt->fetchAll(PDO::FETCH_ASSOC);


// --------------------------------------------------
// 10. HEADER
// --------------------------------------------------

require_once __DIR__ . '/president_header.php';

?>

<div class="page-shell">

    <?php require_once __DIR__ . '/president_sidebar.php'; ?>

    <main class="main-content">


        <!-- PAGE HEADER -->

        <div class="page-header">

            <div>
                <h1>Manage Posts</h1>

                <p>
                    Manage your club's news, events and updates in one place.
                </p>
            </div>

            <div class="page-header-actions">

                <a href="create-post.php" class="button button-primary">

                    <i class="bi bi-plus-circle"></i>

                    Create New Post

                </a>

            </div>

        </div>


        <!-- STATISTICS -->

        <section class="stats-grid">


            <div class="stat-card">

                <div class="stat-icon total-icon">
                    <i class="bi bi-file-earmark-text"></i>
                </div>

                <div>
                    <p>Total Posts</p>

                    <h2>
                        <?= (int) ($stats['total_posts'] ?? 0) ?>
                    </h2>
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-icon published-icon">
                    <i class="bi bi-check-circle"></i>
                </div>

                <div>
                    <p>Published</p>

                    <h2>
                        <?= (int) ($stats['published_posts'] ?? 0) ?>
                    </h2>
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-icon draft-icon">
                    <i class="bi bi-pencil-square"></i>
                </div>

                <div>
                    <p>Drafts</p>

                    <h2>
                        <?= (int) ($stats['draft_posts'] ?? 0) ?>
                    </h2>
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-icon events-icon">
                    <i class="bi bi-calendar-event"></i>
                </div>

                <div>
                    <p>Total Events</p>

                    <h2>
                        <?= (int) ($stats['total_events'] ?? 0) ?>
                    </h2>
                </div>

            </div>


        </section>


        <!-- MAIN CONTENT CARD -->

        <section class="content-card manage-posts-card">


            <div class="section-heading">

                <div>
                    <h2>All Club Content</h2>

                    <p>
                        Search, filter and manage your club publications.
                    </p>
                </div>

            </div>


            <!-- SEARCH AND FILTER FORM -->

            <form
                method="GET"
                action="manage-posts.php"
                class="filter-bar manage-filter-form"
            >


                <!-- SEARCH -->

                <div class="filter-search">

                    <label for="search">
                        Search by title
                    </label>

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


                <!-- CATEGORY FILTER -->

                <div class="filter-group">

                    <label for="category">
                        Category
                    </label>

                    <select name="category" id="category">

                        <option value="0">
                            All Categories
                        </option>

                        <?php foreach ($categories as $category): ?>

                            <option
                                value="<?= (int) $category['category_id'] ?>"
                                <?= $categoryFilter === (int) $category['category_id'] ? 'selected' : '' ?>
                            >

                                <?= e($category['category_name']) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- CONTENT TYPE FILTER -->

                <div class="filter-group">

                    <label for="type">
                        Content Type
                    </label>

                    <select name="type" id="type">

                        <option
                            value="all"
                            <?= $typeFilter === 'all' ? 'selected' : '' ?>
                        >
                            All Content
                        </option>

                        <option
                            value="posts"
                            <?= $typeFilter === 'posts' ? 'selected' : '' ?>
                        >
                            Posts Only
                        </option>

                        <option
                            value="events"
                            <?= $typeFilter === 'events' ? 'selected' : '' ?>
                        >
                            Events Only
                        </option>

                    </select>

                </div>


                <!-- STATUS FILTER -->

                <div class="filter-group">

                    <label for="status">
                        Status
                    </label>

                    <select name="status" id="status">

                        <option
                            value="all"
                            <?= $statusFilter === 'all' ? 'selected' : '' ?>
                        >
                            All Statuses
                        </option>

                        <option
                            value="published"
                            <?= $statusFilter === 'published' ? 'selected' : '' ?>
                        >
                            Published
                        </option>

                        <option
                            value="draft"
                            <?= $statusFilter === 'draft' ? 'selected' : '' ?>
                        >
                            Draft
                        </option>

                    </select>

                </div>


                <!-- SORT FILTER -->

                <div class="filter-group">

                    <label for="sort">
                        Sort By
                    </label>

                    <select name="sort" id="sort">

                        <option
                            value="newest"
                            <?= $sortFilter === 'newest' ? 'selected' : '' ?>
                        >
                            Newest First
                        </option>

                        <option
                            value="oldest"
                            <?= $sortFilter === 'oldest' ? 'selected' : '' ?>
                        >
                            Oldest First
                        </option>

                        <option
                            value="az"
                            <?= $sortFilter === 'az' ? 'selected' : '' ?>
                        >
                            A - Z
                        </option>

                        <option
                            value="updated"
                            <?= $sortFilter === 'updated' ? 'selected' : '' ?>
                        >
                            Recently Updated
                        </option>

                    </select>

                </div>


                <!-- BUTTONS -->

                <div class="filter-buttons">

                    <button
                        type="submit"
                        class="button button-search"
                    >

                        <i class="bi bi-search"></i>

                        Apply Filters

                    </button>


                    <a
                        href="manage-posts.php"
                        class="button button-clear"
                    >

                        <i class="bi bi-arrow-counterclockwise"></i>

                        Reset

                    </a>

                </div>


            </form>


            <!-- RESULTS HEADING -->

            <div class="posts-results-heading">

                <div>

                    <h3>Posts and Events</h3>

                    <p>
                        Showing
                        <?= $totalResults > 0 ? $offset + 1 : 0 ?>
                        -
                        <?= min($offset + $perPage, $totalResults) ?>
                        of
                        <?= $totalResults ?>
                        results
                    </p>

                </div>

                <span class="results-count">
                    <?= $totalResults ?> result(s)
                </span>

            </div>


            <!-- RESULTS TABLE -->

            <?php if (!empty($posts)): ?>

                <div class="table-responsive">

                    <table class="data-table manage-posts-table">

                        <thead>

                            <tr>

                                <th>Title</th>

                                <th>Category</th>

                                <th>Date / Venue</th>

                                <th>Status</th>

                                <th>Actions</th>

                            </tr>

                        </thead>


                        <tbody>

                            <?php foreach ($posts as $post): ?>

                                <?php

                                $isEvent = !empty($post['event_id']);

                                ?>

                                <tr>


                                    <!-- TITLE -->

                                    <td class="post-title-cell">

                                        <div class="post-title-wrapper">

                                            <span class="post-type-icon <?= $isEvent ? 'event-type-icon' : 'normal-type-icon' ?>">

                                                <i class="bi <?= $isEvent ? 'bi-calendar-event' : 'bi-file-earmark-text' ?>"></i>

                                            </span>

                                            <span>
                                                <?= e($post['title']) ?>
                                            </span>

                                        </div>

                                    </td>


                                    <!-- CATEGORY -->

                                    <td>

                                        <span class="category-badge <?= $isEvent ? 'event-category-badge' : 'normal-category-badge' ?>">

                                            <?= e($post['category_name'] ?? 'Uncategorized') ?>

                                        </span>

                                    </td>


                                    <!-- DATE / VENUE -->

                                    <td>

                                        <?php if ($isEvent): ?>

                                            <div class="event-info-cell">

                                                <span>

                                                    <i class="bi bi-calendar3"></i>

                                                    <?= !empty($post['event_date'])
                                                        ? e(display_date($post['event_date']))
                                                        : 'Date not set' ?>

                                                </span>

                                                <small>

                                                    <i class="bi bi-geo-alt"></i>

                                                    <?= e($post['venue'] ?: 'Venue not set') ?>

                                                </small>

                                            </div>

                                        <?php else: ?>

                                            <?= e(display_date($post['created_at'])) ?>

                                        <?php endif; ?>

                                    </td>


                                    <!-- STATUS -->

                                    <td>

                                        <span class="status-badge <?= e(post_status_class($post['status'])) ?>">

                                            <?= e(ucfirst($post['status'])) ?>

                                        </span>

                                    </td>


                                    <!-- ACTIONS -->

                                    <td>

                                        <div class="table-actions">


                                            <!-- VIEW -->

                                            <a
                                                href="<?= $isEvent
                                                    ? 'view-event.php?id=' . (int) $post['event_id']
                                                    : 'view-post.php?id=' . (int) $post['post_id'] ?>"
                                                class="table-action view-action"
                                            >

                                                <i class="bi bi-eye"></i>

                                                View

                                            </a>


                                            <!-- EDIT -->

                                            <a
                                                href="edit-post.php?id=<?= (int) $post['post_id'] ?>"
                                                class="table-action edit-action"
                                            >

                                                <i class="bi bi-pencil-square"></i>

                                                Edit

                                            </a>


                                            <!-- DELETE -->

                                            <form
                                                method="POST"
                                                action="delete-post.php"
                                                onsubmit="return confirm('Are you sure you want to delete this content?');"
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


                <!-- PAGINATION -->

                <?php if ($totalPages > 1): ?>

                    <div class="posts-pagination">


                        <?php if ($currentPageNumber > 1): ?>

                            <a
                                href="?<?= e(http_build_query([
                                    'search' => $search,
                                    'category' => $categoryFilter,
                                    'type' => $typeFilter,
                                    'status' => $statusFilter,
                                    'sort' => $sortFilter,
                                    'page' => $currentPageNumber - 1
                                ])) ?>"
                                class="pagination-button"
                            >

                                <i class="bi bi-chevron-left"></i>

                                Previous

                            </a>

                        <?php endif; ?>


                        <span class="pagination-info">

                            Page <?= $currentPageNumber ?>

                            of <?= $totalPages ?>

                        </span>


                        <?php if ($currentPageNumber < $totalPages): ?>

                            <a
                                href="?<?= e(http_build_query([
                                    'search' => $search,
                                    'category' => $categoryFilter,
                                    'type' => $typeFilter,
                                    'status' => $statusFilter,
                                    'sort' => $sortFilter,
                                    'page' => $currentPageNumber + 1
                                ])) ?>"
                                class="pagination-button"
                            >

                                Next

                                <i class="bi bi-chevron-right"></i>

                            </a>

                        <?php endif; ?>


                    </div>

                <?php endif; ?>


            <?php else: ?>


                <!-- EMPTY STATE -->

                <div class="empty-state">

                    <i class="bi bi-folder2-open"></i>

                    <h3>No content found</h3>

                    <p>
                        No posts or events match your selected filters.
                    </p>

                    <a
                        href="manage-posts.php"
                        class="button button-clear"
                    >

                        <i class="bi bi-arrow-counterclockwise"></i>

                        Clear Filters

                    </a>

                </div>


            <?php endif; ?>


        </section>

    </main>

</div>


<?php require_once __DIR__ . '/../includes/footer.php'; ?>

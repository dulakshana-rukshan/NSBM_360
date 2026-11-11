<?php
// --- SHARED POSTS ENGINE TEMPLATE ---
// Requires configuration variables ($pageTitle, $activePage, $baseCategoryParentId, etc.) to be set before including.

// 1. Locate db.php (Goes up one level from pages/ to root, then into config/)
require_once __DIR__ . '/../config/database.php';

// Relative path from pages/ folder to assets/css/page_style.css
$pageCSS = $pageCSS ?? '../assets/css/page_style.css';

// 2. Capture filter inputs from the URL query string
$searchKeyword = trim($_GET['search'] ?? '');
$selectedCategory = $_GET['category'] ?? 'all';
$selectedMonth = $_GET['month'] ?? 'all';
$sortOrder = $_GET['sort'] ?? 'newest';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$postsPerPage = 6;

// 3. Build common WHERE clause & parameters securely using UNIQUE parameter names
$whereClauses = ["TRIM(LOWER(posts.status)) = 'published'"];
$params = [];

if (isset($baseCategoryParentId)) {
    $whereClauses[] = "(posts.category_id = :parent_cat OR posts.category_id IN (SELECT category_id FROM categories WHERE parent_id = :parent_cat_sub))";
    $params['parent_cat'] = $baseCategoryParentId;
    $params['parent_cat_sub'] = $baseCategoryParentId;
}

if ($searchKeyword !== '') {
    $whereClauses[] = "(
        LOWER(posts.title) LIKE :title_k 
        OR LOWER(posts.summary) LIKE :summary_k 
        OR LOWER(posts.content) LIKE :content_k 
        OR LOWER(categories.category_name) LIKE :category_k
    )";
    $k = '%' . strtolower($searchKeyword) . '%';
    $params['title_k'] = $k;
    $params['summary_k'] = $k;
    $params['content_k'] = $k;
    $params['category_k'] = $k;
}

if ($selectedCategory !== 'all' && is_numeric($selectedCategory)) {
    $whereClauses[] = "posts.category_id = :category_id";
    $params['category_id'] = $selectedCategory;
}

if ($selectedMonth !== 'all' && preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) {
    $whereClauses[] = "DATE_FORMAT(posts.created_at, '%Y-%m') = :selected_month";
    $params['selected_month'] = $selectedMonth;
}

$whereString = implode(" AND ", $whereClauses);

// 4. Count total matching records using LEFT JOIN
try {
    $countSql = "SELECT COUNT(*) FROM posts LEFT JOIN categories ON posts.category_id = categories.category_id WHERE " . $whereString;
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalPosts = (int)$countStmt->fetchColumn();
} catch (\PDOException $e) {
    error_log("Query Error: " . $e->getMessage());
    $totalPosts = 0;
}

$totalPages = max(1, ceil($totalPosts / $postsPerPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $postsPerPage;

// 5. Build main posts query with LEFT JOIN and dynamic sorting
$sql = "
    SELECT posts.*, COALESCE(categories.category_name, 'General') AS category_name 
    FROM posts 
    LEFT JOIN categories ON posts.category_id = categories.category_id 
    WHERE " . $whereString;

if ($sortOrder === 'oldest') {
    $sql .= " ORDER BY posts.created_at ASC";
} else {
    $sql .= " ORDER BY posts.created_at DESC";
}

$sql .= " LIMIT :limit OFFSET :offset";

try {
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $val) {
        $stmt->bindValue(':' . $key, $val);
    }
    $stmt->bindValue(':limit', $postsPerPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $posts = $stmt->fetchAll();
} catch (\PDOException $e) {
    error_log("Main Query Error: " . $e->getMessage());
    $posts = [];
}

// Fetch relevant subcategories for the dropdown dynamically
if (isset($baseCategoryParentId)) {
    $catStmt = $pdo->prepare("SELECT * FROM categories WHERE parent_id = :parent_id ORDER BY category_name ASC");
    $catStmt->execute(['parent_id' => $baseCategoryParentId]);
} else {
    $catStmt = $pdo->query("SELECT * FROM categories ORDER BY category_name ASC");
}
$allCategories = $catStmt->fetchAll();

// Fetch distinct months dynamically
$monthStmt = $pdo->query("
    SELECT DISTINCT DATE_FORMAT(created_at, '%Y-%m') AS month_val, 
           DATE_FORMAT(created_at, '%M %Y') AS month_label 
    FROM posts 
    WHERE TRIM(LOWER(status)) = 'published' 
    ORDER BY created_at DESC
");
$allMonths = $monthStmt->fetchAll();

// Helper function to render grid and pagination markup
function renderPostsContent($posts, $totalPosts, $postsPerPage, $page, $totalPages) {
    $maxVisible = 4;
    $startPage = max(1, $page - 1);
    $endPage = min($totalPages, $startPage + $maxVisible - 1);
    if (($endPage - $startPage + 1) < $maxVisible) {
        $startPage = max(1, $endPage - $maxVisible + 1);
    }
    ?>
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mb-5">
        <?php if (count($posts) > 0): ?>
            <?php foreach ($posts as $post): ?>
                <div class="col">
                    <div class="card h-100">
                        <img src="<?= htmlspecialchars($post['cover_image'] ?? 'https://via.placeholder.com/400x200') ?>" class="card-img-top" alt="Cover Image">
                        <div class="card-body d-flex flex-column">
                            <div class="mb-2">
                                <span class="badge bg-primary px-2 py-1 rounded"><?= htmlspecialchars($post['category_name']) ?></span>
                            </div>
                            <h5 class="card-title fw-bold text-dark"><?= htmlspecialchars($post['title']) ?></h5>
                            <p class="card-text text-muted small flex-grow-1"><?= htmlspecialchars($post['summary']) ?></p>
                            <div class="mt-3">
                                <p class="text-custom-muted mb-2"><?= date('d M Y', strtotime($post['created_at'])) ?></p>
                                <a href="read-story.php?id=<?= $post['post_id'] ?>" class="read-story-link">Read story &rarr;</a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12">
                <p class="text-muted text-center py-4">No published stories found matching your filter criteria.</p>
            </div>
        <?php endif; ?>
    </div>

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 pt-3 border-top">
        <span class="text-muted small">
            Showing <?= $totalPosts > 0 ? (($page - 1) * $postsPerPage + 1) . '–' . min($page * $postsPerPage, $totalPosts) : 0 ?> of <?= $totalPosts ?> results
        </span>
        <nav aria-label="Page navigation">
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                    <a class="page-link pagination-link" href="#" data-page="<?= $page - 1 ?>" <?= ($page <= 1) ? 'tabindex="-1" aria-disabled="true"' : '' ?>>Previous</a>
                </li>
                <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                    <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                        <a class="page-link pagination-link" href="#" data-page="<?= $i ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                    <a class="page-link pagination-link" href="#" data-page="<?= $page + 1 ?>" <?= ($page >= $totalPages) ? 'tabindex="-1" aria-disabled="true"' : '' ?>>Next</a>
                </li>
            </ul>
        </nav>
    </div>
    <?php
}

// Handle AJAX Request
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    renderPostsContent($posts, $totalPosts, $postsPerPage, $page, $totalPages);
    exit;
}

// Include header and navbar from the /includes/ folder
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php'; 
?>

<main class="container py-5">
    <nav aria-label="breadcrumb" class="mb-2">
        <ol class="breadcrumb mb-1">
            <li class="breadcrumb-item"><a href="#" class="text-decoration-none text-muted">Home</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($pageHeading) ?></li>
        </ol>
    </nav>

    <div class="mb-4">
        <h1 class="fw-bold text-dark mb-1"><?= htmlspecialchars($pageHeading) ?></h1>
        <p class="text-muted"><?= htmlspecialchars($pageSubheading) ?></p>
    </div>

    <form method="GET" action="" id="filterForm" class="card p-3 mb-4">
        <div class="row g-3 align-items-center">
            <div class="col-lg-4 col-md-12">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="search" value="<?= htmlspecialchars($searchKeyword) ?>" class="form-control border-start-0 ps-0 shadow-none" placeholder="Search by title or keyword" autocomplete="off">
                </div>
            </div>

            <div class="col-lg-2 col-md-4">
                <select name="category" class="form-select text-muted shadow-none">
                    <option value="all">Categories</option>
                    <?php foreach ($allCategories as $cat): ?>
                        <option value="<?= $cat['category_id'] ?>" <?= ($selectedCategory == $cat['category_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['category_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-lg-2 col-md-4">
                <select class="form-select text-muted shadow-none" disabled>
                    <option selected>Published</option>
                </select>
            </div>

            <div class="col-lg-2 col-md-4">
                <select name="month" class="form-select text-muted shadow-none">
                    <option value="all" <?= ($selectedMonth === 'all') ? 'selected' : '' ?>>All Months</option>
                    <?php foreach ($allMonths as $m): ?>
                        <option value="<?= $m['month_val'] ?>" <?= ($selectedMonth === $m['month_val']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($m['month_label']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-lg-2 col-md-12">
                <button type="submit" class="btn btn-success w-100 fw-semibold">Search</button>
            </div>
        </div>
    </form>

    <form method="GET" action="" id="sortForm" class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <input type="hidden" name="search" value="<?= htmlspecialchars($searchKeyword) ?>">
        <input type="hidden" name="category" value="<?= htmlspecialchars($selectedCategory) ?>">
        <input type="hidden" name="month" value="<?= htmlspecialchars($selectedMonth) ?>">
        <input type="hidden" name="page" value="<?= $page ?>">

        <span class="text-muted small">Showing results matching your filters</span>
        <div class="d-flex align-items-center gap-2">
            <span class="text-muted small">Sort by:</span>
            <select name="sort" class="form-select form-select-sm w-auto shadow-none">
                <option value="newest" <?= ($sortOrder === 'newest') ? 'selected' : '' ?>>Newest first</option>
                <option value="oldest" <?= ($sortOrder === 'oldest') ? 'selected' : '' ?>>Oldest first</option>
            </select>
        </div>
    </form>

    <div id="postsContainer">
        <?php renderPostsContent($posts, $totalPosts, $postsPerPage, $page, $totalPages); ?>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const filterForm = document.getElementById('filterForm');
    const sortForm = document.getElementById('sortForm');
    const searchInput = filterForm.querySelector('input[name="search"]');
    const categorySelect = filterForm.querySelector('select[name="category"]');
    const monthSelect = filterForm.querySelector('select[name="month"]');
    const sortSelect = sortForm.querySelector('select[name="sort"]');
    const postsContainer = document.getElementById('postsContainer');

    let debounceTimer;
    let currentController = null;
    let currentPage = <?= $page ?>;

    function fetchFilteredResults(targetPage = 1) {
        currentPage = targetPage;
        const searchVal = searchInput.value;
        const categoryVal = categorySelect.value;
        const monthVal = monthSelect.value;
        const sortVal = sortSelect.value;

        sortForm.querySelector('input[name="search"]').value = searchVal;
        sortForm.querySelector('input[name="category"]').value = categoryVal;
        sortForm.querySelector('input[name="month"]').value = monthVal;
        sortForm.querySelector('input[name="page"]').value = currentPage;

        const params = new URLSearchParams({
            search: searchVal,
            category: categoryVal,
            month: monthVal,
            sort: sortVal,
            page: currentPage,
            ajax: '1'
        });

        if (currentController) {
            currentController.abort();
        }
        currentController = new AbortController();

        const fetchUrl = window.location.origin + window.location.pathname + '?' + params.toString();

        fetch(fetchUrl, { signal: currentController.signal })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.text();
            })
            .then(html => {
                postsContainer.innerHTML = html;
                attachPaginationEvents(); 
                
                const cleanParams = new URLSearchParams({
                    search: searchVal,
                    category: categoryVal,
                    month: monthVal,
                    sort: sortVal,
                    page: currentPage
                });
                window.history.replaceState({}, '', window.location.pathname + '?' + cleanParams.toString());
            })
            .catch(error => {
                if (error.name !== 'AbortError') {
                    console.error('Error fetching search results:', error);
                }
            });
    }

    function attachPaginationEvents() {
        postsContainer.querySelectorAll('.pagination-link').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const parentLi = this.closest('.page-item');
                if (parentLi.classList.contains('disabled') || parentLi.classList.contains('active')) {
                    return;
                }
                const targetPage = parseInt(this.getAttribute('data-page'));
                if (targetPage && !isNaN(targetPage)) {
                    fetchFilteredResults(targetPage);
                    window.scrollTo({ top: postsContainer.offsetTop - 100, behavior: 'smooth' });
                }
            });
        });
    }

    attachPaginationEvents();

    filterForm.addEventListener('submit', function(e) {
        e.preventDefault();
        fetchFilteredResults(1); 
    });

    searchInput.addEventListener('input', function() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => fetchFilteredResults(1), 300);
    });

    categorySelect.addEventListener('change', () => fetchFilteredResults(1));
    monthSelect.addEventListener('change', () => fetchFilteredResults(1));
    sortSelect.addEventListener('change', () => fetchFilteredResults(1));
});
</script>

<?php 
// Include footer from the /includes/ folder
include __DIR__ . '/../includes/footer.php';
?>
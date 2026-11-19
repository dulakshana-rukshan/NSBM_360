<?php
// --- SHARED POSTS ENGINE TEMPLATE ---
// Requires configuration variables ($pageTitle, $activePage, $baseCategoryParentId, etc.) to be set before including.

// 1. Locate database connection
require_once __DIR__ . '/../config/database.php';

// Relative path from pages/ folder to assets/css/page_style.css
$pageCSS = $pageCSS ?? '../assets/css/page_style.css';

// 2. Capture filter inputs from the URL query string
$searchKeyword = trim($_GET['search'] ?? '');
$selectedSubcategory = $_GET['subcategory'] ?? 'all'; // Filters by subcategory_id
$selectedFaculty = $_GET['faculty'] ?? 'all';
$selectedMonth = $_GET['month'] ?? 'all';
$sortOrder = $_GET['sort'] ?? 'newest';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$postsPerPage = 6;

// 3. Build common WHERE clause & parameters securely using UNIQUE parameter names
$whereClauses = ["TRIM(LOWER(posts.status)) = 'published'"];
$params = [];

// Restrict posts strictly to the main section category_id (e.g., 1000 for News, 2000 for Events)
if (isset($baseCategoryParentId)) {
    $whereClauses[] = "posts.category_id = :parent_cat";
    $params['parent_cat'] = $baseCategoryParentId;
}

if ($searchKeyword !== '') {
    $whereClauses[] = "(
        LOWER(posts.title) LIKE :title_k 
        OR LOWER(posts.summary) LIKE :summary_k 
        OR LOWER(posts.content) LIKE :content_k 
    )";
    $k = '%' . strtolower($searchKeyword) . '%';
    $params['title_k'] = $k;
    $params['summary_k'] = $k;
    $params['content_k'] = $k;
}

// Filter by specific subcategory_id if selected
if ($selectedSubcategory !== 'all' && is_numeric($selectedSubcategory)) {
    $whereClauses[] = "posts.subcategory_id = :subcat_id";
    $params['subcat_id'] = $selectedSubcategory;
}

// Filter posts using multiple faculty relationships
if ($selectedFaculty !== 'all' && ctype_digit((string)$selectedFaculty)) {

    $whereClauses[] = "
        EXISTS (
            SELECT 1
            FROM post_faculties pf
            WHERE pf.post_id = posts.post_id
              AND pf.faculty_id = :faculty_id
        )
    ";

    $params['faculty_id'] = (int)$selectedFaculty;
}

if ($selectedMonth !== 'all' && preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) {
    $whereClauses[] = "DATE_FORMAT(posts.created_at, '%Y-%m') = :selected_month";
    $params['selected_month'] = $selectedMonth;
}

$whereString = implode(" AND ", $whereClauses);

// 4. Count total matching records using LEFT JOIN
try {
    $countSql = "SELECT COUNT(*) FROM posts LEFT JOIN subcategories ON posts.subcategory_id = subcategories.subcategory_id WHERE " . $whereString;
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

// 5. Build main posts query joining subcategories to get the badge name
$sql = "
    SELECT posts.*, COALESCE(subcategories.subcategory_name, 'General') AS category_name 
    FROM posts 
    LEFT JOIN subcategories ON posts.subcategory_id = subcategories.subcategory_id 
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

// Fetch subcategories dynamically from the `subcategories` table where category_id matches parent (e.g. 1000)
try {
    if (isset($baseCategoryParentId)) {
        $subCatStmt = $pdo->prepare("SELECT * FROM subcategories WHERE category_id = :parent_id ORDER BY subcategory_name ASC");
        $subCatStmt->execute(['parent_id' => $baseCategoryParentId]);
        $allSubcategories = $subCatStmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $subCatStmt = $pdo->query("SELECT * FROM subcategories ORDER BY subcategory_name ASC");
        $allSubcategories = $subCatStmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (\PDOException $e) {
    $allSubcategories = [];
}

// Fetch faculties dynamically for the dropdown
try {
    $facultyStmt = $pdo->query("SELECT * FROM faculties ORDER BY faculty_name ASC");
    $allFaculties = $facultyStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\PDOException $e) {
    $allFaculties = [];
}

// Fetch distinct months dynamically
try {
    $monthStmt = $pdo->query("
        SELECT DISTINCT DATE_FORMAT(created_at, '%Y-%m') AS month_val, 
               DATE_FORMAT(created_at, '%M %Y') AS month_label 
        FROM posts 
        WHERE TRIM(LOWER(status)) = 'published' 
        ORDER BY created_at DESC
    ");
    $allMonths = $monthStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\PDOException $e) {
    $allMonths = [];
}
function post_image_url(?string $path): string
{
    $fallback = 'https://placehold.co/400x200?text=No+Image';

    if (empty(trim($path ?? ''))) {
        return $fallback;
    }

    $path = trim(str_replace('\\', '/', $path));

    // Already a complete URL
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }

    // Remove leading ../ or ./
    $path = preg_replace('#^(\.\./|\./)+#', '', $path);

    // Already contains project root
    if (str_starts_with($path, '/NSBM_360/')) {
        return $path;
    }

    if (str_starts_with($path, 'NSBM_360/')) {
        return '/' . $path;
    }

    return '/NSBM_360/' . ltrim($path, '/');
}

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
                    <a href="news_details.php?id=<?= $post['post_id'] ?>" class="text-decoration-none h-100 d-block">
                        <div class="card h-100 card-hover-effect">
                            <img
                                src="<?= htmlspecialchars(post_image_url($post['cover_image'] ?? null), ENT_QUOTES, 'UTF-8') ?>"
                                class="card-img-top"
                                alt="Cover Image"
                            >
                            <div class="card-body d-flex flex-column">
                                <div class="mb-2">
                                    <span class="badge bg-primary px-2 py-1 rounded"><?= htmlspecialchars($post['category_name']) ?></span>
                                </div>
                                <h5 class="card-title fw-bold text-dark"><?= htmlspecialchars($post['title']) ?></h5>
                                <p class="card-text text-muted small flex-grow-1"><?= htmlspecialchars($post['summary']) ?></p>
                                <div class="mt-3">
                                    <p class="text-custom-muted mb-2"><?= date('d M Y', strtotime($post['created_at'])) ?></p>
                                    <span class="read-story-link">Read story &rarr;</span>
                                </div>
                            </div>
                        </div>
                    </a>
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

    <!-- Filter Form -->
    <form method="GET" action="" id="filterForm" class="card p-3 mb-4">
        <div class="row g-3 align-items-center">
            <div class="col-lg-4 col-md-12">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="search" value="<?= htmlspecialchars($searchKeyword) ?>" class="form-control border-start-0 ps-0 shadow-none" placeholder="Search by title or keyword" autocomplete="off">
                </div>
            </div>

            <!-- Subcategories Dropdown -->
            <div class="col-lg-2 col-md-4">
                <select name="subcategory" class="form-select text-muted shadow-none">
                    <option value="all">Categories</option>
                    <?php foreach ($allSubcategories as $sub): ?>
                        <option value="<?= $sub['subcategory_id'] ?>" <?= ($selectedSubcategory == $sub['subcategory_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($sub['subcategory_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Faculty Dropdown -->
            <div class="col-lg-2 col-md-4">
                <select name="faculty" class="form-select text-muted shadow-none">
                    <option value="all">Faculties</option>
                    <?php foreach ($allFaculties as $fac): ?>
                        <option value="<?= $fac['faculty_id'] ?>" <?= ($selectedFaculty == $fac['faculty_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($fac['faculty_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Month Dropdown -->
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

    <!-- Sort Form -->
    <form method="GET" action="" id="sortForm" class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <input type="hidden" name="search" value="<?= htmlspecialchars($searchKeyword) ?>">
        <input type="hidden" name="subcategory" value="<?= htmlspecialchars($selectedSubcategory) ?>">
        <input type="hidden" name="faculty" value="<?= htmlspecialchars($selectedFaculty) ?>">
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
    const subcategorySelect = filterForm.querySelector('select[name="subcategory"]');
    const facultySelect = filterForm.querySelector('select[name="faculty"]');
    const monthSelect = filterForm.querySelector('select[name="month"]');
    const sortSelect = sortForm.querySelector('select[name="sort"]');
    const postsContainer = document.getElementById('postsContainer');

    let debounceTimer;
    let currentController = null;
    let currentPage = <?= $page ?>;

    function fetchFilteredResults(targetPage = 1) {
        currentPage = targetPage;
        const searchVal = searchInput.value;
        const subcategoryVal = subcategorySelect.value;
        const facultyVal = facultySelect.value;
        const monthVal = monthSelect.value;
        const sortVal = sortSelect.value;

        sortForm.querySelector('input[name="search"]').value = searchVal;
        sortForm.querySelector('input[name="subcategory"]').value = subcategoryVal;
        sortForm.querySelector('input[name="faculty"]').value = facultyVal;
        sortForm.querySelector('input[name="month"]').value = monthVal;
        sortForm.querySelector('input[name="page"]').value = currentPage;

        const params = new URLSearchParams({
            search: searchVal,
            subcategory: subcategoryVal,
            faculty: facultyVal,
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
                    subcategory: subcategoryVal,
                    faculty: facultyVal,
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

    subcategorySelect.addEventListener('change', () => fetchFilteredResults(1));
    facultySelect.addEventListener('change', () => fetchFilteredResults(1));
    monthSelect.addEventListener('change', () => fetchFilteredResults(1));
    sortSelect.addEventListener('change', () => fetchFilteredResults(1));
});
</script>

<?php 
// Include footer from the /includes/ folder
include __DIR__ . '/../includes/footer.php';
?>
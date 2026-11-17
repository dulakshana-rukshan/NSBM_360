<?php
// --- DEDICATED SPORTS PAGE ---
$pageTitle = 'Sports News | NSBM 360';
$activePage = 'sports';
$pageHeading = 'Sports News';
$pageSubheading = 'The latest sports news, achievements and competitions at NSBM.';

// 1. Locate database connection
require_once __DIR__ . '/../config/database.php';

// Relative path from pages/ folder to assets/css/page_style.css
$pageCSS = '../assets/css/page_style.css';

// Sports main section category ID (3000)
$sportsParentId = 3000;

// 2. Capture filter inputs from the URL query string
$searchKeyword = trim($_GET['search'] ?? '');
$selectedSubcategory = $_GET['subcategory'] ?? 'all';
$selectedSport = $_GET['sport'] ?? 'all';
$selectedFaculty = $_GET['faculty'] ?? 'all';
$selectedMonth = $_GET['month'] ?? 'all';
$sortOrder = $_GET['sort'] ?? 'newest';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$postsPerPage = 6;

// 3. Build common WHERE clause & parameters securely
$whereClauses = ["TRIM(LOWER(posts.status)) = 'published'", "posts.category_id = :parent_cat"];
$params = ['parent_cat' => $sportsParentId];

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

if ($selectedSubcategory !== 'all' && is_numeric($selectedSubcategory)) {
    $whereClauses[] = "posts.subcategory_id = :subcat_id";
    $params['subcat_id'] = $selectedSubcategory;
}

if ($selectedSport !== 'all' && is_numeric($selectedSport)) {
    $whereClauses[] = "posts.sport_id = :sport_id";
    $params['sport_id'] = $selectedSport;
}

if ($selectedFaculty !== 'all' && is_numeric($selectedFaculty)) {
    $whereClauses[] = "posts.faculty_id = :faculty_id";
    $params['faculty_id'] = $selectedFaculty;
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

// 5. Build main posts query
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
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\PDOException $e) {
    error_log("Main Query Error: " . $e->getMessage());
    $posts = [];
}

// 6. Fetch Dropdown Data
try {
    // Subcategories under Sports (3000)
    $subCatStmt = $pdo->prepare("SELECT * FROM subcategories WHERE category_id = :parent_id ORDER BY subcategory_name ASC");
    $subCatStmt->execute(['parent_id' => $sportsParentId]);
    $allSubcategories = $subCatStmt->fetchAll(PDO::FETCH_ASSOC);

    // Sports list
    $sportStmt = $pdo->query("SELECT * FROM sports ORDER BY sport_name ASC");
    $allSports = $sportStmt->fetchAll(PDO::FETCH_ASSOC);

    // Faculties
    $facultyStmt = $pdo->query("SELECT * FROM faculties ORDER BY faculty_name ASC");
    $allFaculties = $facultyStmt->fetchAll(PDO::FETCH_ASSOC);

    // Months scoped to sports
    $monthStmt = $pdo->prepare("
        SELECT DISTINCT DATE_FORMAT(created_at, '%Y-%m') AS month_val, 
               DATE_FORMAT(created_at, '%M %Y') AS month_label 
        FROM posts 
        WHERE TRIM(LOWER(status)) = 'published' AND category_id = :parent_cat
        ORDER BY created_at DESC
    ");
    $monthStmt->execute(['parent_cat' => $sportsParentId]);
    $allMonths = $monthStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\PDOException $e) {
    $allSubcategories = [];
    $allSports = [];
    $allFaculties = [];
    $allMonths = [];
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
                            <img src="<?= htmlspecialchars($post['cover_image'] ?? 'https://via.placeholder.com/400x200') ?>" class="card-img-top" alt="Cover Image">
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
                <p class="text-muted text-center py-4">No published sports stories found matching your filter criteria.</p>
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

// Include header and navbar
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php'; 
?>

<main class="container py-5">
    <nav aria-label="breadcrumb" class="mb-2">
        <ol class="breadcrumb mb-1">
            <li class="breadcrumb-item"><a href="../index.php" class="text-decoration-none text-muted">Home</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($pageHeading) ?></li>
        </ol>
    </nav>

    <div class="mb-4">
        <h1 class="fw-bold text-dark mb-1"><?= htmlspecialchars($pageHeading) ?></h1>
        <p class="text-muted"><?= htmlspecialchars($pageSubheading) ?></p>
    </div>

    <!-- Filter Form (Total = 12 cols on lg) -->
    <form method="GET" action="" id="filterForm" class="card p-3 mb-4">
        <div class="row g-2 align-items-center">
            <div class="col-lg-3 col-md-12">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="search" value="<?= htmlspecialchars($searchKeyword) ?>" class="form-control border-start-0 ps-0 shadow-none" placeholder="Search sports news" autocomplete="off">
                </div>
            </div>

            <!-- Subcategories Dropdown -->
            <div class="col-lg-2 col-md-3">
                <select name="subcategory" class="form-select text-muted shadow-none">
                    <option value="all">Categories</option>
                    <?php foreach ($allSubcategories as $sub): ?>
                        <option value="<?= $sub['subcategory_id'] ?>" <?= ($selectedSubcategory == $sub['subcategory_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($sub['subcategory_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Sport Dropdown -->
            <div class="col-lg-2 col-md-3">
                <select name="sport" class="form-select text-muted shadow-none">
                    <option value="all">Sports</option>
                    <?php foreach ($allSports as $sp): ?>
                        <option value="<?= $sp['sport_id'] ?>" <?= ($selectedSport == $sp['sport_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($sp['sport_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Faculty Dropdown -->
            <div class="col-lg-2 col-md-3">
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
            <div class="col-lg-2 col-md-3">
                <select name="month" class="form-select text-muted shadow-none">
                    <option value="all" <?= ($selectedMonth === 'all') ? 'selected' : '' ?>>All Months</option>
                    <?php foreach ($allMonths as $m): ?>
                        <option value="<?= $m['month_val'] ?>" <?= ($selectedMonth === $m['month_val']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($m['month_label']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-lg-1 col-md-12">
                <button type="submit" class="btn btn-success w-100 fw-semibold px-2 text-nowrap">Search</button>
            </div>
        </div>
    </form>

    <!-- Sort Form -->
    <form method="GET" action="" id="sortForm" class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <input type="hidden" name="search" value="<?= htmlspecialchars($searchKeyword) ?>">
        <input type="hidden" name="subcategory" value="<?= htmlspecialchars($selectedSubcategory) ?>">
        <input type="hidden" name="sport" value="<?= htmlspecialchars($selectedSport) ?>">
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

    <!-- Main Posts Grid -->
    <div id="postsContainer">
        <?php renderPostsContent($posts, $totalPosts, $postsPerPage, $page, $totalPages); ?>
    </div>

    <!-- Upcoming Sports Events Section -->
    <?php
    try {
        $upcomingSql = "
            SELECT e.*, p.title, p.summary, p.cover_image, p.post_id
            FROM events e
            INNER JOIN posts p ON e.post_id = p.post_id
            WHERE TRIM(LOWER(p.status)) = 'published'
              AND e.event_date >= CURDATE()
              AND (p.subcategory_id = 38 OR p.sport_id IS NOT NULL)
            ORDER BY e.event_date ASC, e.start_time ASC
            LIMIT 3
        ";
        $upcomingStmt = $pdo->query($upcomingSql);
        $upcomingEvents = $upcomingStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (\PDOException $e) {
        $upcomingEvents = [];
    }
    ?>

    <section class="mt-5 pt-5 border-top">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold text-dark mb-1">Upcoming Sports Events</h2>
                <p class="text-muted small mb-0">Don't miss out on upcoming matches, tournaments, and athletic meets.</p>
            </div>
            <a href="events.php" class="text-success text-decoration-none fw-semibold">All events &rarr;</a>
        </div>

        <div class="row row-cols-1 row-cols-md-3 g-4">
            <?php if (count($upcomingEvents) > 0): ?>
                <?php foreach ($upcomingEvents as $ev): ?>
                    <div class="col">
                        <a href="news_details.php?id=<?= $ev['post_id'] ?>" class="text-decoration-none h-100 d-block">
                            <div class="card h-100 card-hover-effect border-0 shadow-sm">
                                <img src="<?= htmlspecialchars($ev['cover_image'] ?? 'https://via.placeholder.com/400x200') ?>" class="card-img-top" alt="Event Cover">
                                <div class="card-body d-flex flex-column">
                                    <div class="mb-2 d-flex justify-content-between align-items-center">
                                        <span class="badge bg-success px-2 py-1 rounded">UPCOMING EVENT</span>
                                        <span class="text-muted small fw-semibold"><?= date('d M Y', strtotime($ev['event_date'])) ?></span>
                                    </div>
                                    <h5 class="card-title fw-bold text-dark"><?= htmlspecialchars($ev['title']) ?></h5>
                                    <p class="card-text text-muted small flex-grow-1"><?= htmlspecialchars($ev['summary']) ?></p>
                                    <div class="mt-3 pt-2 border-top small text-muted">
                                        <div><strong>Time:</strong> <?= date('h:i A', strtotime($ev['start_time'])) ?></div>
                                        <div><strong>Venue:</strong> <?= htmlspecialchars($ev['venue']) ?></div>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="card p-4 text-center bg-light border-0">
                        <p class="text-muted mb-0">No upcoming sports events scheduled right now. Check back soon!</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const filterForm = document.getElementById('filterForm');
    const sortForm = document.getElementById('sortForm');
    const searchInput = filterForm.querySelector('input[name="search"]');
    const subcategorySelect = filterForm.querySelector('select[name="subcategory"]');
    const sportSelect = filterForm.querySelector('select[name="sport"]');
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
        const sportVal = sportSelect.value;
        const facultyVal = facultySelect.value;
        const monthVal = monthSelect.value;
        const sortVal = sortSelect.value;

        sortForm.querySelector('input[name="search"]').value = searchVal;
        sortForm.querySelector('input[name="subcategory"]').value = subcategoryVal;
        sortForm.querySelector('input[name="sport"]').value = sportVal;
        sortForm.querySelector('input[name="faculty"]').value = facultyVal;
        sortForm.querySelector('input[name="month"]').value = monthVal;
        sortForm.querySelector('input[name="page"]').value = currentPage;

        const params = new URLSearchParams({
            search: searchVal,
            subcategory: subcategoryVal,
            sport: sportVal,
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
                    sport: sportVal,
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
    sportSelect.addEventListener('change', () => fetchFilteredResults(1));
    facultySelect.addEventListener('change', () => fetchFilteredResults(1));
    monthSelect.addEventListener('change', () => fetchFilteredResults(1));
    sortSelect.addEventListener('change', () => fetchFilteredResults(1));
});
</script>

<?php 
// Include footer
include __DIR__ . '/../includes/footer.php';
?>
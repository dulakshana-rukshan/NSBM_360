<?php
// --- EVENTS PAGE CONFIGURATION ---
$pageTitle = 'Campus Events | NSBM 360';
$activePage = 'events';
$pageHeading = 'Be part of what\'s next.';
$pageSubheading = 'Your campus calendar for ideas, connections and unforgettable moments.';

// 1. Locate database connection
require_once __DIR__ . '/../config/database.php';

// Relative path from pages/ folder to assets/css/page_style.css
$pageCSS = '../assets/css/page_style.css';

// Events main section category ID (2000)
$eventsParentId = 2000;

// 2. Capture filter inputs from the URL query string
$searchKeyword = trim($_GET['search'] ?? '');
$selectedEventType = $_GET['event_type'] ?? 'all';
$selectedFaculty = $_GET['faculty'] ?? 'all';
$selectedClub = $_GET['club'] ?? 'all';
$selectedTimeframe = $_GET['timeframe'] ?? 'all'; // 'all', 'upcoming', 'past'
$sortOrder = $_GET['sort'] ?? 'newest';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$postsPerPage = 6;

// 3. Build common WHERE clause & parameters securely
$whereClauses = ["TRIM(LOWER(posts.status)) = 'published'", "posts.category_id = :parent_cat"];
$params = ['parent_cat' => $eventsParentId];

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

if ($selectedEventType !== 'all' && is_numeric($selectedEventType)) {
    $whereClauses[] = "posts.subcategory_id = :subcat_id";
    $params['subcat_id'] = $selectedEventType;
}

if ($selectedFaculty !== 'all' && is_numeric($selectedFaculty)) {
    $whereClauses[] = "posts.faculty_id = :faculty_id";
    $params['faculty_id'] = $selectedFaculty;
}

if ($selectedClub !== 'all' && is_numeric($selectedClub)) {
    $whereClauses[] = "posts.club_id = :club_id";
    $params['club_id'] = $selectedClub;
}

// Filter by Upcoming or Past using events.event_date
if ($selectedTimeframe === 'upcoming') {
    $whereClauses[] = "events.event_date >= CURDATE()";
} elseif ($selectedTimeframe === 'past') {
    $whereClauses[] = "events.event_date < CURDATE()";
}

$whereString = implode(" AND ", $whereClauses);

// 4. Count total matching records using LEFT JOIN on subcategories and events
try {
    $countSql = "
        SELECT COUNT(*) 
        FROM posts 
        LEFT JOIN subcategories ON posts.subcategory_id = subcategories.subcategory_id 
        LEFT JOIN events ON posts.post_id = events.post_id
        WHERE " . $whereString;
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

// 5. Build main events query
$sql = "
    SELECT posts.*, 
           COALESCE(subcategories.subcategory_name, 'Event') AS event_type_name,
           events.event_date,
           events.start_time,
           events.venue
    FROM posts 
    LEFT JOIN subcategories ON posts.subcategory_id = subcategories.subcategory_id 
    LEFT JOIN events ON posts.post_id = events.post_id
    WHERE " . $whereString;

if ($sortOrder === 'oldest') {
    $sql .= " ORDER BY COALESCE(events.event_date, posts.created_at) ASC";
} else {
    $sql .= " ORDER BY COALESCE(events.event_date, posts.created_at) DESC";
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
    // Event Types (Subcategories under Events 2000)
    $eventTypeStmt = $pdo->prepare("SELECT * FROM subcategories WHERE category_id = :parent_id ORDER BY subcategory_name ASC");
    $eventTypeStmt->execute(['parent_id' => $eventsParentId]);
    $allEventTypes = $eventTypeStmt->fetchAll(PDO::FETCH_ASSOC);

    // Faculties
    $facultyStmt = $pdo->query("SELECT * FROM faculties ORDER BY faculty_name ASC");
    $allFaculties = $facultyStmt->fetchAll(PDO::FETCH_ASSOC);

    // Organizing Clubs (all active clubs)
    $clubStmt = $pdo->query("SELECT * FROM clubs WHERE status = 'active' ORDER BY club_name ASC");
    $allClubs = $clubStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\PDOException $e) {
    $allEventTypes = [];
    $allFaculties = [];
    $allClubs = [];
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
                <?php 
                    $displayDate = !empty($post['event_date']) ? $post['event_date'] : $post['created_at'];
                    $isUpcoming = !empty($post['event_date']) && strtotime($post['event_date']) >= strtotime(date('Y-m-d'));
                ?>
                <div class="col">
                    <a href="news_details.php?id=<?= $post['post_id'] ?>" class="text-decoration-none h-100 d-block">
                        <div class="card h-100 card-hover-effect">
                            <img src="<?= htmlspecialchars($post['cover_image'] ?? 'https://via.placeholder.com/400x200') ?>" class="card-img-top" alt="Event Cover">
                            <div class="card-body d-flex flex-column">
                                <div class="mb-2 d-flex justify-content-between align-items-center gap-1 flex-wrap">
                                    <span class="badge bg-primary px-2 py-1 rounded"><?= htmlspecialchars($post['event_type_name']) ?></span>
                                    <?php if (!empty($post['event_date'])): ?>
                                        <span class="badge <?= $isUpcoming ? 'bg-success' : 'bg-secondary' ?> px-2 py-1 rounded">
                                            <?= $isUpcoming ? 'Upcoming' : 'Past' ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <h5 class="card-title fw-bold text-dark"><?= htmlspecialchars($post['title']) ?></h5>
                                <p class="card-text text-muted small flex-grow-1"><?= htmlspecialchars($post['summary']) ?></p>
                                <div class="mt-3 pt-2 border-top">
                                    <p class="text-custom-muted mb-1 small">
                                        <strong>Date:</strong> <?= date('d M Y', strtotime($displayDate)) ?>
                                        <?php if (!empty($post['venue'])): ?>
                                            &bull; <?= htmlspecialchars($post['venue']) ?>
                                        <?php endif; ?>
                                    </p>
                                    <span class="read-story-link">View event &rarr;</span>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12">
                <p class="text-muted text-center py-4">No campus events found matching your filter criteria.</p>
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
            <li class="breadcrumb-item active" aria-current="page">Events</li>
        </ol>
    </nav>

    <div class="mb-4">
        <h1 class="fw-bold text-dark mb-1"><?= htmlspecialchars($pageHeading) ?></h1>
        <p class="text-muted"><?= htmlspecialchars($pageSubheading) ?></p>
    </div>

    <!-- Filter Form (Total = 12 cols on lg) -->
    <form method="GET" action="" id="filterForm" class="card p-3 mb-4">
        <div class="row g-2 align-items-center">
            <!-- Search Input -->
            <div class="col-lg-3 col-md-12">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="search" value="<?= htmlspecialchars($searchKeyword) ?>" class="form-control border-start-0 ps-0 shadow-none" placeholder="Search events" autocomplete="off">
                </div>
            </div>

            <!-- Event Type (Category) Dropdown -->
            <div class="col-lg-2 col-md-3">
                <select name="event_type" class="form-select text-muted shadow-none">
                    <option value="all">Event Types</option>
                    <?php foreach ($allEventTypes as $et): ?>
                        <option value="<?= $et['subcategory_id'] ?>" <?= ($selectedEventType == $et['subcategory_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($et['subcategory_name']) ?>
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

            <!-- Organizing Club Dropdown -->
            <div class="col-lg-2 col-md-3">
                <select name="club" class="form-select text-muted shadow-none">
                    <option value="all">Organizing Clubs</option>
                    <?php foreach ($allClubs as $cl): ?>
                        <option value="<?= $cl['club_id'] ?>" <?= ($selectedClub == $cl['club_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cl['club_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Timeframe Dropdown (All / Upcoming / Past) -->
            <div class="col-lg-2 col-md-3">
                <select name="timeframe" class="form-select text-muted shadow-none">
                    <option value="all" <?= ($selectedTimeframe === 'all') ? 'selected' : '' ?>>All Events</option>
                    <option value="upcoming" <?= ($selectedTimeframe === 'upcoming') ? 'selected' : '' ?>>Upcoming Events</option>
                    <option value="past" <?= ($selectedTimeframe === 'past') ? 'selected' : '' ?>>Past Events</option>
                </select>
            </div>

            <!-- Submit Button -->
            <div class="col-lg-1 col-md-12">
                <button type="submit" class="btn btn-success w-100 fw-semibold px-2 text-nowrap">Search</button>
            </div>
        </div>
    </form>

    <!-- Sort Form -->
    <form method="GET" action="" id="sortForm" class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <input type="hidden" name="search" value="<?= htmlspecialchars($searchKeyword) ?>">
        <input type="hidden" name="event_type" value="<?= htmlspecialchars($selectedEventType) ?>">
        <input type="hidden" name="faculty" value="<?= htmlspecialchars($selectedFaculty) ?>">
        <input type="hidden" name="club" value="<?= htmlspecialchars($selectedClub) ?>">
        <input type="hidden" name="timeframe" value="<?= htmlspecialchars($selectedTimeframe) ?>">
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

    <!-- Main Events Grid -->
    <div id="postsContainer">
        <?php renderPostsContent($posts, $totalPosts, $postsPerPage, $page, $totalPages); ?>
    </div>

    <!-- Upcoming Featured Sports Events Section -->
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
                <h2 class="fw-bold text-dark mb-1">Upcoming Sports & Tournament Highlights</h2>
                <p class="text-muted small mb-0">Don't miss out on upcoming matches, tournaments, and athletic meets.</p>
            </div>
            <a href="sports.php" class="text-success text-decoration-none fw-semibold">Sports hub &rarr;</a>
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
    const eventTypeSelect = filterForm.querySelector('select[name="event_type"]');
    const facultySelect = filterForm.querySelector('select[name="faculty"]');
    const clubSelect = filterForm.querySelector('select[name="club"]');
    const timeframeSelect = filterForm.querySelector('select[name="timeframe"]');
    const sortSelect = sortForm.querySelector('select[name="sort"]');
    const postsContainer = document.getElementById('postsContainer');

    let debounceTimer;
    let currentController = null;
    let currentPage = <?= $page ?>;

    function fetchFilteredResults(targetPage = 1) {
        currentPage = targetPage;
        const searchVal = searchInput.value;
        const eventTypeVal = eventTypeSelect.value;
        const facultyVal = facultySelect.value;
        const clubVal = clubSelect.value;
        const timeframeVal = timeframeSelect.value;
        const sortVal = sortSelect.value;

        sortForm.querySelector('input[name="search"]').value = searchVal;
        sortForm.querySelector('input[name="event_type"]').value = eventTypeVal;
        sortForm.querySelector('input[name="faculty"]').value = facultyVal;
        sortForm.querySelector('input[name="club"]').value = clubVal;
        sortForm.querySelector('input[name="timeframe"]').value = timeframeVal;
        sortForm.querySelector('input[name="page"]').value = currentPage;

        const params = new URLSearchParams({
            search: searchVal,
            event_type: eventTypeVal,
            faculty: facultyVal,
            club: clubVal,
            timeframe: timeframeVal,
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
                    event_type: eventTypeVal,
                    faculty: facultyVal,
                    club: clubVal,
                    timeframe: timeframeVal,
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

    eventTypeSelect.addEventListener('change', () => fetchFilteredResults(1));
    facultySelect.addEventListener('change', () => fetchFilteredResults(1));
    clubSelect.addEventListener('change', () => fetchFilteredResults(1));
    timeframeSelect.addEventListener('change', () => fetchFilteredResults(1));
    sortSelect.addEventListener('change', () => fetchFilteredResults(1));
});
</script>

<?php 
// Include footer
include __DIR__ . '/../includes/footer.php';
?>
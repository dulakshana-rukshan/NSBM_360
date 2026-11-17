<?php
// NSBM 360 - Clubs listing page
// Keep using the existing PDO connection from the project.
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'Clubs | NSBM 360';
$activePage = 'clubs';
$pageCSS = '../assets/css/clubs.css';

// Escape database values before displaying them in HTML.
function clubsEscape($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

// Read an image path saved in the database. Block unsafe URL schemes.
function clubsImagePath($value): string {
    $path = trim((string)($value ?? ''));
    if ($path === '') return '';
    if (preg_match('~^https?://~i', $path)) return $path;
    if (str_starts_with($path, '//') || preg_match('~^[a-z][a-z0-9+.-]*:~i', $path)) return '';
    if (str_contains($path, '..') || str_contains($path, '\\')) return '';
    return $path;
}

// 1. Read search, status, sorting and pagination parameters.
$searchKeyword = trim((string)($_GET['search'] ?? ''));
$searchKeyword = substr($searchKeyword, 0, 100);
$statusFilter = (string)($_GET['status'] ?? 'all');
if (!in_array($statusFilter, ['all', 'active', 'inactive'], true)) {
    $statusFilter = 'all';
}
$sortOrder = (($_GET['sort'] ?? 'newest') === 'oldest') ? 'oldest' : 'newest';
$page = max(1, (int)($_GET['page'] ?? 1));
$clubsPerPage = 6;

// 2. Build safe parameterized filters.
$whereClauses = ['1 = 1'];
$params = [];

if ($searchKeyword !== '') {
    $whereClauses[] = '(LOWER(clubs.club_name) LIKE :name_keyword OR LOWER(clubs.description) LIKE :description_keyword)';
    $keyword = '%' . strtolower($searchKeyword) . '%';
    $params['name_keyword'] = $keyword;
    $params['description_keyword'] = $keyword;
}
if ($statusFilter !== 'all') {
    $whereClauses[] = 'clubs.status = :status';
    $params['status'] = $statusFilter;
}
$whereSql = implode(' AND ', $whereClauses);

// 3. Count records for pagination and load only the current page.
$clubs = [];
$totalClubs = 0;
$totalPages = 1;
try {
    $countStatement = $pdo->prepare("SELECT COUNT(*) FROM clubs WHERE $whereSql");
    $countStatement->execute($params);
    $totalClubs = (int)$countStatement->fetchColumn();

    $totalPages = max(1, (int)ceil($totalClubs / $clubsPerPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $clubsPerPage;

    $direction = $sortOrder === 'oldest' ? 'ASC' : 'DESC';
    $sql = "SELECT clubs.* FROM clubs WHERE $whereSql ORDER BY clubs.created_at $direction, clubs.club_id $direction LIMIT :limit OFFSET :offset";
    $statement = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $statement->bindValue(':' . $key, $value);
    }
    $statement->bindValue(':limit', $clubsPerPage, PDO::PARAM_INT);
    $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
    $statement->execute();
    $clubs = $statement->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $exception) {
    error_log('Clubs query failed: ' . $exception->getMessage());
}
$totalPages = max(1, (int)ceil($totalClubs / $clubsPerPage));

// 4. Render cards and pagination. Called on full loads and AJAX loads.
function renderClubsContent(array $clubs, int $totalClubs, int $clubsPerPage, int $page, int $totalPages): void {
    $first = $totalClubs ? (($page - 1) * $clubsPerPage + 1) : 0;
    $last = min($page * $clubsPerPage, $totalClubs);
    $startPage = max(1, min($page - 1, max(1, $totalPages - 3)));
    $endPage = min($totalPages, $startPage + 3);
    ?>
    <div class="clubs-meta-bar">
        <span>Showing <?= $first ?>–<?= $last ?> of <?= $totalClubs ?> clubs</span>
    </div>

    <div class="clubs-grid">
        <?php if (!empty($clubs)): ?>
            <?php foreach ($clubs as $club): ?>
                <?php
                // 'logo' = small profile photo. 'banner_image' = large cover.
                // If banner_image has not been added yet, show a styled fallback.
                $logo = clubsImagePath($club['logo'] ?? '');
                $banner = clubsImagePath($club['banner_image'] ?? '');
                $name = (string)($club['club_name'] ?? 'Club');
                $initials = strtoupper(substr($name, 0, 2));
                $detailUrl = "club_detail.php?id=" . (int)$club['club_id'];
                ?>
                <article class="club-card">
    <!-- Large banner: Clickable wrapper for banner photo -->
    <div class="card-image-wrapper">
        <a href="<?= $detailUrl ?>" 
           onclick="checkLoginAndExplore(event, '<?= $detailUrl ?>')"
           style="display: block; width: 100%; height: 100%;">
            <div class="club-banner-fallback" aria-hidden="true"></div>
            <?php if ($banner !== ''): ?>
                <img src="../uploads/clubs/<?= clubsEscape($banner) ?>"
                     alt="<?= clubsEscape($name) ?> banner"
                     class="club-banner" loading="lazy"
                     onerror="this.remove()">
            <?php endif; ?>
        </a>
    </div>

    <div class="card-body">
        <div class="card-top-row">
            <!-- Club logo -->
            <div class="club-avatar club-avatar--green-ring">
                <span class="club-default-icon"><?= clubsEscape($initials) ?></span>
                <?php if ($logo !== ''): ?>
                    <img src="../uploads/clubs/<?= clubsEscape($logo) ?>"
                         alt="<?= clubsEscape($name) ?> logo"
                         class="club-profile-img" loading="lazy"
                         onerror="this.style.display='none'">
                <?php endif; ?>
            </div>
            <div class="club-titles">
                <!-- Club Name: Clickable -->
                <h3 class="club-name">
                    <a href="<?= $detailUrl ?>" 
                       onclick="checkLoginAndExplore(event, '<?= $detailUrl ?>')"
                       style="color: inherit; text-decoration: none;">
                        <?= clubsEscape($name) ?>
                    </a>
                </h3>
                <span class="club-category">Student Community</span>
            </div>
        </div>

        <p class="club-desc"><?= clubsEscape($club['description'] ?? '') ?></p>

        <div class="club-footer-row">
            <span class="clubs-members">Active Community</span>
            <?php if (($club['status'] ?? 'active') === 'active'): ?>
                <span class="badge-open">OPEN TO JOIN</span>
            <?php else: ?>
                <span class="badge-inactive">INACTIVE</span>
            <?php endif; ?>
        </div>

        <a class="explore-link" href="<?= $detailUrl ?>"
           onclick="checkLoginAndExplore(event, '<?= $detailUrl ?>')">
            Explore club <span aria-hidden="true">&rarr;</span>
        </a>
    </div>
</article>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="clubs-empty-state">No clubs found matching your search.</div>
        <?php endif; ?>
    </div>

    <div class="clubs-pagination-bar">
        <span class="clubs-page-count">Page <?= $page ?> of <?= $totalPages ?></span>
        <nav aria-label="Clubs pages">
            <ul class="clubs-pagination">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a href="#" class="pagination-link page-nav" data-page="<?= $page - 1 ?>"
                       <?= $page <= 1 ? 'aria-disabled="true" tabindex="-1"' : '' ?>>&larr; Previous</a>
                </li>
                <?php for ($number = $startPage; $number <= $endPage; $number++): ?>
                    <li class="page-item <?= $number === $page ? 'active' : '' ?>">
                        <a href="#" class="pagination-link page-number"
                           data-page="<?= $number ?>" <?= $number === $page ? 'aria-current="page"' : '' ?>><?= $number ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a href="#" class="pagination-link page-nav" data-page="<?= $page + 1 ?>"
                       <?= $page >= $totalPages ? 'aria-disabled="true" tabindex="-1"' : '' ?>>Next &rarr;</a>
                </li>
            </ul>
        </nav>
    </div>
    <?php
}

// 5. AJAX requests return only the cards and pagination.
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    renderClubsContent($clubs, $totalClubs, $clubsPerPage, $page, $totalPages);
    exit;
}

// 6. Normal page includes the project's existing shared header and footer.
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>
<!-- Ensure club CSS loads, even if the shared header ignores $pageCSS. -->
<link rel="stylesheet" href="../assets/css/clubs.css?v=5">

<main class="clubs-main-container">
    <section class="clubs-header-section">
        <div class="clubs-breadcrumb">
            <a href="index.php">Home</a><span>/</span><span>Clubs</span>
        </div>
        <h1 class="clubs-page-title">Find your people.</h1>
        <p class="clubs-page-subtitle">Explore student-led communities. Try something new. Make campus your own.</p>
    </section>

    <!-- Search form -->
    <form method="GET" action="clubs.php" id="filterForm" class="clubs-filter-bar">
        <div class="search-input-wrapper">
            <span class="search-icon" aria-hidden="true">&#9906;</span>
            <input type="search" name="search" class="search-input" autocomplete="off"
                   value="<?= clubsEscape($searchKeyword) ?>"
                   placeholder="Search clubs by name or interest">
        </div>
        <div class="filter-dropdowns">
            <select name="status" class="filter-select" aria-label="Filter by club status">
                <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Status</option>
                <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
        </div>
        <button type="submit" class="search-btn">Search</button>
    </form>

    <!-- Keep sorting separate from filtering, but preserve the filter values. -->
    <form method="GET" action="clubs.php" id="sortForm" class="clubs-sort-bar">
        <input type="hidden" name="search" value="<?= clubsEscape($searchKeyword) ?>">
        <input type="hidden" name="status" value="<?= clubsEscape($statusFilter) ?>">
        <input type="hidden" name="page" value="<?= $page ?>">
        <span>A community for every interest and ambition</span>
        <label class="sort-wrapper">Sort by:
            <select name="sort" class="filter-select" aria-label="Sort clubs">
                <option value="newest" <?= $sortOrder === 'newest' ? 'selected' : '' ?>>Newest first</option>
                <option value="oldest" <?= $sortOrder === 'oldest' ? 'selected' : '' ?>>Oldest first</option>
            </select>
        </label>
    </form>

    <!-- Live results container -->
    <div id="clubsContainer" aria-live="polite">
        <?php renderClubsContent($clubs, $totalClubs, $clubsPerPage, $page, $totalPages); ?>
    </div>
</main>

<script>
// 7. Live search, status filter, sort and pagination without reloading.
document.addEventListener('DOMContentLoaded', function () {
    const filterForm = document.getElementById('filterForm');
    const sortForm = document.getElementById('sortForm');
    const container = document.getElementById('clubsContainer');
    const searchInput = filterForm.querySelector('[name="search"]');
    const statusInput = filterForm.querySelector('[name="status"]');
    const sortInput = sortForm.querySelector('[name="sort"]');
    let timer;
    let controller;

    function loadClubs(page = 1) {
        const params = new URLSearchParams({
            search: searchInput.value,
            status: statusInput.value,
            sort: sortInput.value,
            page: String(page)
        });
        sortForm.querySelector('[name="search"]').value = searchInput.value;
        sortForm.querySelector('[name="status"]').value = statusInput.value;
        sortForm.querySelector('[name="page"]').value = page;

        if (controller) controller.abort();
        controller = new AbortController();
        const ajaxParams = new URLSearchParams(params);
        ajaxParams.set('ajax', '1');

        fetch('clubs.php?' + ajaxParams.toString(), { signal: controller.signal })
            .then(response => {
                if (!response.ok) throw new Error('Unable to load clubs');
                return response.text();
            })
            .then(html => {
                container.innerHTML = html;
                window.history.replaceState({}, '', 'clubs.php?' + params.toString());
            })
            .catch(error => {
                if (error.name !== 'AbortError') console.error(error);
            });
    }

    filterForm.addEventListener('submit', function (event) {
        event.preventDefault();
        loadClubs(1);
    });
    searchInput.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(() => loadClubs(1), 300);
    });
    statusInput.addEventListener('change', () => loadClubs(1));
    sortInput.addEventListener('change', () => loadClubs(1));
    sortForm.addEventListener('submit', function (event) {
        event.preventDefault();
        loadClubs(1);
    });
    // Event delegation also works for links added by AJAX.
    container.addEventListener('click', function (event) {
        const link = event.target.closest('.pagination-link');
        if (!link) return;
        event.preventDefault();
        if (link.closest('.disabled, .active')) return;
        const targetPage = Number(link.dataset.page);
        if (Number.isInteger(targetPage) && targetPage > 0) {
            loadClubs(targetPage);
            container.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>

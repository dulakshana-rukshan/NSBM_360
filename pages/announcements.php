
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

$activePage = 'announcements';
$pageTitle = 'Announcements | NSBM 360';
$pageCSS = '/NSBM_360/assets/css/announcements.css';

// Read filters
$search = trim($_GET['search'] ?? '');
$faculty = $_GET['faculty'] ?? '';
$importance = $_GET['importance'] ?? '';
$sort = $_GET['sort'] ?? 'newest';

// Validate filters
if (!ctype_digit((string)$faculty)) {
    $faculty = '';
}

if (!in_array($importance, ['', '1', '0'], true)) {
    $importance = '';
}

if (!in_array($sort, ['newest', 'oldest'], true)) {
    $sort = 'newest';
}

// Load faculties
$facultyStmt = $pdo->query(
    "SELECT faculty_id, faculty_name
     FROM faculties
     ORDER BY faculty_name ASC"
);

$faculties = $facultyStmt->fetchAll();

// Build dynamic query
$sql = "
    SELECT
        a.announcement_id,
        a.title,
        a.content,
        a.is_important,
        a.published_at,
        a.created_at,
        a.faculty_id,
        f.faculty_name
    FROM announcements a
    LEFT JOIN faculties f
        ON a.faculty_id = f.faculty_id
    WHERE a.status = 'published'
      AND (a.expires_at IS NULL OR a.expires_at > NOW())
";

$sql = "
    SELECT
        a.announcement_id,
        a.title,
        a.content,
        a.image,
        a.is_important,
        a.published_at,
        a.created_at,
        a.faculty_id,
        f.faculty_name
    FROM announcements a
    LEFT JOIN faculties f
        ON a.faculty_id = f.faculty_id
    WHERE a.status = 'published'
      AND (a.expires_at IS NULL OR a.expires_at > NOW())
";

$params = [];

if ($search !== '') {
    $sql .= " AND (a.title LIKE :title_search
                    OR a.content LIKE :content_search)";

    $params['title_search'] = '%' . $search . '%';
    $params['content_search'] = '%' . $search . '%';
}

if ($faculty !== '') {
    $sql .= " AND (a.faculty_id = :faculty_id
                    OR a.faculty_id IS NULL)";
    $params['faculty_id'] = (int)$faculty;
}

if ($importance !== '') {
    $sql .= " AND a.is_important = :importance";
    $params['importance'] = (int)$importance;
}

if ($sort === 'oldest') {
    $sql .= " ORDER BY COALESCE(a.published_at, a.created_at) ASC,
                      a.announcement_id ASC";
} else {
    $sql .= " ORDER BY COALESCE(a.published_at, a.created_at) DESC,
                      a.announcement_id DESC";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$announcements = $stmt->fetchAll();

function announcementEscape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<main class="page-content">

    <section class="announcements-page">

        <div class="announcements-header">
            <h1>Campus Announcements</h1>
            <p>
                Stay updated with important campus
                announcements and notices.
            </p>
        </div>

        <!-- SEARCH & FILTER -->
        <form class="announcements-toolbar"
              method="GET"
              action="announcements.php">

            <div class="announcement-search">
                <i class="bi bi-search"></i>

                <input
                    type="search"
                    name="search"
                    placeholder="Search announcements..."
                    value="<?= announcementEscape($search) ?>">
            </div>

            <select name="faculty"
                    aria-label="Filter by faculty">

                <option value="">All Faculties</option>

                <?php foreach ($faculties as $f): ?>
                    <option
                        value="<?= (int)$f['faculty_id'] ?>"
                        <?= (string)$faculty === (string)$f['faculty_id']
                            ? 'selected' : '' ?>>

                        <?= announcementEscape($f['faculty_name']) ?>

                    </option>
                <?php endforeach; ?>

            </select>

            <select name="importance"
                    aria-label="Filter by importance">

                <option value="">All Types</option>
                <option value="1"
                    <?= $importance === '1' ? 'selected' : '' ?>>
                    Important
                </option>
                <option value="0"
                    <?= $importance === '0' ? 'selected' : '' ?>>
                    Normal
                </option>
            </select>

            <select name="sort"
                    aria-label="Sort by date">

                <option value="newest"
                    <?= $sort === 'newest' ? 'selected' : '' ?>>
                    Newest First
                </option>

                <option value="oldest"
                    <?= $sort === 'oldest' ? 'selected' : '' ?>>
                    Oldest First
                </option>

            </select>

            <button type="submit" class="announcement-filter-btn">
                Apply
            </button>

        </form>

        <!-- RESULTS -->
        <div class="announcements-results">
            <span>
                <?= count($announcements) ?>
                announcement(s) found
            </span>
        </div>

        <!-- DYNAMIC ANNOUNCEMENTS -->
        <div class="announcements-list">

            <?php foreach ($announcements as $announcement): ?>

                <article class="announcement-card">

                    <div class="announcement-card-top">

                        <?php if ((int)$announcement['is_important'] === 1): ?>
                            <span class="announcement-badge important">
                                IMPORTANT
                            </span>
                        <?php else: ?>
                            <span class="announcement-badge general">
                                GENERAL
                            </span>
                        <?php endif; ?>

                        <span class="announcement-date">
                            <i class="bi bi-calendar3"></i>

                            <?= date(
                                'M j, Y',
                                strtotime(
                                    $announcement['published_at']
                                    ?? $announcement['created_at']
                                )
                            ) ?>
                        </span>

                    </div>

                    
<div class="announcement-card-content">

    <div class="announcement-card-text">

        <h2>
            <?= announcementEscape($announcement['title']) ?>
        </h2>

        <p>
            <?= nl2br(
                announcementEscape($announcement['content'])
            ) ?>
        </p>

        </div>

            <?php if (!empty($announcement['image'])): ?>

                <?php
                $imagePath = trim($announcement['image']);

                // Handle paths saved relative to the project.
                if (!preg_match('~^https?://~i', $imagePath)) {
                    $imagePath = '/NSBM_360/' . ltrim($imagePath, '/');
                }
                ?>

                <div class="announcement-card-image">
                    <img
                        src="<?= announcementEscape($imagePath) ?>"
                        alt="Image for <?= announcementEscape($announcement['title']) ?>"
                        loading="lazy"
                    >
                </div>

            <?php endif; ?>

        </div>


                    <div class="announcement-card-bottom">
                        <span>
                            <i class="bi bi-mortarboard"></i>

                            <?= announcementEscape(
                                $announcement['faculty_name']
                                ?? 'All Faculties'
                            ) ?>
                        </span>
                    </div>

                </article>

            <?php endforeach; ?>

        </div>

        <?php if (empty($announcements)): ?>
            <div class="announcements-empty">
                <i class="bi bi-inbox"></i>
                <p>No announcements found.</p>
            </div>
        <?php endif; ?>

    </section>

</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>

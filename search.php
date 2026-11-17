
<?php

require_once __DIR__ . '/config/database.php';

//Read and validate search filters from the URL
$search = trim((string)($_GET['q'] ?? ''));
$search = substr($search, 0, 100);
$type = (string)($_GET['type'] ?? 'all');
$time = (string)($_GET['time'] ?? 'any');
$sort = (string)($_GET['sort'] ?? 'relevant');
$page = max(1, (int)($_GET['page'] ?? 1));

$types = ['all', 'news', 'events', 'clubs', 'academic', 'announcements'];
$times = ['upcoming', 'any', 'week', 'month'];
$sorts = ['relevant', 'newest', 'oldest'];

if (!in_array($type, $types, true)) $type = 'all';
if (!in_array($time, $times, true)) $time = 'any';
if (!in_array($sort, $sorts, true)) $sort = 'relevant';

//Reusable helper functions
function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function excerpt($text, int $length = 130): string {
    $text = trim(preg_replace('/\s+/', ' ', strip_tags((string)$text)));
    return strlen($text) > $length ? substr($text, 0, $length - 3) . '...' : $text;
}

function formattedDate($date): string {
    $stamp = strtotime((string)$date);
    return $stamp ? date('d M Y', $stamp) : '';
}

//Only allow normal image paths or HTTPS/HTTP links stored in the database
function photoPath($value): string {
    $path = trim((string)$value);

    if (
        $path === '' ||
        preg_match('~^(?:javascript:|data:|//)~i', $path) ||
        str_contains($path, '..')
    ) {
        return '';
    }

    return $path;
}

//Build safe get links while keeping the search term and selected filters
function linkTo(array $changes = []): string {
    global $search, $type, $time, $sort;

    return '?' . http_build_query(array_merge([
        'q' => $search,
        'type' => $type,
        'time' => $time,
        'sort' => $sort
    ], $changes));
}

//Fetch records using the teams PDO database connection
function getRows(PDO $pdo, string $sql, array $params = []): array {
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

$results = [];
$error = '';

//Get published posts, events, active clubs, and published announcements
try {
    //$pdo comes from config/database.php.
    $keyword = '%' . $search . '%';

    //POSTS - event details come from events, names from categories/users
    $sql = "SELECT p.post_id, p.title, p.summary, p.content, p.cover_image,
                   p.created_at, p.published_at, c.category_name,
                   u.first_name, u.last_name, e.event_id, e.event_date,
                   e.start_time, e.venue, e.registration_link
            FROM posts p
            JOIN categories c ON p.category_id = c.category_id
            JOIN users u ON p.created_by = u.user_id
            LEFT JOIN events e ON e.post_id = p.post_id
            WHERE p.status = 'published'";

    $params = [];

    if ($search !== '') {
        $sql .= " AND (p.title LIKE ? OR p.summary LIKE ? OR p.content LIKE ? OR c.category_name LIKE ?)";
        $params = array_fill(0, 4, $keyword);
    }

    foreach (getRows($pdo, $sql, $params) as $row) {
        $category = (string)$row['category_name'];
        $isEvent = $row['event_id'] !== null || stripos($category, 'event') !== false;
        $kind = $isEvent
            ? 'events'
            : (stripos($category, 'academic') !== false ? 'academic' : 'news');

        $published = $row['published_at'] ?: $row['created_at'];
        $author = trim($row['first_name'] . ' ' . $row['last_name']);
        $meta = $category . ' · ' . formattedDate($published) . ' · ' . $author;

        if ($isEvent && $row['event_date']) {
            $meta = 'Event ' . formattedDate($row['event_date']) . ' · ' . $row['venue'];
        }

        $results[] = [
            'key' => 'post-' . $row['post_id'],
            'kind' => $kind,
            'label' => $isEvent ? 'EVENT' : strtoupper($category),
            'title' => $row['title'],
            'summary' => $row['summary'] ?: excerpt($row['content']),
            'content' => $row['content'],
            'image' => $row['cover_image'],
            'date' => $published,
            'meta' => $meta,
            'event_date' => $row['event_date'],
            'start_time' => $row['start_time'],
            'venue' => $row['venue'],
            'registration_link' => $row['registration_link']
        ];
    }

    //CLUBS - there is no member count field in the clubs table
    $sql = "SELECT club_id, club_name, description, logo, status, created_at
            FROM clubs WHERE status = 'active'";

    $params = [];

    if ($search !== '') {
        $sql .= " AND (club_name LIKE ? OR description LIKE ?)";
        $params = [$keyword, $keyword];
    }

    foreach (getRows($pdo, $sql, $params) as $row) {
        $results[] = [
            'key' => 'club-' . $row['club_id'],
            'kind' => 'clubs',
            'label' => 'CLUB',
            'title' => $row['club_name'],
            'summary' => excerpt($row['description']),
            'content' => $row['description'],
            'image' => $row['logo'],
            'date' => $row['created_at'],
            'meta' => 'Active club · Added ' . formattedDate($row['created_at'])
        ];
    }

    //Announcement - only show published and non expired items
    $sql = "SELECT a.announcement_id, a.title, a.content, a.image,
                   a.created_at, u.first_name, u.last_name
            FROM announcements a
            JOIN users u ON a.created_by = u.user_id
            WHERE a.status = 'published'
            AND (a.expires_at IS NULL OR a.expires_at > NOW())";

    $params = [];

    if ($search !== '') {
        $sql .= " AND (a.title LIKE ? OR a.content LIKE ?)";
        $params = [$keyword, $keyword];
    }

    foreach (getRows($pdo, $sql, $params) as $row) {
        $results[] = [
            'key' => 'announcement-' . $row['announcement_id'],
            'kind' => 'announcements',
            'label' => 'ANNOUNCEMENT',
            'title' => $row['title'],
            'summary' => excerpt($row['content']),
            'content' => $row['content'],
            'image' => $row['image'],
            'date' => $row['created_at'],
            'meta' => 'Published ' . formattedDate($row['created_at']) .
                      ' · ' . trim($row['first_name'] . ' ' . $row['last_name'])
        ];
    }

} catch (Throwable $exception) {
    //Keep raw database errors out of the public web page
    $error = 'Database connection or query failed. Check MySQL and your nsbm_360 database.';
    error_log($exception->getMessage());
}

//Apply content type and time filters
$cutoff = 0;

if ($time === 'week') $cutoff = strtotime('-7 days');
if ($time === 'month') $cutoff = strtotime('-30 days');
//Upcoming shows only events scheduled for today or later
$today = date('Y-m-d');

//Count matching records for each filter
$filterCounts = array_fill_keys($types, 0);

foreach ($results as $item) {
    if ($time === 'upcoming' && ($item['kind'] !== 'events' || empty($item['event_date']) || $item['event_date'] < $today)) continue;
    if ($cutoff && strtotime((string)$item['date']) < $cutoff) continue;

    $filterCounts['all']++;
    $filterCounts[$item['kind']]++;
}

$results = array_values(array_filter($results, function ($item) use ($type, $cutoff, $time, $today) {
    if ($type !== 'all' && $item['kind'] !== $type) return false;
    if ($time === 'upcoming' && ($item['kind'] !== 'events' || empty($item['event_date']) || $item['event_date'] < $today)) return false;
    if ($cutoff && strtotime((string)$item['date']) < $cutoff) return false;
    return true;
}));

//Sort results title matches rank first for Most Relevant searches
usort($results, function ($a, $b) use ($sort, $search) {
    $aDate = strtotime((string)$a['date']) ?: 0;
    $bDate = strtotime((string)$b['date']) ?: 0;

    if ($sort === 'oldest') return $aDate <=> $bDate;
    if ($sort === 'newest' || $search === '') return $bDate <=> $aDate;

    $aScore = stripos($a['title'], $search) !== false ? 2 : 0;
    $bScore = stripos($b['title'], $search) !== false ? 2 : 0;

    $aScore += stripos($a['summary'], $search) !== false ? 1 : 0;
    $bScore += stripos($b['summary'], $search) !== false ? 1 : 0;

    return ($bScore <=> $aScore) ?: ($bDate <=> $aDate);
});

//Show 6 search results per page
$perPage = 6;
$total = count($results);
$totalPages = max(1, (int)ceil($total / $perPage));
$page = min($page, $totalPages);

$visibleResults = array_slice(
    $results,
    ($page - 1) * $perPage,
    $perPage
);

$first = $total ? (($page - 1) * $perPage + 1) : 0;
$last = min($page * $perPage, $total);

//View Details works on this same page
$detailKey = (string)($_GET['detail'] ?? '');
$detail = null;

foreach ($results as $item) {
    if ($item['key'] === $detailKey) {
        $detail = $item;
        break;
    }
}
?>


<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search | NSBM 360</title>

    <link rel="stylesheet" href="assets\css\search-style.css">
    <link rel="stylesheet" href="\NSBM_360\assets\css\style.css">

</head>
<body>

<?php
    include 'includes/header.php';
?>



<main class="search-page">

    <!-- Breadcrumb and page title -->
    <nav class="breadcrumb" aria-label="Breadcrumb">
        <a href="index.php">Home</a>
        <span>/</span>
        Search
    </nav>

    <header class="page-heading">
        <h1>Search your campus.</h1>
        <p>Stories, clubs and events — find what matters to you.</p>
    </header>

    <!-- Search toolbar -->
    <form class="search-toolbar" method="get" action="search.php">

        <label class="search-input-wrap">
            <span class="search-symbol" aria-hidden="true">⌕</span>

            <input
                type="search"
                name="q"
                value="<?= e($search) ?>"
                placeholder="Search your campus"
                aria-label="Search your campus"
            >
        </label>

        <select class="toolbar-select" name="type" aria-label="Content type">
            <option value="all" <?= $type === 'all' ? 'selected' : '' ?>>All content</option>
            <option value="news" <?= $type === 'news' ? 'selected' : '' ?>>News</option>
            <option value="events" <?= $type === 'events' ? 'selected' : '' ?>>Events</option>
            <option value="clubs" <?= $type === 'clubs' ? 'selected' : '' ?>>Clubs</option>
            <option value="academic" <?= $type === 'academic' ? 'selected' : '' ?>>Academic</option>
            <option value="announcements" <?= $type === 'announcements' ? 'selected' : '' ?>>Announcements</option>
        </select>

        <select class="toolbar-select time-select" name="time" aria-label="Time range">
            <option value="upcoming" <?= $time === 'upcoming' ? 'selected' : '' ?>>Upcoming</option>
            <option value="any" <?= $time === 'any' ? 'selected' : '' ?>>Any time</option>
            <option value="week" <?= $time === 'week' ? 'selected' : '' ?>>Past 7 days</option>
            <option value="month" <?= $time === 'month' ? 'selected' : '' ?>>Past 30 days</option>
        </select>

        <input type="hidden" name="sort" value="<?= e($sort) ?>">

        <button type="submit" class="button-search">Search</button>
    </form>

    <?php if ($error): ?>
        <div class="error-message" role="alert">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <!-- Search page layout -->
    <div class="search-layout">

        <aside class="filters-column" aria-label="Filter results">

            <section class="filters-card">
                <h2>Filter results</h2>

                <?php
                $filterLabels = [
                    'all' => 'All content',
                    'news' => 'News',
                    'events' => 'Events',
                    'clubs' => 'Clubs',
                    'academic' => 'Academic',
                    'announcements' => 'Announcements'
                ];

                foreach ($filterLabels as $filter => $filterLabel):
                ?>

                    <a
                        class="filter-option <?= $type === $filter ? 'active' : '' ?>"
                        href="<?= e(linkTo(['type' => $filter, 'page' => 1])) ?>"
                        <?= $type === $filter ? 'aria-current="true"' : '' ?>
                    >
                        <span class="filter-checkbox" aria-hidden="true">
                            <?= $type === $filter ? '✓' : '' ?>
                        </span>

                        <span class="filter-name">
                            <?= e($filterLabel) ?>
                        </span>

                        <span class="filter-count">
                            <?= (int)$filterCounts[$filter] ?>
                        </span>
                    </a>

                <?php endforeach; ?>

                <a
                    class="reset-link"
                    href="<?= e(linkTo([
                        'type' => 'all',
                        'time' => 'any',
                        'sort' => 'relevant',
                        'page' => 1
                    ])) ?>"
                >
                    Reset filters
                </a>

            </section>

            <p class="filter-tip">
                Tip: try a club name, an event title or a topic such as “careers”.
            </p>

        </aside>

        <section class="results-column" aria-label="Search results">

            <?php if ($detail): ?>

                <!-- Details of a single result -->
                <article class="details-card">

                    <a class="back-link" href="<?= e(linkTo()) ?>">
                        ← Back to results
                    </a>

                    <span class="result-badge <?= e($detail['kind']) ?>">
                        <?= e($detail['label']) ?>
                    </span>

                    <h2><?= e($detail['title']) ?></h2>

                    <p class="result-meta">
                        <?= e($detail['meta']) ?>
                    </p>

                    <?php if (photoPath($detail['image'])): ?>

                        <img
                            class="detail-image"
                            src="<?= e(photoPath($detail['image'])) ?>"
                            alt="<?= e($detail['title']) ?>"
                        >

                    <?php endif; ?>

                    <div class="detail-content">
                        <?= nl2br(e($detail['content'])) ?>
                    </div>

                    <?php if (
                        !empty($detail['registration_link']) &&
                        preg_match('~^https?://~i', $detail['registration_link'])
                    ): ?>

                        <a
                            class="registration-link"
                            href="<?= e($detail['registration_link']) ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            Event registration
                        </a>

                    <?php endif; ?>

                </article>

            <?php else: ?>

                <!-- Results heading and sorting -->
                <div class="results-heading">

                    <h2>
                        <?= $total ?> result<?= $total === 1 ? '' : 's' ?>

                        <?php if ($search !== ''): ?>
                            for “<?= e($search) ?>”
                        <?php endif; ?>
                    </h2>

                    <form class="sort-form" method="get" action="search.php">

                        <input type="hidden" name="q" value="<?= e($search) ?>">
                        <input type="hidden" name="type" value="<?= e($type) ?>">
                        <input type="hidden" name="time" value="<?= e($time) ?>">

                        <select
                            name="sort"
                            aria-label="Sort results"
                            onchange="this.form.submit()"
                        >
                            <option value="relevant" <?= $sort === 'relevant' ? 'selected' : '' ?>>
                                Most relevant ↓
                            </option>

                            <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>
                                Newest first ↓
                            </option>

                            <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>
                                Oldest first ↑
                            </option>
                        </select>

                        <noscript>
                            <button type="submit">Sort</button>
                        </noscript>

                    </form>

                </div>

                <?php if (!$visibleResults): ?>

                    <div class="empty-results">
                        <h3>No matching results</h3>
                        <p>Try another keyword or choose a different category.</p>
                        <a href="search.php">Clear search</a>
                    </div>

                <?php endif; ?>

                <!-- Search result cards -->
                <?php foreach ($visibleResults as $item): ?>

                    <article class="result-card">

                        <div class="result-image">

                            <?php if (photoPath($item['image'])): ?>

                                <img
                                    src="<?= e(photoPath($item['image'])) ?>"
                                    alt="<?= e($item['title']) ?>"
                                    loading="lazy"
                                >

                            <?php else: ?>

                                <div class="image-placeholder">
                                    NSBM <strong>360</strong>
                                </div>

                            <?php endif; ?>

                        </div>

                        <div class="result-info">

                            <span class="result-badge <?= e($item['kind']) ?>">
                                <?= e($item['label']) ?>
                            </span>

                            <h3><?= e($item['title']) ?></h3>

                            <p class="result-description">
                                <?= e($item['summary']) ?>
                            </p>

                            <p class="result-meta">
                                <?= e($item['meta']) ?>
                            </p>

                        </div>

                        <a
                            class="details-arrow"
                            href="<?= e(linkTo(['detail' => $item['key']])) ?>"
                            aria-label="View details for <?= e($item['title']) ?>"
                        >
                            →
                        </a>

                    </article>

                <?php endforeach; ?>

                <!-- Pagination -->
                <div class="page-navigation">

                    <span>
                        Showing <?= $first ?>–<?= $last ?> of <?= $total ?> results
                    </span>

                    <?php if ($totalPages > 1): ?>

                        <nav class="pagination" aria-label="Result pages">

                            <?php if ($page > 1): ?>

                                <a href="<?= e(linkTo(['page' => $page - 1])) ?>">
                                    ← Previous
                                </a>

                            <?php endif; ?>

                            <?php for (
                                $i = max(1, $page - 2);
                                $i <= min($totalPages, $page + 2);
                                $i++
                            ): ?>

                                <a
                                    class="<?= $i === $page ? 'current' : '' ?>"
                                    href="<?= e(linkTo(['page' => $i])) ?>"
                                    <?= $i === $page ? 'aria-current="page"' : '' ?>
                                >
                                    <?= $i ?>
                                </a>

                            <?php endfor; ?>

                            <?php if ($page < $totalPages): ?>

                                <a href="<?= e(linkTo(['page' => $page + 1])) ?>">
                                    Next →
                                </a>

                            <?php endif; ?>

                        </nav>

                    <?php endif; ?>

                </div>

            <?php endif; ?>

        </section>

    </div>

</main>

<?php
    include 'includes/footer.php';
?>

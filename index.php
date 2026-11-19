
<?php
require_once 'config/database.php';

// Start session before HTML output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Page settings
$activePage = 'home';
$pageTitle = 'Home | NSBM 360';
$pageCSS = '/NSBM_360/assets/css/home.css';
$pageJS = '/NSBM_360/assets/js/home.js';

// Login status
$isLoggedIn = isset($_SESSION['user_id']);
$userName = '';

if ($isLoggedIn && !empty($_SESSION['user_name'])) {
    $userName = $_SESSION['user_name'];
}

// =========================
// HELPER FUNCTIONS
// =========================

function homeImageUrl(?string $path): string
{
    $fallback = '/NSBM_360/assets/image/news-1.jpg';

    if (empty($path)) {
        return $fallback;
    }

    $path = trim(str_replace('\\', '/', $path));

    // Full external image URL
    if (preg_match('~^https?://~i', $path)) {
        return $path;
    }

    // Already contains project URL
    if (str_starts_with($path, '/NSBM_360/')) {
        return $path;
    }

    // Remove leading ../ and ./
    while (str_starts_with($path, '../')) {
        $path = substr($path, 3);
    }

    while (str_starts_with($path, './')) {
        $path = substr($path, 2);
    }

    $path = ltrim($path, '/');

    return '/NSBM_360/' . $path;
}

function homeEscape(?string $value): string
{
    return htmlspecialchars(
        $value ?? '',
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

// =========================
// LATEST NEWS
// =========================

$latestNews = $pdo->query("
    SELECT
        p.post_id,
        p.title,
        p.summary,
        p.cover_image,
        p.published_at,
        p.created_at,
        c.category_name
    FROM posts p
    JOIN categories c
        ON c.category_id = p.category_id
    WHERE p.status = 'published'
      AND c.category_name = 'News'
    ORDER BY
        COALESCE(p.published_at, p.created_at) DESC,
        p.post_id DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// =========================
// UPCOMING EVENTS
// =========================


$upcomingEvents = $pdo->query("
    SELECT
        e.event_id,
        e.event_date,
        e.start_time,
        e.end_time,
        e.start_at,
        e.end_at,
        e.venue,
        p.post_id,
        p.title,
        p.summary,
        p.cover_image,
        c.category_name,
        COALESCE(
            e.start_at,
            TIMESTAMP(e.event_date, e.start_time)
        ) AS event_start,
        COALESCE(
            e.end_at,
            TIMESTAMP(e.event_date, e.end_time)
        ) AS event_end,
        CASE
            WHEN COALESCE(
                e.start_at,
                TIMESTAMP(e.event_date, e.start_time)
            ) <= NOW()
            THEN 'live'
            ELSE 'upcoming'
        END AS event_status
    FROM events e
    JOIN posts p
        ON p.post_id = e.post_id
    JOIN categories c
        ON c.category_id = p.category_id
    WHERE p.status = 'published'
      AND c.category_name = 'Events'
      AND COALESCE(
          e.end_at,
          TIMESTAMP(e.event_date, e.end_time)
      ) > NOW()
    ORDER BY
        CASE
            WHEN COALESCE(
                e.start_at,
                TIMESTAMP(e.event_date, e.start_time)
            ) <= NOW()
            THEN 0
            ELSE 1
        END,
        event_start ASC
    LIMIT 3
")->fetchAll(PDO::FETCH_ASSOC);


// =========================
// INTERACTIVE CALENDAR
// =========================

$calendarRows = $pdo->query("
    SELECT
        e.event_date,
        e.start_time,
        e.start_at,
        e.venue,
        p.title,
        c.category_name,
        COALESCE(
            e.start_at,
            TIMESTAMP(e.event_date, e.start_time)
        ) AS event_start
    FROM events e
    JOIN posts p
        ON p.post_id = e.post_id
    JOIN categories c
        ON c.category_id = p.category_id
    WHERE p.status = 'published'
      AND c.category_name = 'Events'
      AND DATE(
          COALESCE(
              e.start_at,
              TIMESTAMP(e.event_date, e.start_time)
          )
      ) BETWEEN DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
            AND DATE_ADD(CURDATE(), INTERVAL 24 MONTH)
    ORDER BY event_start ASC
")->fetchAll(PDO::FETCH_ASSOC);

$calendarActivities = [];

foreach ($calendarRows as $event) {

    $category = strtolower($event['category_name']);

    $type = match ($category) {
        'sports' => 'sports',
        'academic', 'academics' => 'academic',
        'meeting', 'meetings' => 'meeting',
        'holiday', 'holidays' => 'holiday',
        default => 'event',
    };

    $calendarActivities[] = [
        'date' => date('Y-m-d', strtotime($event['event_start'])),
        'type' => $type,
        'title' => $event['title'],
        'time' => date('g:i A', strtotime($event['event_start'])),
        'location' => $event['venue'],
    ];
}

// =========================
// HOME ANNOUNCEMENTS
// =========================

$announcements = $pdo->query("
    SELECT
        announcement_id,
        title,
        content,
        is_important,
        published_at
    FROM announcements
    WHERE status = 'published'
      AND (
          expires_at IS NULL
          OR expires_at > NOW()
      )
    ORDER BY
        is_important DESC,
        COALESCE(published_at, created_at) DESC,
        announcement_id DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

$totalAnnouncements = count($announcements);

// =========================
// INCLUDE HEADER & NAVBAR
// =========================

include 'includes/header.php';
include 'includes/navbar.php';
?>

<main class="page-content">

    <!-- =========================
         WELCOME SECTION
    ========================= -->

    <section class="welcome-section">

        <div class="welcome-content">

            <div class="welcome-tagline">
                <span class="tagline-line"></span>
                YOUR CAMPUS. YOUR WORLD.
            </div>

            <h1 class="welcome-heading">

                <?php if ($isLoggedIn && $userName !== ''): ?>

                    <span class="welcome-heading-top">
                        Welcome back,
                    </span>

                    <span class="welcome-heading-highlight">
                        <?= homeEscape($userName) ?>!
                    </span>

                <?php else: ?>

                    <span class="welcome-heading-top">
                        Welcome to
                    </span>

                    <span class="welcome-heading-highlight">
                        NSBM 360.
                    </span>

                <?php endif; ?>

            </h1>

            <p class="welcome-description">
                Discover stories, explore opportunities, and stay
                connected with everything happening around your campus.
            </p>

        </div>

        <!-- Official Social Links -->

        <div class="welcome-social-links">

            <a href="https://www.nsbm.ac.lk/"
               target="_blank"
               rel="noopener noreferrer"
               aria-label="NSBM Official Website"
               title="Official Website">
                <i class="bi bi-globe"></i>
                <span>Official Website</span>
            </a>

            <a href="https://www.facebook.com/nsbm.lk/"
               target="_blank"
               rel="noopener noreferrer"
               aria-label="NSBM Facebook"
               title="Facebook">
                <i class="bi bi-facebook"></i>
                <span>Facebook</span>
            </a>

            <a href="https://www.instagram.com/nsbmgreenuniversity/"
               target="_blank"
               rel="noopener noreferrer"
               aria-label="NSBM Instagram"
               title="Instagram">
                <i class="bi bi-instagram"></i>
                <span>Instagram</span>
            </a>

            <a href="https://www.linkedin.com/school/nsbmgreenuniversity/"
               target="_blank"
               rel="noopener noreferrer"
               aria-label="NSBM LinkedIn"
               title="LinkedIn">
                <i class="bi bi-linkedin"></i>
                <span>LinkedIn</span>
            </a>

        </div>

    </section>

    <!-- =========================
         CAMPUS ANNOUNCEMENTS
    ========================= -->

    <?php if ($totalAnnouncements > 0): ?>

        <section class="home-announcements">

            <div class="announcement-bar">

                <div class="announcement-top">

                    <div class="announcement-label">

                        <i class="bi bi-megaphone"></i>

                        <div class="announcement-heading">
                            <h2>Campus Announcements</h2>
                            <span>IMPORTANT CAMPUS UPDATES</span>
                        </div>

                    </div>

                    <a href="/NSBM_360/pages/announcements.php"
                       class="announcement-view-all">
                        View All
                        <i class="bi bi-arrow-up-right"></i>
                    </a>

                </div>

                <div class="announcement-slider" id="announcementSlider">

                    <?php foreach ($announcements as $index => $announcement): ?>

                        <div class="announcement-slide <?= $index === 0 ? 'active' : '' ?>">

                            <?php if ((int) $announcement['is_important'] === 1): ?>
                                <span class="announcement-important">
                                    IMPORTANT
                                </span>
                            <?php endif; ?>

                            <h3>
                                <?= homeEscape($announcement['title']) ?>
                            </h3>

                            <p>
                                <?= nl2br(homeEscape($announcement['content'])) ?>
                            </p>

                        </div>

                    <?php endforeach; ?>

                </div>

                <div class="announcement-bottom">

                    <div class="announcement-dots">

                        <?php foreach ($announcements as $index => $announcement): ?>

                            <button
                                class="announcement-dot <?= $index === 0 ? 'active' : '' ?>"
                                type="button"
                                aria-label="Announcement <?= $index + 1 ?>">
                            </button>

                        <?php endforeach; ?>

                    </div>

                    <div class="announcement-actions">

                        <span
                            class="announcement-counter"
                            id="announcementCounter">
                            01 / <?= str_pad($totalAnnouncements, 2, '0', STR_PAD_LEFT) ?>
                        </span>

                        <button
                            type="button"
                            id="announcementPrev"
                            aria-label="Previous announcement">
                            <i class="bi bi-chevron-left"></i>
                        </button>

                        <button
                            type="button"
                            id="announcementNext"
                            aria-label="Next announcement">
                            <i class="bi bi-chevron-right"></i>
                        </button>

                    </div>

                </div>

            </div>

        </section>

    <?php endif; ?>

    <!-- =========================
         NEWS & CAMPUS ACTIVITY
    ========================= -->

    <section class="home-highlights">

        <!-- LATEST NEWS -->

        <div class="latest-news-panel">

            <div class="home-section-heading">

                <div>
                    <span class="section-eyebrow">CAMPUS STORIES</span>
                    <h2>Latest News</h2>
                </div>

                <a href="/NSBM_360/pages/news.php"
                   class="view-all-news">
                    View All News
                    <i class="bi bi-arrow-up-right"></i>
                </a>

            </div>

            <div class="news-slider" id="newsSlider">

                <?php if ($latestNews): ?>

                    <?php foreach ($latestNews as $i => $news): ?>

                        <article class="news-slide <?= $i === 0 ? 'active' : '' ?>">

                            <div
                                class="news-slide-image"
                                style="background-image: url('<?= homeEscape(homeImageUrl($news['cover_image'])) ?>');">
                            </div>

                            <div class="news-slide-overlay">

                                <span class="news-category">
                                    <?= homeEscape($news['category_name']) ?>
                                </span>

                                <h3>
                                    <?= homeEscape($news['title']) ?>
                                </h3>

                                <p>
                                    <?= homeEscape(
                                        $news['summary'] ?: 'Read the latest campus update.'
                                    ) ?>
                                </p>

                                <span class="news-date">
                                    <?= date(
                                        'M j, Y',
                                        strtotime(
                                            $news['published_at'] ?: $news['created_at']
                                        )
                                    ) ?>
                                </span>

                            </div>

                        </article>

                    <?php endforeach; ?>

                    <?php if (count($latestNews) > 1): ?>

                        <button
                            class="news-arrow news-prev"
                            type="button"
                            aria-label="Previous news">
                            <i class="bi bi-chevron-left"></i>
                        </button>

                        <button
                            class="news-arrow news-next"
                            type="button"
                            aria-label="Next news">
                            <i class="bi bi-chevron-right"></i>
                        </button>

                        <div class="news-dots">

                            <?php foreach ($latestNews as $i => $news): ?>

                                <button
                                    class="news-dot <?= $i === 0 ? 'active' : '' ?>"
                                    type="button"
                                    aria-label="News slide <?= $i + 1 ?>">
                                </button>

                            <?php endforeach; ?>

                        </div>

                    <?php endif; ?>

                <?php else: ?>

                    <div class="home-empty-state">
                        No published news available yet.
                    </div>

                <?php endif; ?>

            </div>

        </div>

        <!-- CAMPUS LIVE & UPCOMING -->

        <div class="campus-activity-panel">

            <div class="home-section-heading">

                <div>
                    <span class="section-eyebrow">WHAT'S HAPPENING</span>
                    <h2>Campus Live & Upcoming</h2>
                </div>

            </div>

            <div class="campus-activity-card">

                <div class="activity-slider-header">

                    <span class="activity-label">
                        <span class="activity-pulse"></span>
                        CAMPUS UPDATES
                    </span>

                    <div class="activity-controls">

                        <button
                            id="activityPrev"
                            type="button"
                            aria-label="Previous activity slide">
                            <i class="bi bi-chevron-left"></i>
                        </button>

                        <button
                            id="activityNext"
                            type="button"
                            aria-label="Next activity slide">
                            <i class="bi bi-chevron-right"></i>
                        </button>

                    </div>

                </div>

                <!-- Slide 1: Upcoming Event -->

                <div
                    class="activity-slide active"
                    id="featuredEventSlide">

                    <?php if ($upcomingEvents): ?>

                        <?php $featuredEvent = $upcomingEvents[0]; ?>

                        
                        <span class="activity-status">
                            <?= $featuredEvent['event_status'] === 'live'
                                ? 'LIVE NOW'
                                : 'UPCOMING EVENT' ?>
                        </span>


                        <h3>
                            <?= homeEscape($featuredEvent['title']) ?>
                        </h3>

                        <p class="activity-description">
                            <?= homeEscape(
                                $featuredEvent['summary']
                                ?: 'Discover upcoming campus activities.'
                            ) ?>
                        </p>

                        <div class="activity-detail">

                            <i class="bi bi-calendar-event"></i>

                            <span>
                                <?= date(
                                    'M j, Y',
                                    strtotime($featuredEvent['event_start'])
                                ) ?>
                                ·
                                <?= date(
                                    'g:i A',
                                    strtotime($featuredEvent['event_start'])
                                ) ?>
                            </span>

                        </div>

                        <div class="activity-detail">

                            <i class="bi bi-geo-alt"></i>

                            <span>
                                <?= homeEscape($featuredEvent['venue']) ?>
                            </span>

                        </div>

                    <?php else: ?>

                        <span class="activity-status">
                            CAMPUS UPDATES
                        </span>

                        <h3>No Upcoming Events</h3>

                        <p class="activity-description">
                            Check back soon for new campus activities.
                        </p>

                    <?php endif; ?>

                    <a href="/NSBM_360/pages/events.php"
                       class="activity-view-link">
                        Explore Events
                        <i class="bi bi-arrow-right"></i>
                    </a>

                </div>

                <!-- Slide 2: Calendar -->

                <div class="activity-slide">

                    <div class="calendar-header">

                        <button
                            id="calendarPrev"
                            type="button"
                            aria-label="Previous month">
                            <i class="bi bi-chevron-left"></i>
                        </button>

                        <h3 id="calendarMonth"></h3>

                        <button
                            id="calendarNext"
                            type="button"
                            aria-label="Next month">
                            <i class="bi bi-chevron-right"></i>
                        </button>

                    </div>

                    <div class="calendar-weekdays">
                        <span>Sun</span>
                        <span>Mon</span>
                        <span>Tue</span>
                        <span>Wed</span>
                        <span>Thu</span>
                        <span>Fri</span>
                        <span>Sat</span>
                    </div>

                    <div class="calendar-days" id="calendarDays"></div>

                    <div
                        class="calendar-event-info"
                        id="calendarEventInfo"
                        aria-live="polite">
                        Select a highlighted date to view details.
                    </div>

                    <div class="calendar-legend">
                        <span><i class="legend-dot event"></i> Events</span>
                        <span><i class="legend-dot meeting"></i> Meetings</span>
                        <span><i class="legend-dot academic"></i> Academic</span>
                        <span><i class="legend-dot holiday"></i> Holidays</span>
                        <span><i class="legend-dot sports"></i> Sports</span>
                    </div>

                </div>

                <div class="activity-dots">
                    <span class="activity-dot active"></span>
                    <span class="activity-dot"></span>
                </div>

            </div>

        </div>

    </section>

    <!-- =========================
         EXPLORE CAMPUS SECTION
    ========================= -->

    <section class="explore-campus-section">

        <div class="explore-campus-heading">

            <div>
                <span class="section-eyebrow">DISCOVER NSBM</span>
                <h2>Explore Campus</h2>

                <p>
                    Everything happening around your campus,
                    all in one place.
                </p>

            </div>

        </div>

        <div class="explore-campus-grid">

            <!-- EVENTS -->

            <a href="/NSBM_360/pages/events.php"
               class="explore-campus-card">

                <div class="explore-card-icon">
                    <i class="bi bi-calendar-event"></i>
                </div>

                <h3>Events</h3>

                <p>
                    Discover upcoming events, workshops
                    and exciting campus activities.
                </p>

                <span class="explore-card-link">
                    Explore Events
                    <i class="bi bi-arrow-up-right"></i>
                </span>

            </a>

            <!-- CLUBS -->

            <a href="/NSBM_360/pages/clubs.php"
               class="explore-campus-card">

                <div class="explore-card-icon">
                    <i class="bi bi-people"></i>
                </div>

                <h3>Clubs</h3>

                <p>
                    Connect with student clubs, communities
                    and organizations at NSBM.
                </p>

                <span class="explore-card-link">
                    Explore Clubs
                    <i class="bi bi-arrow-up-right"></i>
                </span>

            </a>

            <!-- SPORTS -->

            <a href="/NSBM_360/pages/sports.php"
               class="explore-campus-card">

                <div class="explore-card-icon">
                    <i class="bi bi-trophy"></i>
                </div>

                <h3>Sports</h3>

                <p>
                    Follow sports tournaments, competitions
                    and achievements across campus.
                </p>

                <span class="explore-card-link">
                    Explore Sports
                    <i class="bi bi-arrow-up-right"></i>
                </span>

            </a>

            <!-- ACADEMICS -->

            <a href="/NSBM_360/pages/academic.php"
               class="explore-campus-card">

                <div class="explore-card-icon">
                    <i class="bi bi-mortarboard"></i>
                </div>

                <h3>Academic</h3>

                <p>
                    Stay informed about academic updates,
                    examinations and university notices.
                </p>

                <span class="explore-card-link">
                    Explore Academic
                    <i class="bi bi-arrow-up-right"></i>
                </span>

            </a>

        </div>

    </section>

    <!-- =========================
         UPCOMING EVENTS SECTION
    ========================= -->

    <section class="upcoming-events-section">

        <div class="upcoming-events-heading">

            <div>
                <span class="section-eyebrow">WHAT'S COMING UP</span>
                <h2>Upcoming Events</h2>

                <p>
                    Don't miss what's happening around your campus.
                </p>
            </div>

            <a href="/NSBM_360/pages/events.php"
               class="view-all-events">
                View All Events
                <i class="bi bi-arrow-up-right"></i>
            </a>

        </div>

        <div
            class="upcoming-events-list"
            id="upcomingEventsList">

            <?php if ($upcomingEvents): ?>

                <?php foreach ($upcomingEvents as $event): ?>

                    <article class="upcoming-event-card">

                        <div class="event-date-badge">

                            <span class="event-date-day">
                                <?= date(
                                    'd',
                                    strtotime($event['event_start'])
                                ) ?>
                            </span>

                            <span class="event-date-month">
                                <?= strtoupper(
                                    date(
                                        'M',
                                        strtotime($event['event_start'])
                                    )
                                ) ?>
                            </span>

                        </div>

                        <div class="upcoming-event-content">

                            <span class="upcoming-event-category">
                                <?= homeEscape($event['category_name']) ?>
                            </span>

                            <h3>
                                <?= homeEscape($event['title']) ?>
                            </h3>

                            <div class="upcoming-event-meta">

                                <span>
                                    <i class="bi bi-clock"></i>
                                    <?= date(
                                        'g:i A',
                                        strtotime($event['event_start'])
                                    ) ?>
                                </span>

                                <span>
                                    <i class="bi bi-geo-alt"></i>
                                    <?= homeEscape($event['venue']) ?>
                                </span>

                            </div>

                        </div>

                        <div class="upcoming-event-image">

                            <img
                                src="<?= homeEscape(homeImageUrl($event['cover_image'])) ?>"
                                alt="<?= homeEscape($event['title']) ?>"
                                loading="lazy">

                        </div>

                        <a href="/NSBM_360/pages/events.php"
                           class="upcoming-event-arrow"
                           aria-label="View <?= homeEscape($event['title']) ?>">
                            <i class="bi bi-arrow-up-right"></i>
                        </a>

                    </article>

                <?php endforeach; ?>

            <?php else: ?>

                <p class="upcoming-empty-state">
                    No upcoming events available yet.
                </p>

            <?php endif; ?>

        </div>

    </section>

    <script
        type="application/json"
        id="homeCalendarData"><?= json_encode(
            $calendarActivities,
            JSON_HEX_TAG |
            JSON_HEX_AMP |
            JSON_HEX_APOS |
            JSON_HEX_QUOT |
            JSON_INVALID_UTF8_SUBSTITUTE
        ) ?></script>

</main>

<?php
include 'includes/footer.php';
?>

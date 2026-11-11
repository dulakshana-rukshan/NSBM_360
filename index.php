
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


// Home page data from the currently configured database (test_nsbm during development).
// Only published posts/events are displayed.
$latestNews = $pdo->query("
    SELECT p.post_id, p.title, p.summary, p.cover_image, p.published_at,
           p.created_at, c.category_name
    FROM posts p
    JOIN categories c ON c.category_id = p.category_id
    WHERE p.status = 'published' AND c.category_name = 'News'
    ORDER BY COALESCE(p.published_at, p.created_at) DESC, p.post_id DESC
    LIMIT 5
")->fetchAll();

$upcomingEvents = $pdo->query("
    SELECT e.event_id, e.event_date, e.start_time, e.end_time, e.venue,
           p.post_id, p.title, p.summary, p.cover_image, c.category_name
    FROM events e
    JOIN posts p ON p.post_id = e.post_id
    JOIN categories c ON c.category_id = p.category_id
    WHERE p.status = 'published' AND e.event_date >= CURDATE()
    ORDER BY e.event_date ASC, e.start_time ASC
    LIMIT 3
")->fetchAll();

// Load published events for the interactive calendar, including future months.
$calendarRows = $pdo->query("
    SELECT e.event_date, e.start_time, e.venue, p.title, c.category_name
    FROM events e
    JOIN posts p ON p.post_id = e.post_id
    JOIN categories c ON c.category_id = p.category_id
    WHERE p.status = 'published'
      AND e.event_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                           AND DATE_ADD(CURDATE(), INTERVAL 24 MONTH)
    ORDER BY e.event_date, e.start_time
")->fetchAll();

$calendarActivities = [];
foreach ($calendarRows as $event) {
    $category = strtolower($event['category_name']);
    $type = match ($category) {
        'sports' => 'sports',
        'academic' => 'academic',
        'meeting', 'meetings' => 'meeting',
        'holiday', 'holidays' => 'holiday',
        default => 'event',
    };
    $calendarActivities[] = [
        'date' => $event['event_date'],
        'type' => $type,
        'title' => $event['title'],
        'time' => date('g:i A', strtotime($event['start_time'])),
        'location' => $event['venue'],
    ];
}

// Keep image paths local to this project. A missing image uses a known placeholder.
function homeImageUrl(?string $path): string {
    $fallback = '/NSBM_360/assets/image/news-1.jpg';
    if (!$path) return $fallback;
    $path = trim(str_replace('\\', '/', $path));
    if (str_starts_with($path, '/NSBM_360/assets/image/')) return $path;
    if (str_starts_with($path, 'assets/image/')) return '/NSBM_360/' . $path;
    return $fallback;
}

function homeEscape(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Include Header and Navbar
include 'includes/header.php';
include 'includes/navbar.php';
?>

<main class="page-content">

    <!-- =========================
         WELCOME SECTION
    ========================= -->

    <section class="welcome-section">

        <div class="welcome-content">

            <!-- Small Tagline -->
            <div class="welcome-tagline">
                <span class="tagline-line"></span>
                YOUR CAMPUS. YOUR WORLD.
            </div>

            <!-- Welcome Heading -->
            <h1 class="welcome-heading">

                <?php if ($isLoggedIn && $userName !== ''): ?>

                    <span class="welcome-heading-top">
                        Welcome back,
                    </span>

                    <span class="welcome-heading-highlight">
                        <?php echo htmlspecialchars($userName, ENT_QUOTES, 'UTF-8'); ?>!
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

            <!-- Description -->
            <p class="welcome-description">
                Discover stories, explore opportunities, and stay
                connected with everything happening around your campus.
            </p>

        </div>

        <!-- Official Social Links -->
        
        <div class="welcome-social-links">

            <!-- Official Website -->
            <a href="https://www.nsbm.ac.lk/"
            target="_blank"
            rel="noopener noreferrer"
            aria-label="NSBM Official Website"
            title="Official Website">
                <i class="bi bi-globe"></i>
                <span>Official Website</span>
            </a>

            <!-- Facebook -->
            <a href="https://www.facebook.com/nsbm.lk/"
            target="_blank"
            rel="noopener noreferrer"
            aria-label="NSBM Facebook"
            title="Facebook">
                <i class="bi bi-facebook"></i>
                <span>Facebook</span>
            </a>

            <!-- Instagram -->
            <a href="https://www.instagram.com/nsbmgreenuniversity/"
            target="_blank"
            rel="noopener noreferrer"
            aria-label="NSBM Instagram"
            title="Instagram">
                <i class="bi bi-instagram"></i>
                <span>Instagram</span>
            </a>

            <!-- LinkedIn -->
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

            <a href="/NSBM_360/pages/news.php" class="view-all-news">
                View All News
                <i class="bi bi-arrow-up-right"></i>
            </a>
        </div>

        <div class="news-slider" id="newsSlider">
            <?php if ($latestNews): ?>
                <?php foreach ($latestNews as $i => $news): ?>
                    <article class="news-slide <?= $i === 0 ? 'active' : '' ?>">
                        <div class="news-slide-image" style="background-image: url('<?= homeEscape(homeImageUrl($news['cover_image'])) ?>');"></div>
                        <div class="news-slide-overlay">
                            <span class="news-category"><?= homeEscape($news['category_name']) ?></span>
                            <h3><?= homeEscape($news['title']) ?></h3>
                            <p><?= homeEscape($news['summary'] ?: 'Read the latest campus update.') ?></p>
                            <span class="news-date"><?= date('M j, Y', strtotime($news['published_at'] ?: $news['created_at'])) ?></span>
                        </div>
                    </article>
                <?php endforeach; ?>
                <?php if (count($latestNews) > 1): ?>
                    <button class="news-arrow news-prev" type="button" aria-label="Previous news"><i class="bi bi-chevron-left"></i></button>
                    <button class="news-arrow news-next" type="button" aria-label="Next news"><i class="bi bi-chevron-right"></i></button>
                    <div class="news-dots">
                        <?php foreach ($latestNews as $i => $news): ?>
                            <button class="news-dot <?= $i === 0 ? 'active' : '' ?>" type="button" aria-label="News slide <?= $i + 1 ?>"></button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="home-empty-state">No published news available yet.</div>
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
                    <button id="activityPrev" type="button" aria-label="Previous activity slide">
                        <i class="bi bi-chevron-left"></i>
                    </button>

                    <button id="activityNext" type="button" aria-label="Next activity slide">
                        <i class="bi bi-chevron-right"></i>
                    </button>
                </div>
            </div>

            <!-- Slide 1: Upcoming Event -->
            <div class="activity-slide active" id="featuredEventSlide">
                <?php if ($upcomingEvents): $featuredEvent = $upcomingEvents[0]; ?>
                    <span class="activity-status">UPCOMING EVENT</span>
                    <h3><?= homeEscape($featuredEvent['title']) ?></h3>
                    <p class="activity-description"><?= homeEscape($featuredEvent['summary'] ?: 'Discover upcoming campus activities.') ?></p>
                    <div class="activity-detail"><i class="bi bi-calendar-event"></i><span><?= date('M j, Y', strtotime($featuredEvent['event_date'])) ?> · <?= date('g:i A', strtotime($featuredEvent['start_time'])) ?></span></div>
                    <div class="activity-detail"><i class="bi bi-geo-alt"></i><span><?= homeEscape($featuredEvent['venue']) ?></span></div>
                <?php else: ?>
                    <span class="activity-status">CAMPUS UPDATES</span>
                    <h3>No Upcoming Events</h3>
                    <p class="activity-description">Check back soon for new campus activities.</p>
                <?php endif; ?>
                <a href="/NSBM_360/pages/events.php" class="activity-view-link">Explore Events <i class="bi bi-arrow-right"></i></a>
            </div>

            <!-- Slide 2: Calendar -->
            <div class="activity-slide">

                <div class="calendar-header">
                    <button id="calendarPrev" type="button" aria-label="Previous month">
                        <i class="bi bi-chevron-left"></i>
                    </button>

                    <h3 id="calendarMonth"></h3>

                    <button id="calendarNext" type="button" aria-label="Next month">
                        <i class="bi bi-chevron-right"></i>
                    </button>
                </div>

                <div class="calendar-weekdays">
                    <span>Sun</span><span>Mon</span><span>Tue</span>
                    <span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span>
                </div>

                <div class="calendar-days" id="calendarDays"></div>

                <div class="calendar-event-info" id="calendarEventInfo" aria-live="polite">
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

    <!-- ACADEMIC -->
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

  <!-- Section Heading -->
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

  <!-- Events List -->
  <div class="upcoming-events-list" id="upcomingEventsList">
    <?php if ($upcomingEvents): ?>
      <?php foreach ($upcomingEvents as $event): ?>
        <article class="upcoming-event-card">
          <div class="event-date-badge">
            <span class="event-date-day"><?= date('d', strtotime($event['event_date'])) ?></span>
            <span class="event-date-month"><?= strtoupper(date('M', strtotime($event['event_date']))) ?></span>
          </div>
          <div class="upcoming-event-content">
            <span class="upcoming-event-category"><?= homeEscape($event['category_name']) ?></span>
            <h3><?= homeEscape($event['title']) ?></h3>
            <div class="upcoming-event-meta">
              <span><i class="bi bi-clock"></i> <?= date('g:i A', strtotime($event['start_time'])) ?></span>
              <span><i class="bi bi-geo-alt"></i> <?= homeEscape($event['venue']) ?></span>
            </div>
          </div>
          <div class="upcoming-event-image">
            <img src="<?= homeEscape(homeImageUrl($event['cover_image'])) ?>" alt="<?= homeEscape($event['title']) ?>" loading="lazy">
          </div>
          <a href="/NSBM_360/pages/events.php" class="upcoming-event-arrow" aria-label="View <?= homeEscape($event['title']) ?>"><i class="bi bi-arrow-up-right"></i></a>
        </article>
      <?php endforeach; ?>
    <?php else: ?>
      <p class="upcoming-empty-state">No upcoming events available yet.</p>
    <?php endif; ?>
  </div>

</section>



<script type="application/json" id="homeCalendarData"><?= json_encode($calendarActivities, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) ?></script>
</main>

<?php
include 'includes/footer.php';
?>

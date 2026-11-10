
<?php

// --------------------------------------------------
// 1. PRESIDENT AUTHENTICATION
// --------------------------------------------------

require_once __DIR__ . '/president_auth.php';
require_once __DIR__ . '/president_functions.php';

$pageTitle = 'President Dashboard';
$pageCSS = '/NSBM_360/club/president.css';
$isPresidentPage = true;


// --------------------------------------------------
// 2. GET POST STATISTICS
// --------------------------------------------------

$statsQuery = "
    SELECT
        COUNT(*) AS total_posts,

        SUM(
            CASE WHEN status = 'published'
            THEN 1 ELSE 0 END
        ) AS published_posts,

        SUM(
            CASE WHEN status = 'draft'
            THEN 1 ELSE 0 END
        ) AS draft_posts,

        SUM(
            CASE WHEN status = 'archived'
            THEN 1 ELSE 0 END
        ) AS archived_posts

    FROM posts

    WHERE club_id = :club_id
";

$statsStmt = $pdo->prepare($statsQuery);

$statsStmt->execute([
    'club_id' => $clubId
]);

$stats = $statsStmt->fetch();


// --------------------------------------------------
// 3. GET LATEST POSTS
// --------------------------------------------------

$postsQuery = "
    SELECT
        p.post_id,
        p.title,
        p.status,
        p.created_at,
        c.category_name

    FROM posts p

    LEFT JOIN categories c
        ON p.category_id = c.category_id

    WHERE p.club_id = :club_id

    ORDER BY p.created_at DESC

    LIMIT 5
";

$postsStmt = $pdo->prepare($postsQuery);

$postsStmt->execute([
    'club_id' => $clubId
]);

$latestPosts = $postsStmt->fetchAll();


// --------------------------------------------------
// 4. GET NEXT UPCOMING EVENT
// --------------------------------------------------

$eventQuery = "
    SELECT
        e.event_id,
        e.event_date,
        e.start_time,
        e.venue,

        p.post_id,
        p.title

    FROM events e

    INNER JOIN posts p
        ON e.post_id = p.post_id

    WHERE p.club_id = :club_id

      AND p.status = 'published'

      AND e.event_date >= CURDATE()

    ORDER BY
        e.event_date ASC,
        e.start_time ASC

    LIMIT 1
";

$eventStmt = $pdo->prepare($eventQuery);

$eventStmt->execute([
    'club_id' => $clubId
]);

$upcomingEvent = $eventStmt->fetch();


// --------------------------------------------------
// 5. INCLUDE PRESIDENT HEADER
// --------------------------------------------------

require_once __DIR__ . '/president_header.php';

?>


<!-- --------------------------------------------------
     PRESIDENT DASHBOARD
--------------------------------------------------- -->

<div class="page-shell">

    <?php require_once __DIR__ . '/president_sidebar.php'; ?>


    <main class="main-content">


        <!-- PAGE HEADER -->

        <div class="page-header">

            <div>

                <h1>Dashboard</h1>

                <p>
                    Welcome back, <?= e($userName) ?>!
                </p>

            </div>


            <div class="page-header-actions">

                <a
                    href="create-post.php"
                    class="button button-primary"
                >
                    <i class="bi bi-plus-circle"></i>
                    Create New Post
                </a>


                <a
                    href="create-event.php"
                    class="button button-event"
                >
                    <i class="bi bi-calendar-plus"></i>
                    Create New Event
                </a>

            </div>

        </div>



        <!-- WELCOME PANEL -->

        <section class="welcome-panel">

            <div>

                <h2>
                    Welcome to <?= e($clubName) ?>
                </h2>

                <p>
                    Manage your club's posts, events and activities
                    from this workspace.
                </p>

            </div>

            <i class="bi bi-people-fill welcome-icon"></i>

        </section>



        <!-- --------------------------------------------------
             STATISTICS
        --------------------------------------------------- -->

        <section class="stats-grid">


            <!-- TOTAL POSTS -->

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



            <!-- PUBLISHED POSTS -->

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



            <!-- DRAFT POSTS -->

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



            <!-- ARCHIVED POSTS -->

            <div class="stat-card">

                <div class="stat-icon archived-icon">

                    <i class="bi bi-archive"></i>

                </div>

                <div>

                    <p>Archived</p>

                    <h2>
                        <?= (int) ($stats['archived_posts'] ?? 0) ?>
                    </h2>

                </div>

            </div>


        </section>



        <!-- --------------------------------------------------
             RECENT POSTS
        --------------------------------------------------- -->

        <section class="content-card">


            <div class="section-heading">

                <div>

                    <h2>Recent Posts</h2>

                    <p>Your club's latest posts</p>

                </div>


                <a
                    href="manage-posts.php"
                    class="text-link"
                >
                    View All
                </a>

            </div>



            <?php if (!empty($latestPosts)): ?>


                <div class="table-responsive">

                    <table class="data-table">


                        <thead>

                            <tr>

                                <th>Post Title</th>

                                <th>Category</th>

                                <th>Date</th>

                                <th>Status</th>

                                <th>Action</th>

                            </tr>

                        </thead>



                        <tbody>


                            <?php foreach ($latestPosts as $post): ?>


                                <tr>


                                    <!-- POST TITLE -->

                                    <td>
                                        <?= e($post['title']) ?>
                                    </td>



                                    <!-- CATEGORY -->

                                    <td>
                                        <?= e($post['category_name'] ?? '-') ?>
                                    </td>



                                    <!-- CREATED DATE -->

                                    <td>
                                        <?= e(display_date($post['created_at'])) ?>
                                    </td>



                                    <!-- POST STATUS -->

                                    <td>

                                        <span class="status-badge <?= e(post_status_class($post['status'])) ?>">

                                            <?= e(ucfirst($post['status'])) ?>

                                        </span>

                                    </td>



                                    <!-- POST VIEW BUTTON -->

                                    <td>

                                        <a
                                            href="view-post.php?id=<?= (int) $post['post_id'] ?>"
                                            class="table-link"
                                        >
                                            View
                                        </a>

                                    </td>


                                </tr>


                            <?php endforeach; ?>


                        </tbody>


                    </table>

                </div>


            <?php else: ?>


                <div class="empty-state">

                    <i class="bi bi-file-earmark"></i>

                    <h3>No posts yet</h3>

                    <p>Create your first club post.</p>


                    <a
                        href="create-post.php"
                        class="button button-primary"
                    >
                        Create Post
                    </a>

                </div>


            <?php endif; ?>


        </section>



        <!-- --------------------------------------------------
             UPCOMING EVENT
        --------------------------------------------------- -->

        <section class="content-card">


            <div class="section-heading">

                <div>

                    <h2>Upcoming Event</h2>

                    <p>Your next scheduled club event</p>

                </div>

            </div>



            <?php if ($upcomingEvent): ?>


                <div class="event-preview">


                    <!-- EVENT ICON -->

                    <div class="event-date-icon">

                        <i class="bi bi-calendar-event"></i>

                    </div>



                    <!-- EVENT DETAILS -->

                    <div class="event-details">


                        <h3>
                            <?= e($upcomingEvent['title']) ?>
                        </h3>



                        <p>

                            <i class="bi bi-calendar"></i>

                            <?= e(display_date($upcomingEvent['event_date'])) ?>

                        </p>



                        <p>

                            <i class="bi bi-clock"></i>

                            <?= e(date(
                                'h:i A',
                                strtotime($upcomingEvent['start_time'])
                            )) ?>

                        </p>



                        <p>

                            <i class="bi bi-geo-alt"></i>

                            <?= e($upcomingEvent['venue']) ?>

                        </p>


                    </div>



                    <!-- EVENT VIEW BUTTON -->

                    <a
                        href="view-event.php?id=<?= (int) $upcomingEvent['event_id'] ?>"
                        class="table-link"
                    >
                        View Event
                    </a>


                </div>


            <?php else: ?>


                <div class="empty-state">

                    <i class="bi bi-calendar-x"></i>

                    <h3>No upcoming events</h3>

                    <p>
                        There are no upcoming published events.
                    </p>


                    <a
                        href="create-event.php"
                        class="button button-primary"
                    >
                        Create Event
                    </a>

                </div>


            <?php endif; ?>


        </section>


    </main>

</div>



<?php

// --------------------------------------------------
// 6. COMMON FOOTER
// --------------------------------------------------

require_once __DIR__ . '/../includes/footer.php';

?>

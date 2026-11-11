<?php
// Database connection එක ඇතුළත් කිරීම
include 'db.php';
require_once 'config/database.php';
      $activePage = 'news';
      $pageCSS = '../NSBM_360/assets/css/sadika_news.css';
      $pageTitle = 'News | NSBM 360';
      

      include 'includes/header.php';
      include 'includes/navbar.php';
      

// URL එකෙන් එන Post ID එක ලබා ගැනීම (උදා: news.php?id=1)
$post_id = isset($_GET['id']) ? intval($_GET['id']) : 1; 

// posts, categories සහ users tables එකතු කර (JOIN) දත්ත ලබා ගැනීම
$sql = "SELECT p.*, c.category_name, CONCAT(u.first_name, ' ', u.last_name) AS author_name 
        FROM posts p 
        LEFT JOIN categories c ON p.category_id = c.category_id 
        LEFT JOIN users u ON p.created_by = u.user_id 
        WHERE p.post_id = $post_id AND p.status = 'published'";

$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
} else {
    // පුවතක් හමු නොවුණහොත් හෝ ඩේටා හිස් නම් පෙන්වන මූලික දත්ත
    $row = [
        "title" => "Story not found",
        "summary" => "The requested story could not be found.",
        "category_name" => "CAMPUS NEWS",
        "author_name" => "NSBM Editorial",
        "published_at" => date("Y-m-d H:i:s"),
        "cover_image" => "https://via.placeholder.com/800x450",
        "content" => "<p>Please check the article link or try again later.</p>"
    ];
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($row['title']); ?> - NSBM 360</title>
    <link rel="stylesheet" href="news.css">
</head>
<body>

<div class="page-wrapper">
    
    <!-- Breadcrumb -->
    <div class="breadcrumb">HOME / NEWS / <?php echo strtoupper(substr($row['title'], 0, 20)); ?>...</div>

    <div class="content-grid">
        
        <!-- Left Article Column -->
        <article class="article-main">
            <div class="article-category"><?php echo htmlspecialchars(strtoupper($row['category_name'] ?? 'CAMPUS NEWS')); ?></div>
            <h1 class="article-title"><?php echo nl2br(htmlspecialchars($row['title'])); ?></h1>
            <p class="article-subtitle"><?php echo htmlspecialchars($row['summary']); ?></p>

            <!-- Author info -->
            <div class="author-meta">
                <div class="author-avatar"><?php echo strtoupper(substr($row['author_name'], 0, 2)); ?></div>
                <div class="author-details">
                    <span class="author-name"><?php echo htmlspecialchars($row['author_name']); ?> · NSBM 360 Editorial</span>
                    <span class="post-date"><?php echo htmlspecialchars($row['published_at']); ?></span>
                </div>
                <div class="share-actions">
                    <button>Share ↗</button>
                </div>
            </div>

            <!-- Cover Image -->
            <div class="featured-image-container">
                <img src="<?php echo htmlspecialchars(!empty($row['cover_image']) ? $row['cover_image'] : 'https://via.placeholder.com/800x450'); ?>" alt="<?php echo htmlspecialchars($row['title']); ?>">
            </div>

            <!-- Article Body Content -->
            <div class="article-body">
                <?php echo $row['content']; // Database එකෙන් එන HTML content මෙහි render වේ ?>
            </div>

            <!-- Tags -->
            <div class="tags-row">
                <span class="tag">SUSTAINABILITY</span>
                <span class="tag">CAMPUS LIFE</span>
                <span class="tag">STUDENT VOICES</span>
            </div>
        </article>

        <!-- Right Sidebar Column -->
        <aside class="sidebar">
            <!-- Event Widget Card -->
            <div class="sidebar-card event-card">
                <div class="event-card-img">
                    <img src="https://via.placeholder.com/300x160" alt="Tree Planting">
                </div>
                <div class="event-card-content">
                    <span class="be-part-title">Be part of the story</span>
                    <span class="upcoming-badge">UPCOMING EVENT</span>
                    <h3>Green Heart: Campus Tree Planting Day</h3>
                    <p><strong>24 October 2026</strong><br>8:00 AM – 11:00 AM<br>Green University Grounds</p>
                    <a href="#" class="btn-green">View event details</a>
                </div>
            </div>

            <!-- About Widget Card -->
            <div class="sidebar-card about-card">
                <h4>About NSBM 360</h4>
                <p>Your Campus. Your World. Independent student perspectives and official updates, in one place.</p>
                <a href="#">Have a campus story? Get in touch →</a>
            </div>
        </aside>

    </div>

    <!-- Keep Exploring Section -->
    <section class="keep-exploring">
        <div class="section-header">
            <h2>Keep exploring</h2>
            <a href="#">All news →</a>
        </div>
        <div class="explore-grid">
            <div class="explore-card">
                <img src="https://via.placeholder.com/350x200" alt="Tech Showcase">
                <div class="explore-body">
                    <span class="badge-academic">ACADEMIC</span>
                    <h3>Tech Showcase 2026 brings ideas to life</h3>
                    <p>Student teams with real-world projects...</p>
                    <span class="meta-info">Faculty of Computing · 15 Oct 2026</span>
                    <a href="#">Read story →</a>
                </div>
            </div>
            <div class="explore-card">
                <img src="https://via.placeholder.com/350x200" alt="Clubs">
                <div class="explore-body">
                    <span class="badge-club">CLUBS</span>
                    <h3>Find your people in a campus club</h3>
                    <p>A community for every interest and ambition...</p>
                    <span class="meta-info">NSBM 360 Editorial · 14 Oct 2026</span>
                    <a href="#">Read story →</a>
                </div>
            </div>
            <div class="explore-card">
                <img src="https://via.placeholder.com/350x200" alt="Careers">
                <div class="explore-body">
                    <span class="badge-career">CAREERS</span>
                    <h3>Your next opportunity starts here</h3>
                    <p>Get ready for the Future Forward career fair...</p>
                    <span class="meta-info">Career Guidance Unit · 13 Oct 2026</span>
                    <a href="#">Read story →</a>
                </div>
            </div>
        </div>
    </section>

</div>

</body>
<?php include 'includes/footer.php'?>
</html>
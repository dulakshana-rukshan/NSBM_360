
<?php
$activePage = $activePage ?? '';
?>

<nav class="site-navbar">

    <ul class="nav-links">

        <li>
            <a href="/NSBM_360/index.php"
               class="<?php echo ($activePage == 'home') ? 'active' : ''; ?>">
                Home
            </a>
        </li>

        <li>
            <a href="/NSBM_360/pages/events.php"
               class="<?php echo ($activePage == 'events') ? 'active' : ''; ?>">
                Events
            </a>
        </li>

        <li>
            <a href="/NSBM_360/pages/clubs.php"
               class="<?php echo ($activePage == 'clubs') ? 'active' : ''; ?>">
                Clubs
            </a>
        </li>

        <li>
            <a href="/NSBM_360/pages/news.php"
               class="<?php echo ($activePage == 'news') ? 'active' : ''; ?>">
                News
            </a>
        </li>

        <li>
            <a href="/NSBM_360/pages/sports.php"
               class="<?php echo ($activePage == 'sports') ? 'active' : ''; ?>">
                Sports
            </a>
        </li>

    </ul>

    <div class="navbar-right">

        <span class="navbar-location">
            Homagama, Sri Lanka
        </span>

        <form class="navbar-search"
              action="/NSBM_360/search.php"
              method="GET">

            <button type="submit" aria-label="Search">
                <i class="bi bi-search"></i>
            </button>

            <input
                type="search"
                name="q"
                placeholder="Search NSBM 360"
                aria-label="Search NSBM 360"
            >

        </form>

    </div>

</nav>

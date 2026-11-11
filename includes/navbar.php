
<?php
$activePage = $activePage ?? '';

// Navigation links used in desktop and mobile.
$navItems = [
    ['label' => 'Home',     'page' => 'home',     'url' => '/NSBM_360/index.php',    'icon' => 'bi-house'],
    ['label' => 'Events',   'page' => 'events',   'url' => '/NSBM_360/pages/events.php',   'icon' => 'bi-calendar-event'],
    ['label' => 'Clubs',    'page' => 'clubs',    'url' => '/NSBM_360/pages/clubs.php',    'icon' => 'bi-people'],
    ['label' => 'News',     'page' => 'news',     'url' => '/NSBM_360/pages/news.php',     'icon' => 'bi-newspaper'],
    ['label' => 'Sports',   'page' => 'sports',   'url' => '/NSBM_360/pages/sports.php',   'icon' => 'bi-trophy'],
    ['label' => 'Academic', 'page' => 'academic', 'url' => '/NSBM_360/pages/academic.php', 'icon' => 'bi-mortarboard']
];
?>

<nav class="site-navbar" aria-label="Main navigation">

    <!-- Mobile Hamburger Button -->
    <button
        type="button"
        class="navbar-menu-toggle"
        id="navbarMenuToggle"
        aria-label="Open navigation menu"
        aria-controls="mobileNavSidebar"
        aria-expanded="false"
    >
        <i class="bi bi-list"></i>
    </button>

    <!-- Desktop Navigation -->
    <ul class="nav-links">
        <?php foreach ($navItems as $item): ?>
            <li>
                <a
                    href="<?= htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8') ?>"
                    class="<?= $activePage === $item['page'] ? 'active' : '' ?>"
                    <?= $activePage === $item['page'] ? 'aria-current="page"' : '' ?>
                >
                    <?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>

    <!-- Navbar Right -->
    <div class="navbar-right">

        <span class="navbar-location">
            Homagama, Sri Lanka
        </span>

        <form
            class="navbar-search"
            action="/NSBM_360/search.php"
            method="GET"
            role="search"
        >
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

<!-- Mobile Sidebar Backdrop -->
<div
    class="mobile-nav-backdrop"
    id="mobileNavBackdrop"
    aria-hidden="true"
></div>

<!-- Mobile Sidebar -->
<aside
    class="mobile-nav-sidebar"
    id="mobileNavSidebar"
    aria-label="Mobile navigation"
    aria-hidden="true"
    inert
>
    <div class="mobile-nav-header">

        <span class="mobile-nav-title">
            Explore Campus
        </span>

        <button
            type="button"
            class="mobile-nav-close"
            id="mobileNavClose"
            aria-label="Close navigation menu"
        >
            <i class="bi bi-x-lg"></i>
        </button>

    </div>

    <ul class="mobile-nav-links">
        <?php foreach ($navItems as $item): ?>
            <li>
                <a
                    href="<?= htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8') ?>"
                    class="<?= $activePage === $item['page'] ? 'active' : '' ?>"
                    <?= $activePage === $item['page'] ? 'aria-current="page"' : '' ?>
                >
                    <i class="bi <?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') ?>"></i>

                    <span>
                        <?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>

    <div class="mobile-nav-footer">
        <i class="bi bi-geo-alt"></i>
        Homagama, Sri Lanka
    </div>

</aside>

<script>
(function () {
    const toggle = document.getElementById('navbarMenuToggle');
    const sidebar = document.getElementById('mobileNavSidebar');
    const backdrop = document.getElementById('mobileNavBackdrop');
    const closeButton = document.getElementById('mobileNavClose');

    if (!toggle || !sidebar || !backdrop || !closeButton) {
        return;
    }

    let previousFocus = null;

    function openSidebar() {
        previousFocus = document.activeElement;

        sidebar.inert = false;
        sidebar.classList.add('is-open');
        backdrop.classList.add('is-visible');

        sidebar.setAttribute('aria-hidden', 'false');
        toggle.setAttribute('aria-expanded', 'true');
        toggle.setAttribute('aria-label', 'Close navigation menu');

        document.body.classList.add('mobile-nav-open');

        closeButton.focus();
    }

    function closeSidebar() {
        sidebar.classList.remove('is-open');
        backdrop.classList.remove('is-visible');

        sidebar.setAttribute('aria-hidden', 'true');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-label', 'Open navigation menu');

        sidebar.inert = true;
        document.body.classList.remove('mobile-nav-open');

        if (previousFocus && typeof previousFocus.focus === 'function') {
            previousFocus.focus();
        }
    }

    toggle.addEventListener('click', function () {
        if (sidebar.classList.contains('is-open')) {
            closeSidebar();
        } else {
            openSidebar();
        }
    });

    closeButton.addEventListener('click', closeSidebar);
    backdrop.addEventListener('click', closeSidebar);

    document.addEventListener('keydown', function (event) {
        if (!sidebar.classList.contains('is-open')) {
            return;
        }

        if (event.key === 'Escape') {
            closeSidebar();
        }

        if (event.key === 'Tab') {
            const focusable = Array.from(
                sidebar.querySelectorAll(
                    'a[href], button:not([disabled])'
                )
            );

            if (!focusable.length) {
                return;
            }

            const first = focusable[0];
            const last = focusable[focusable.length - 1];

            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        }
    });

    // Close sidebar when switching to desktop size.
    const desktopQuery = window.matchMedia('(min-width: 992px)');

    function handleViewportChange(event) {
        if (event.matches && sidebar.classList.contains('is-open')) {
            closeSidebar();
        }
    }

    if (desktopQuery.addEventListener) {
        desktopQuery.addEventListener('change', handleViewportChange);
    } else {
        desktopQuery.addListener(handleViewportChange);
    }
})();
</script>

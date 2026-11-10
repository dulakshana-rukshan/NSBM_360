
<?php

// ------------------------------------
// 1. IDENTIFY THE CURRENT PAGE
// ------------------------------------

$currentPage = basename($_SERVER['PHP_SELF']);


// ------------------------------------
// 2. CHECK ACTIVE SIDEBAR SECTIONS
// ------------------------------------

$isManageArea = in_array(
    $currentPage,
    ['manage-posts.php', 'edit-post.php', 'view-post.php'],
    true
);

$isEditArea = ($currentPage === 'edit-post.php');

$isEventArea = in_array(
    $currentPage,
    ['create-event.php', 'view-event.php', 'edit-event.php'],
    true
);

?>

<aside class="sidebar">

    <!-- SIDEBAR HEADING -->
    <div class="sidebar-heading">

        <p class="sidebar-kicker">NEWS 360</p>

        <h2>Club Workspace</h2>

        <p class="sidebar-caption">President panel</p>

    </div>


    <!-- ASSIGNED CLUB INFORMATION -->
    <div class="club-card">

        <div class="club-avatar" aria-hidden="true">
            360
        </div>

        <div class="club-card-text">

            <span class="small-label">
                ASSIGNED CLUB
            </span>

            <strong>
                <?= e($clubName ?? 'Club Name') ?>
            </strong>

            <span class="role-label">
                Club President
            </span>

        </div>

    </div>


    <!-- PRESIDENT NAVIGATION -->
    <nav class="sidebar-menu" aria-label="President navigation">


        <!-- CLUB OVERVIEW -->
        <a
            class="side-link <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>"
            href="dashboard.php"
            <?= $currentPage === 'dashboard.php' ? 'aria-current="page"' : '' ?>
        >

            <span class="nav-icon">⌂</span>

            <span>Club Overview</span>

        </a>


        <!-- MANAGE POSTS -->
        <a
            class="side-link <?= $isManageArea ? 'active' : '' ?>"
            href="manage-posts.php"
            <?= $isManageArea ? 'aria-current="page"' : '' ?>
        >

            <span class="nav-icon">▤</span>

            <span>Manage My Posts</span>

        </a>


        <!-- CREATE NEW POST -->
        <a
            class="side-link <?= $currentPage === 'create-post.php' ? 'active' : '' ?>"
            href="create-post.php"
            <?= $currentPage === 'create-post.php' ? 'aria-current="page"' : '' ?>
        >

            <span class="nav-icon">＋</span>

            <span>Create New Post</span>

        </a>


        <!-- CREATE EVENT -->
        <a
            class="side-link <?= $currentPage === 'create-event.php' ? 'active' : '' ?>"
            href="create-event.php"
            <?= $currentPage === 'create-event.php' ? 'aria-current="page"' : '' ?>
        >

            <span><i class="bi bi-calendar-plus"></i></span>
            

            <span>Create New Event</span>

        </a>


       

        <!-- MY PROFILE -->
        <a
            class="side-link <?= $currentPage === 'profile.php' ? 'active' : '' ?>"
            href="/NSBM_360/profile.php"
            <?= $currentPage === 'profile.php' ? 'aria-current="page"' : '' ?>
        >

            <span class="nav-icon">♙</span>

            <span>My Profile</span>

        </a>


        <div class="sidebar-divider"></div>


        <!-- PUBLIC WEBSITE -->
        <a
            class="side-link"
            href="/NSBM_360/index.php"
        >

            <span class="nav-icon">↗</span>

            <span>View Public Site</span>

        </a>


        <!-- SIGN OUT -->
        <a
            class="side-link signout-link"
            href="/NSBM_360/auth/logout.php"
        >

            <span class="nav-icon">⇥</span>

            <span>Sign Out</span>

        </a>


    </nav>

</aside>

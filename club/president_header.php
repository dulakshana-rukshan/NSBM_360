
<?php

// --------------------------------------------------
// 1. GET LOGGED-IN USER DETAILS
// --------------------------------------------------

$headerUserName = $userName ?? 'User';

$headerUserRole = ($clubRole ?? '') === 'vice_president'
    ? 'Vice President'
    : 'Club President';

$headerProfileImage = $profileImage ?? '';

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        <?= htmlspecialchars(
            $pageTitle ?? 'President Workspace | NEWS 360',
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </title>


    <!-- BOOTSTRAP CSS -->
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
    >


    <!-- BOOTSTRAP ICONS -->
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    >


    <!-- MAIN WEBSITE CSS -->
    <link
        rel="stylesheet"
        href="/NSBM_360/assets/css/style.css?v=2"
    >


    <!-- PRESIDENT WORKSPACE CSS -->
    <link
        rel="stylesheet"
        href="/NSBM_360/assets/css/president.css"
    >

</head>


<body>


<!-- =========================================
     PRESIDENT HEADER
========================================= -->

<header class="president-topbar">

    <div class="president-topbar-inner">


        <!-- NEWS 360 LOGO -->
        <div class="president-brand">

            <a href="/NSBM_360/index.php">

                <img
                    src="/NSBM_360/assets/image/NSBM_360_Logo_without_background_1.png"
                    alt="NEWS 360 Logo"
                    class="president-logo"
                >

            </a>

        </div>



        <!-- HEADER TITLE -->
        <div class="president-header-title">

            <h2>President Workspace</h2>

            <p>Manage your club activities and updates</p>

        </div>



        <!-- CLICKABLE USER PROFILE -->
        <a
            href="/NSBM_360/profile.php"
            class="president-profile text-decoration-none"
            aria-label="View My Profile"
            title="View My Profile"
        >


            <!-- USER PROFILE PICTURE -->
            <div class="president-profile-icon">

                <?php if (!empty($headerProfileImage)): ?>

                    <img
                        src="/NSBM_360/<?= htmlspecialchars(
                            ltrim($headerProfileImage, '/'),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        alt="<?= htmlspecialchars(
                            $headerUserName,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?> Profile Picture"
                        onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                    >

                    <!-- DEFAULT ICON -->
                    <i
                        class="bi bi-person-fill"
                        style="display:none;"
                    ></i>

                <?php else: ?>

                    <i class="bi bi-person-fill"></i>

                <?php endif; ?>

            </div>



            <!-- USER INFORMATION -->
            <div class="president-profile-info">


                <!-- DYNAMIC USER NAME -->
                <span class="president-profile-name">

                    <?= htmlspecialchars(
                        $headerUserName,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </span>



                <!-- DYNAMIC ROLE -->
                <span class="president-profile-role">

                    <?= htmlspecialchars(
                        $headerUserRole,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </span>


            </div>


        </a>


    </div>

</header>



<!-- =========================================
     PUBLIC WEBSITE NAVIGATION BAR
========================================= -->

<?php

$activePage = '';

require_once __DIR__ . '/../includes/navbar.php';

?>

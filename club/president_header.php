
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        <?= htmlspecialchars($pageTitle ?? 'President Workspace | NEWS 360', ENT_QUOTES, 'UTF-8') ?>
    </title>

        
    <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">

    <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <link rel="stylesheet"
        href="/NSBM_360/assets/css/style.css">

    <link rel="stylesheet"
        href="/NSBM_360/assets/css/president.css">

</head>

<body>

<!-- PRESIDENT HEADER -->

<header class="president-topbar">

    <div class="president-topbar-inner">

        <div class="president-brand">
            <a href="/NSBM_360/index.php">
                <img
                    src="/NSBM_360/assets/image/NSBM_360_Logo_without_background_1.png"
                    alt="NEWS 360 Logo"
                    class="president-logo">
            </a>
        </div>

        <div class="president-header-title">
            <h2>President Workspace</h2>
            <p>Manage your club activities and updates</p>
        </div>

        <div class="president-profile">

            <div class="president-profile-icon">
                <i class="bi bi-person-fill"></i>
            </div>

            <div class="president-profile-info">

                <span class="president-profile-name">
                    <?= htmlspecialchars($userName ?? 'President', ENT_QUOTES, 'UTF-8') ?>
                </span>

                <span class="president-profile-role">
                    Club President
                </span>

            </div>

        </div>

    </div>

</header>

<!-- PUBLIC WEBSITE NAVIGATION BAR -->
 <link rel="stylesheet" href="/NSBM_360/assets/css/style.css?v=2">

<?php

$activePage = '';

require_once __DIR__ . '/../includes/navbar.php';

?>

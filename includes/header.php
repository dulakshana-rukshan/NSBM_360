
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        <?php echo isset($pageTitle) ? $pageTitle : 'NSBM 360 | Your Campus. Your World.'; ?>
    </title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css"
          rel="stylesheet"
          integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC"
          crossorigin="anonymous">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <link rel="stylesheet" href="/NSBM_360/assets/css/style.css">

    <?php if (!empty($pageCSS)): ?>
        <link rel="stylesheet" href="<?php echo htmlspecialchars($pageCSS, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>

</head>

<body>

    <header>
        <div class="header">

            <a href="/NSBM_360/index.php">
                <img src="/NSBM_360/assets/image/NSBM_360_Logo_without_background_1.png"
                     alt="NSBM 360 Logo"
                     class="logo">
            </a>

            <?php
            $currentPage = basename($_SERVER['SCRIPT_NAME']);
            ?>

            <?php if ($currentPage !== 'login.php' && $currentPage !== 'register.php'): ?>

                <div class="login">
                    <a href="/NSBM_360/auth/login.php">
                        <i class="bi bi-person-circle"></i>
                        Login
                    </a>
                </div>

            <?php endif; ?>

        </div>
    </header>

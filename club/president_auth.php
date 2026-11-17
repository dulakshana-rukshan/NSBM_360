
<?php

// --------------------------------------------------
// 1. START SESSION
// --------------------------------------------------

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// --------------------------------------------------
// 2. IDENTIFY CURRENT PAGE
// --------------------------------------------------

$currentPage = basename($_SERVER['PHP_SELF']);


// --------------------------------------------------
// 3. LOAD DATABASE AND HELPER FUNCTIONS
// --------------------------------------------------

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/president_functions.php';


// --------------------------------------------------
// 4. CHECK LOGIN
// --------------------------------------------------

if (
    !isset($_SESSION['user_id']) ||
    !is_scalar($_SESSION['user_id']) ||
    !ctype_digit((string) $_SESSION['user_id']) ||
    (int) $_SESSION['user_id'] < 1
) {
    show_access_message(
        401,
        'Login Required',
        'Please log in to access the President Workspace.'
    );
}

$userId = (int) $_SESSION['user_id'];


// --------------------------------------------------
// 5. CHECK PRESIDENT / VICE PRESIDENT MEMBERSHIP
// --------------------------------------------------

$sql = "SELECT
            u.user_id,
            u.first_name,
            u.last_name,
            u.email,
            u.phone,
            u.student_id,
            u.profile_image,

            cm.club_id,
            cm.club_role,
            cm.membership_status,
            cm.joined_at,

            c.club_name,
            c.logo,
            c.banner,
            c.description AS club_description,
            c.club_type,
            c.status AS club_status

        FROM users AS u

        INNER JOIN club_memberships AS cm
            ON u.user_id = cm.user_id

        INNER JOIN clubs AS c
            ON cm.club_id = c.club_id

        WHERE u.user_id = :user_id

          AND cm.club_role IN (
              'president',
              'vice_president'
          )

          AND cm.membership_status = 'active'

          AND c.status = 'active'

        LIMIT 2";

$stmt = $pdo->prepare($sql);

$stmt->execute([
    'user_id' => $userId
]);

$presidentRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);


// --------------------------------------------------
// 6. ENSURE THE USER HAS ONLY ONE ACTIVE CLUB
// --------------------------------------------------

if (count($presidentRecords) !== 1) {

    show_access_message(
        403,
        'Access Denied',
        'You do not have an active President or Vice President membership for one club.'
    );

}


// --------------------------------------------------
// 7. STORE USER AND CLUB DETAILS
// --------------------------------------------------

$president = $presidentRecords[0];

$userId = (int) $president['user_id'];

$clubId = (int) $president['club_id'];

$userName = trim(
    $president['first_name'] . ' ' .
    $president['last_name']
);

$profileImage = $president['profile_image'];

$clubName = $president['club_name'];

$clubRole = $president['club_role'];

$clubType = $president['club_type'];

$clubLogo = $president['logo'];

$clubBanner = $president['banner'];

$clubDescription = $president['club_description'];

?>


<?php

require_once __DIR__ . '/president_auth.php';
require_once __DIR__ . '/president_functions.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: manage-posts.php');
    exit;
}

// Validate CSRF token
if (!csrf_is_valid()) {
    http_response_code(403);
    exit('Invalid security token. Please try again.');
}

// Get event ID
$eventId = filter_input(INPUT_POST, 'event_id', FILTER_VALIDATE_INT);

if (!$eventId || $eventId <= 0) {
    header('Location: manage-posts.php');
    exit;
}

$coverImage = null;

try {

    $pdo->beginTransaction();

    // Find the event and its related post
    // Only allow deleting events belonging to this club
    $findQuery = "
        SELECT
            e.event_id,
            e.post_id,
            p.cover_image
        FROM events e
        INNER JOIN posts p
            ON e.post_id = p.post_id
        WHERE e.event_id = :event_id
          AND p.club_id = :club_id
        LIMIT 1
        FOR UPDATE
    ";

    $findStmt = $pdo->prepare($findQuery);

    $findStmt->execute([
        'event_id' => $eventId,
        'club_id' => $clubId
    ]);

    $event = $findStmt->fetch();

    // Event not found or does not belong to this club
    if (!$event) {
        $pdo->rollBack();
        header('Location: manage-posts.php');
        exit;
    }

    $postId = (int) $event['post_id'];
    $coverImage = $event['cover_image'];

    // Delete event details first
    $deleteEventQuery = "
        DELETE FROM events
        WHERE event_id = :event_id
    ";

    $deleteEventStmt = $pdo->prepare($deleteEventQuery);

    $deleteEventStmt->execute([
        'event_id' => $eventId
    ]);

    // Delete the related post
    $deletePostQuery = "
        DELETE FROM posts
        WHERE post_id = :post_id
          AND club_id = :club_id
    ";

    $deletePostStmt = $pdo->prepare($deletePostQuery);

    $deletePostStmt->execute([
        'post_id' => $postId,
        'club_id' => $clubId
    ]);

    // Save database changes
    $pdo->commit();

    // Delete cover image after successful database deletion
    if (!empty($coverImage)) {
        delete_post_image($coverImage);
    }

    header('Location: manage-posts.php');
    exit;

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Delete event error: ' . $e->getMessage());

    http_response_code(500);
    exit('Something went wrong while deleting the event. Please try again.');
}

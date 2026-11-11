
<?php

require_once __DIR__ . '/president_auth.php';
require_once __DIR__ . '/president_functions.php';

// Only POST requests are allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: manage-posts.php');
    exit;
}

// Check CSRF token
if (!csrf_is_valid()) {
    $_SESSION['error_message'] = 'Invalid request. Please try again.';
    header('Location: manage-posts.php');
    exit;
}

// Get post ID
$postId = filter_input(INPUT_POST, 'post_id', FILTER_VALIDATE_INT);

// Validate post ID
if (!$postId || $postId <= 0) {
    $_SESSION['error_message'] = 'Invalid post selected.';
    header('Location: manage-posts.php');
    exit;
}

try {

    // Start database transaction
    $pdo->beginTransaction();

    // Find the post and check whether it belongs to this club
    $findPostQuery = "
        SELECT post_id, cover_image
        FROM posts
        WHERE post_id = :post_id
          AND club_id = :club_id
        LIMIT 1
    ";

    $findPostStmt = $pdo->prepare($findPostQuery);

    $findPostStmt->execute([
        'post_id' => $postId,
        'club_id' => $clubId
    ]);

    $post = $findPostStmt->fetch();

    // Stop if the post does not belong to this club
    if (!$post) {
        $pdo->rollBack();

        $_SESSION['error_message'] = 'Post not found or you do not have permission to delete it.';

        header('Location: manage-posts.php');
        exit;
    }

    // Delete the related event record, if one exists
    $deleteEventQuery = "
        DELETE FROM events
        WHERE post_id = :post_id
    ";

    $deleteEventStmt = $pdo->prepare($deleteEventQuery);

    $deleteEventStmt->execute([
        'post_id' => $postId
    ]);

    // Delete the post belonging to this club
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

    // Confirm that a post was deleted
    if ($deletePostStmt->rowCount() !== 1) {
        throw new Exception('Post could not be deleted.');
    }

    // Commit database changes
    $pdo->commit();

    // Delete the uploaded image after successful database deletion
    delete_post_image($post['cover_image']);

    // Success message
    $_SESSION['success_message'] = 'Post deleted successfully!';

    header('Location: manage-posts.php');
    exit;

} catch (Throwable $e) {

    // Roll back if a transaction is still active
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Post deletion error: ' . $e->getMessage());

    $_SESSION['error_message'] = 'Something went wrong. The post could not be deleted.';

    header('Location: manage-posts.php');
    exit;
}

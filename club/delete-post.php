
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

// Get and validate post ID
$postId = filter_input(INPUT_POST, 'post_id', FILTER_VALIDATE_INT);

if (!$postId || $postId <= 0) {
    $_SESSION['error_message'] = 'Invalid post selected.';
    header('Location: manage-posts.php');
    exit;
}

try {

    // Start transaction
    $pdo->beginTransaction();

    // Check post ownership and get cover image
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

    $post = $findPostStmt->fetch(PDO::FETCH_ASSOC);

    if (!$post) {
        throw new Exception(
            'Post not found or you do not have permission to delete it.'
        );
    }

    // Get all additional images before deleting database records
    $imageQuery = "
        SELECT image_path
        FROM post_images
        WHERE post_id = :post_id
    ";

    $imageStmt = $pdo->prepare($imageQuery);
    $imageStmt->execute(['post_id' => $postId]);

    $additionalImages = $imageStmt->fetchAll(PDO::FETCH_COLUMN);

    // Delete related event record (if this post is an event)
    $deleteEventStmt = $pdo->prepare("
        DELETE FROM events
        WHERE post_id = :post_id
    ");

    $deleteEventStmt->execute([
        'post_id' => $postId
    ]);

    // Delete faculty relationships
    $deleteFacultiesStmt = $pdo->prepare("
        DELETE FROM post_faculties
        WHERE post_id = :post_id
    ");

    $deleteFacultiesStmt->execute([
        'post_id' => $postId
    ]);

    // Delete additional image records
    $deleteImagesStmt = $pdo->prepare("
        DELETE FROM post_images
        WHERE post_id = :post_id
    ");

    $deleteImagesStmt->execute([
        'post_id' => $postId
    ]);

    // Delete the post itself
    $deletePostStmt = $pdo->prepare("
        DELETE FROM posts
        WHERE post_id = :post_id
          AND club_id = :club_id
    ");

    $deletePostStmt->execute([
        'post_id' => $postId,
        'club_id' => $clubId
    ]);

    if ($deletePostStmt->rowCount() !== 1) {
        throw new Exception('Post could not be deleted.');
    }

    // Commit database changes
    $pdo->commit();

    // Delete uploaded cover image
    try {
        delete_post_image($post['cover_image']);
    } catch (Throwable $imageError) {
        error_log('Cover image cleanup error: ' . $imageError->getMessage());
    }

    // Delete uploaded additional images
    foreach ($additionalImages as $imagePath) {
        try {
            delete_post_image($imagePath);
        } catch (Throwable $imageError) {
            error_log('Additional image cleanup error: ' . $imageError->getMessage());
        }
    }

    // Success message
    $_SESSION['success_message'] = 'Post deleted successfully!';

    header('Location: manage-posts.php');
    exit;

} catch (Throwable $e) {

    // Roll back database changes if transaction is active
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Post deletion error: ' . $e->getMessage());

    $_SESSION['error_message'] =
        'Something went wrong. The post could not be deleted.';

    header('Location: manage-posts.php');
    exit;
}

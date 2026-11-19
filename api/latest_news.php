
<?php
// Public read-only endpoint for the home page live news slider.

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/../config/database.php';

// Convert stored image paths into valid project URLs.
function latestNewsImageUrl(?string $path): string
{
    $fallback = '/NSBM_360/assets/image/news-1.jpg';

    if (empty($path)) {
        return $fallback;
    }

    $path = trim(str_replace('\\', '/', $path));

    // Reject unsafe characters and external URLs.
    if (
        preg_match('/[\x00-\x1F"\x27<>]/', $path) ||
        preg_match('~^[a-z][a-z0-9+.-]*:~i', $path) ||
        str_starts_with($path, '//')
    ) {
        return $fallback;
    }

    // Support relative paths saved by different upload pages.
    while (str_starts_with($path, '../')) {
        $path = substr($path, 3);
    }

    while (str_starts_with($path, './')) {
        $path = substr($path, 2);
    }

    // Remove existing project prefix.
    if (str_starts_with($path, '/NSBM_360/')) {
        $path = substr($path, strlen('/NSBM_360/'));
    }

    $path = ltrim($path, '/');

    // Allow only images from approved project folders.
    if (
        !str_starts_with($path, 'uploads/posts/') &&
        !str_starts_with($path, 'assets/image/')
    ) {
        return $fallback;
    }

    // Prevent path traversal.
    if (in_array('..', explode('/', $path), true)) {
        return $fallback;
    }

    // Allow image file extensions only.
    if (!preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $path)) {
        return $fallback;
    }

    return '/NSBM_360/' . $path;
}

try {
    $stmt = $pdo->query("
        SELECT
            p.post_id,
            p.title,
            p.summary,
            p.cover_image,
            p.published_at,
            p.created_at,
            c.category_name
        FROM posts p
        INNER JOIN categories c
            ON c.category_id = p.category_id
        WHERE p.status = 'published'
          AND c.category_name = 'News'
        ORDER BY
            COALESCE(p.published_at, p.created_at) DESC,
            p.post_id DESC
        LIMIT 5
    ");

    $items = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $items[] = [
            'post_id' => (int) $row['post_id'],
            'title' => $row['title'],
            'summary' => $row['summary'],
            'cover_image' => latestNewsImageUrl($row['cover_image']),
            'category_name' => $row['category_name'],
            'date_label' => date(
                'M j, Y',
                strtotime($row['published_at'] ?: $row['created_at'])
            ),
        ];
    }

    echo json_encode($items, JSON_INVALID_UTF8_SUBSTITUTE);

} catch (Throwable $e) {
    error_log('Latest news API: ' . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        'error' => 'Unable to load news'
    ]);
}

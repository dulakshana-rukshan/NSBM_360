<?php
// Public read-only endpoint for the home page live news slider.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
require_once __DIR__ . '/../config/database.php';
try {
    $stmt = $pdo->query("SELECT p.post_id, p.title, p.summary, p.cover_image, p.published_at,
                               p.created_at, c.category_name
                        FROM posts p
                        INNER JOIN categories c ON c.category_id = p.category_id
                        WHERE p.status = 'published' AND c.category_name = 'News'
                        ORDER BY COALESCE(p.published_at, p.created_at) DESC, p.post_id DESC
                        LIMIT 5");
    $items = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $image = trim(str_replace('\\', '/', $row['cover_image'] ?? ''));
        if (!str_starts_with($image, '/NSBM_360/assets/image/') ||
            str_contains($image, '..') || preg_match('/[\x00-\x1F"\x27<>]/', $image)) {
            $image = '/NSBM_360/assets/image/news-1.jpg';
        }
        $items[] = [
            'post_id' => (int)$row['post_id'],
            'title' => $row['title'],
            'summary' => $row['summary'],
            'cover_image' => $image,
            'category_name' => $row['category_name'],
            'date_label' => date('M j, Y', strtotime($row['published_at'] ?: $row['created_at'])),
        ];
    }
    echo json_encode($items, JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $e) {
    error_log('Latest news API: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Unable to load news']);
}

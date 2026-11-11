<?php
// Read-only event feed for the home page. No private user data exposed.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
require_once __DIR__ . '/../config/database.php';
try {
    $rows = $pdo->query("SELECT e.event_id, e.event_date, e.start_time, e.end_time, e.venue,
                               p.post_id, p.title, p.summary, p.cover_image, c.category_name
                        FROM events e
                        JOIN posts p ON p.post_id = e.post_id
                        JOIN categories c ON c.category_id = p.category_id
                        WHERE p.status = 'published'
                          AND e.event_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                                               AND DATE_ADD(CURDATE(), INTERVAL 24 MONTH)
                        ORDER BY e.event_date, e.start_time, e.event_id")->fetchAll(PDO::FETCH_ASSOC);
    $upcoming = [];
    $calendar = [];
    $today = date('Y-m-d');
    foreach ($rows as $row) {
        $category = strtolower($row['category_name']);
        $type = match ($category) {
            'sports' => 'sports', 'academic' => 'academic',
            'meeting', 'meetings' => 'meeting',
            'holiday', 'holidays' => 'holiday', default => 'event'
        };
        $calendar[] = [
            'date' => $row['event_date'], 'type' => $type, 'title' => $row['title'],
            'time' => date('g:i A', strtotime($row['start_time'])), 'location' => $row['venue']
        ];
        if ($row['event_date'] >= $today && count($upcoming) < 3) {
            $image = trim(str_replace('\\', '/', $row['cover_image'] ?? ''));
            if (!str_starts_with($image, '/NSBM_360/assets/image/') ||
                str_contains($image, '..') || preg_match('/[\x00-\x1F"\x27<>]/', $image)) {
                $image = '/NSBM_360/assets/image/news-1.jpg';
            }
            $upcoming[] = [
                'event_id' => (int)$row['event_id'], 'post_id' => (int)$row['post_id'],
                'title' => $row['title'], 'summary' => $row['summary'],
                'category_name' => $row['category_name'], 'cover_image' => $image,
                'event_date' => $row['event_date'],
                'date_day' => date('d', strtotime($row['event_date'])),
                'date_month' => strtoupper(date('M', strtotime($row['event_date']))),
                'date_label' => date('M j, Y', strtotime($row['event_date'])),
                'time_label' => date('g:i A', strtotime($row['start_time'])),
                'venue' => $row['venue']
            ];
        }
    }
    echo json_encode(['upcoming' => $upcoming, 'calendar' => $calendar], JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $e) {
    error_log('Latest events API: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Unable to load events']);
}

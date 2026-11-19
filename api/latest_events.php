
<?php
// Public read-only event feed for NSBM 360 Home Page.

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/../config/database.php';

date_default_timezone_set('Asia/Colombo');

// Resolve uploaded event image paths.
function eventImageUrl(?string $path): string
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

    // Support relative image paths.
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

    // Only allow approved image folders.
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

    // Allow image extensions only.
    if (!preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $path)) {
        return $fallback;
    }

    return '/NSBM_360/' . $path;
}

try {

    // Use Sri Lankan time for MySQL date filtering.
    $pdo->exec("SET time_zone = '+05:30'");

    $rows = $pdo->query("
        SELECT
            e.event_id,
            e.event_date,
            e.start_time,
            e.end_time,
            e.start_at,
            e.end_at,
            e.venue,
            p.post_id,
            p.title,
            p.summary,
            p.cover_image,
            c.category_name
        FROM events e
        JOIN posts p
            ON p.post_id = e.post_id
        JOIN categories c
            ON c.category_id = p.category_id
        WHERE p.status = 'published'
          AND e.event_date BETWEEN
              DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
              AND DATE_ADD(CURDATE(), INTERVAL 24 MONTH)
        ORDER BY
            e.event_date ASC,
            e.start_time ASC,
            e.event_id ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $upcoming = [];
    $calendar = [];

    $timezone = new DateTimeZone('Asia/Colombo');
    $now = new DateTimeImmutable('now', $timezone);

    foreach ($rows as $row) {

        $category = strtolower($row['category_name']);

        $type = match ($category) {
            'sports' => 'sports',
            'academic' => 'academic',
            'meeting', 'meetings' => 'meeting',
            'holiday', 'holidays' => 'holiday',
            default => 'event'
        };

        // Use full date-time values when available.
        $startValue = $row['start_at']
            ?: $row['event_date'] . ' ' . $row['start_time'];

        $endValue = $row['end_at']
            ?: $row['event_date'] . ' ' . $row['end_time'];

        $start = new DateTimeImmutable($startValue, $timezone);
        $end = new DateTimeImmutable($endValue, $timezone);

        // Automatically calculate event status.
        if ($now < $start) {
            $status = 'upcoming';
        } elseif ($now < $end) {
            $status = 'live';
        } else {
            $status = 'completed';
        }

        // Calendar keeps upcoming, live and completed events.
        $calendar[] = [
            'event_id' => (int) $row['event_id'],
            'post_id' => (int) $row['post_id'],
            'date' => $row['event_date'],
            'type' => $type,
            'title' => $row['title'],
            'time' => $start->format('g:i A'),
            'location' => $row['venue'],
            'event_status' => $status
        ];

        // Do not show completed events in upcoming lists.
        if ($status === 'completed') {
            continue;
        }

        $upcoming[] = [
            'event_id' => (int) $row['event_id'],
            'post_id' => (int) $row['post_id'],
            'title' => $row['title'],
            'summary' => $row['summary'],
            'category_name' => $row['category_name'],
            'cover_image' => eventImageUrl($row['cover_image']),
            'event_date' => $row['event_date'],
            'date_day' => $start->format('d'),
            'date_month' => strtoupper($start->format('M')),
            'date_label' => $start->format('M j, Y'),
            'time_label' => $start->format('g:i A'),
            'venue' => $row['venue'],
            'event_status' => $status,
            'start_at' => $start->format('Y-m-d H:i:s'),
            'end_at' => $end->format('Y-m-d H:i:s')
        ];
    }

    // Live events first, then nearest upcoming events.
    usort($upcoming, function ($a, $b) {

        $aPriority = $a['event_status'] === 'live' ? 0 : 1;
        $bPriority = $b['event_status'] === 'live' ? 0 : 1;

        if ($aPriority !== $bPriority) {
            return $aPriority <=> $bPriority;
        }

        return strcmp($a['start_at'], $b['start_at']);
    });

    // Display maximum 3 events.
    $upcoming = array_slice($upcoming, 0, 3);

    echo json_encode([
        'upcoming' => $upcoming,
        'calendar' => $calendar
    ], JSON_INVALID_UTF8_SUBSTITUTE);

} catch (Throwable $e) {

    error_log('Latest events API: ' . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        'error' => 'Unable to load events'
    ]);
}

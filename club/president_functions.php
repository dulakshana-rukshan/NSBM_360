
<?php

/** Escape values before displaying them in HTML. */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** Return a CSRF token for a form. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/** Check the CSRF token submitted by a form. */
function csrf_is_valid(): bool
{
    return isset($_POST['csrf_token'], $_SESSION['csrf_token'])
        && is_string($_POST['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

/** Return the length of text, using mbstring when available. */
function text_length(string $value): int
{
    return function_exists('mb_strlen')
        ? mb_strlen($value, 'UTF-8')
        : strlen($value);
}

/** Format a date for display. */
function display_date(?string $value): string
{
    if (!$value) {
        return '-';
    }

    $timestamp = strtotime($value);

    return $timestamp === false
        ? '-'
        : date('d M Y', $timestamp);
}

/** Return a CSS class for an allowed post status. */
function post_status_class(string $status): string
{
    $allowed = ['draft', 'published', 'archived'];

    return in_array($status, $allowed, true)
        ? 'status-' . $status
        : 'status-draft';
}

/** Save an uploaded JPG, PNG or WEBP image. */
function save_post_image(array $file): ?string
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('The image upload failed. Please try again.');
    }

    $maxBytes = 5 * 1024 * 1024;

    if (($file['size'] ?? 0) > $maxBytes) {
        throw new RuntimeException('The image must be 5 MB or smaller.');
    }

    $temporaryPath = $file['tmp_name'] ?? '';

    if (!$temporaryPath || !is_uploaded_file($temporaryPath)) {
        throw new RuntimeException('The uploaded file is not valid.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($temporaryPath);

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp'
    ];

    if (!isset($allowed[$mime]) || getimagesize($temporaryPath) === false) {
        throw new RuntimeException('Please upload a JPG, PNG or WEBP image.');
    }

    $directory = __DIR__ . '/uploads/posts';

    if (
        !is_dir($directory) &&
        !mkdir($directory, 0755, true) &&
        !is_dir($directory)
    ) {
        throw new RuntimeException('The uploads/posts folder could not be created.');
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    $destination = $directory . '/' . $filename;

    if (!move_uploaded_file($temporaryPath, $destination)) {
        throw new RuntimeException('The image could not be saved. Check folder permissions.');
    }

    return 'uploads/posts/' . $filename;
}

/** Delete only an image inside the uploads/posts directory. */
function delete_post_image(?string $relativePath): void
{
    if (!$relativePath) {
        return;
    }

    $filename = basename($relativePath);

    // Only accept the expected relative path format.
    if (
        !preg_match('/^uploads\/posts\/[a-f0-9]{32}\.(jpg|png|webp)$/', $relativePath)
    ) {
        return;
    }

    $fullPath = __DIR__ . '/uploads/posts/' . $filename;

    if (is_file($fullPath)) {
        unlink($fullPath);
    }
}

/** Display a friendly setup error. */
function show_access_message(
    int $statusCode,
    string $heading,
    string $message
): never {
    http_response_code($statusCode);

    echo '<!DOCTYPE html>';
    echo '<html lang="en">';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>NEWS 360 Setup</title>';
    echo '<link rel="stylesheet" href="/NSBM_360/club/president.css">';
    echo '</head>';
    echo '<body>';

    echo '<main class="setup-message">';
    echo '<div class="setup-card">';
    echo '<p class="eyebrow">NEWS 360 · PRESIDENT WORKSPACE</p>';
    echo '<h1>' . e($heading) . '</h1>';
    echo '<p>' . e($message) . '</p>';

    echo '<p class="setup-help">';
    echo 'Check <a href="/NSBM_360/check_database.php">the database setup checker</a> for more information.';
    echo '</p>';

    echo '<a class="button button-primary" href="/NSBM_360/auth/login.php">Go to login</a>';

    echo '</div>';
    echo '</main>';
    echo '</body>';
    echo '</html>';

    exit;
}

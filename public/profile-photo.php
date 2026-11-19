<?php
declare(strict_types=1);
require __DIR__.'/_init.php';

try {
    $userId = profile_photo_user_id($_SESSION['user'] ?? null);
} catch (ProfilePhotoUnauthorized) {
    http_response_code(401);
    header('Cache-Control: no-store');
    exit('Sign in to view profile photos.');
}

$storageDirectory = (string)cfg('PROFILE_PHOTO_DIR', '/var/lib/campusflow/profile-photos');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $query = db()->prepare('SELECT profile_photo_key FROM users WHERE id=?');
        $query->execute([$userId]);
        $key = $query->fetchColumn();
        $requestedVersion = $_GET['v'] ?? null;
        if (!profile_photo_version_matches($key, $requestedVersion)) {
            http_response_code(404);
            header('Cache-Control: private, no-store');
            exit;
        }

        $directory = realpath($storageDirectory);
        if ($directory === false || is_link($storageDirectory)) throw new RuntimeException('Photo storage is unavailable.');
        $path = $directory.DIRECTORY_SEPARATOR.$key;
        $realPath = realpath($path);
        if ($realPath === false || is_link($path) || !is_file($realPath) || !str_starts_with($realPath, $directory.DIRECTORY_SEPARATOR)) {
            http_response_code(404);
            header('Cache-Control: private, no-store');
            exit;
        }
        $etag = '"'.hash_file('sha256', $realPath).'"';
        header('Content-Type: image/webp');
        header('Content-Disposition: inline; filename="profile.webp"');
        header('X-Content-Type-Options: nosniff');
        // Cache the private image only with mandatory revalidation. The opaque
        // database key in the URL changes on every upload, so replacements have
        // a distinct cache entry while this route still checks authentication.
        header('Cache-Control: private, no-cache, must-revalidate');
        header('Vary: Cookie');
        header('ETag: '.$etag);
        $ifNoneMatch = trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
        $etagMatches = $ifNoneMatch === '*';
        foreach (explode(',', $ifNoneMatch) as $candidate) {
            if (trim($candidate) === $etag) $etagMatches = true;
        }
        if ($etagMatches) {
            http_response_code(304);
            exit;
        }
        header('Content-Length: '.(string)filesize($realPath));
        readfile($realPath);
        exit;
    } catch (Throwable $error) {
        error_log((string)$error);
        http_response_code(500);
        header('Cache-Control: private, no-store');
        exit('Profile photo is temporarily unavailable.');
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: GET, POST');
    exit('Method not allowed.');
}

// PHP leaves $_POST and $_FILES empty when post_max_size is exceeded. Detect
// that case before CSRF parsing so users receive the upload-size explanation.
$postLimitText = trim((string)ini_get('post_max_size'));
if (preg_match('/\A(\d+)\s*([KMG]?)\z/i', $postLimitText, $postLimitMatch)) {
    $postLimit = (int)$postLimitMatch[1] * match (strtoupper($postLimitMatch[2])) { 'K'=>1024, 'M'=>1024**2, 'G'=>1024**3, default=>1 };
    $contentLength = filter_var($_SERVER['CONTENT_LENGTH'] ?? null, FILTER_VALIDATE_INT);
    if ($contentLength !== false && $contentLength > $postLimit) {
        flash_error('Your image is too large. Please choose a photo smaller than 5 MB.');
        header('Location: /profile.php', true, 303);
        exit;
    }
}

check_csrf();
$action = is_string($_POST['photo_action'] ?? null) ? $_POST['photo_action'] : '';
try {
    if ($action === 'remove') {
        clear_user_profile_photo(db(), $userId, $storageDirectory, __DIR__);
        flash('Profile photo removed.');
    } elseif ($action === 'upload') {
        $upload = $_FILES['profile_photo'] ?? null;
        if (!is_array($upload) || !isset($upload['tmp_name']) || !is_uploaded_file((string)$upload['tmp_name'])) {
            throw new InvalidArgumentException('Choose a valid image from your device.');
        }
        $replacement = replace_user_profile_photo(db(), $userId, $upload, $storageDirectory, __DIR__);
        if (!$replacement['old_file_removed']) {
            flash_error('Your new photo is active, but the previous image could not be cleaned up. Support has been notified.');
        } else {
            flash($replacement['replaced'] ? 'Profile photo changed successfully.' : 'Profile photo uploaded successfully.');
        }
    } else {
        throw new InvalidArgumentException('Choose a photo action and try again.');
    }
    header('Location: /profile.php', true, 303);
    exit;
} catch (InvalidArgumentException $error) {
    flash_error($error->getMessage());
    header('Location: /profile.php', true, 303);
    exit;
} catch (Throwable $error) {
    error_log((string)$error);
    $message = 'The profile photo could not be saved. Please try again.';
    $technicalMessage = strtolower($error->getMessage());
    if ($error instanceof RuntimeException && (str_contains($technicalMessage, 'storage') || str_contains($technicalMessage, 'stored') || str_contains($technicalMessage, 'saved'))) {
        $message = 'Photo storage is temporarily unavailable. Please try again later.';
    } elseif ($error instanceof RuntimeException && (str_contains($technicalMessage, 'process') || str_contains($technicalMessage, 'encoded') || str_contains($technicalMessage, 'decode'))) {
        $message = 'Image processing failed. Please choose another photo.';
    }
    flash_error($message);
    header('Location: /profile.php', true, 303);
    exit;
}

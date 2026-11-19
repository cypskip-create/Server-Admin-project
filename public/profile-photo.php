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
        if (!profile_photo_key_is_valid($key)) {
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
        header('Cache-Control: private, max-age=300, must-revalidate');
        header('ETag: '.$etag);
        if (trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
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
        replace_user_profile_photo(db(), $userId, $upload, $storageDirectory, __DIR__);
        flash('Profile photo updated.');
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
    flash_error('The profile photo could not be saved. Please try again.');
    header('Location: /profile.php', true, 303);
    exit;
}

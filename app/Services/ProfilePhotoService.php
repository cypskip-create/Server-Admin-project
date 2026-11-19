<?php
declare(strict_types=1);

const PROFILE_PHOTO_MAX_BYTES = 2_097_152;
const PROFILE_PHOTO_MAX_PIXELS = 12_000_000;
const PROFILE_PHOTO_OUTPUT_SIZE = 512;

final class ProfilePhotoUnauthorized extends RuntimeException {}

function profile_photo_user_id(mixed $user): int
{
    $id = is_array($user) ? filter_var($user['id'] ?? null, FILTER_VALIDATE_INT) : false;
    if (!$id || $id < 1) throw new ProfilePhotoUnauthorized('Authentication required.');
    return (int)$id;
}

function profile_photo_key_is_valid(mixed $key): bool
{
    return is_string($key) && preg_match('/\A[a-f0-9]{48}\.webp\z/D', $key) === 1;
}

function profile_photo_storage_directory(string $directory, string $publicDirectory): string
{
    if (is_link($directory)) throw new RuntimeException('Profile photo storage cannot be a symbolic link.');
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new RuntimeException('Profile photo storage is unavailable.');
    }
    $realDirectory = realpath($directory);
    $realPublicDirectory = realpath($publicDirectory);
    if ($realDirectory === false || $realPublicDirectory === false) {
        throw new RuntimeException('Profile photo storage is unavailable.');
    }
    $publicPrefix = rtrim($realPublicDirectory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
    if ($realDirectory === $realPublicDirectory || str_starts_with($realDirectory.DIRECTORY_SEPARATOR, $publicPrefix)) {
        throw new RuntimeException('Profile photo storage must be outside the public web root.');
    }
    if (!is_writable($realDirectory)) throw new RuntimeException('Profile photo storage is not writable.');
    return $realDirectory;
}

/** Validate and re-encode an uploaded raster image to a random WebP file. */
function store_profile_photo(array $upload, string $directory, string $publicDirectory): string
{
    $error = filter_var($upload['error'] ?? null, FILTER_VALIDATE_INT);
    if ($error !== UPLOAD_ERR_OK) {
        $messages = [
            UPLOAD_ERR_INI_SIZE => 'The image exceeds the server upload limit.',
            UPLOAD_ERR_FORM_SIZE => 'Choose an image no larger than 2 MB.',
            UPLOAD_ERR_PARTIAL => 'The image upload was interrupted. Try again.',
            UPLOAD_ERR_NO_FILE => 'Choose an image to upload.',
            UPLOAD_ERR_NO_TMP_DIR => 'The server upload area is unavailable.',
            UPLOAD_ERR_CANT_WRITE => 'The server could not save the image.',
            UPLOAD_ERR_EXTENSION => 'The server rejected the image upload.',
        ];
        throw new InvalidArgumentException($messages[$error] ?? 'The image upload failed.');
    }

    $temporaryPath = $upload['tmp_name'] ?? null;
    if (!is_string($temporaryPath) || $temporaryPath === '' || !is_file($temporaryPath) || !is_readable($temporaryPath)) {
        throw new InvalidArgumentException('The uploaded image could not be read.');
    }
    $actualBytes = filesize($temporaryPath);
    $reportedBytes = filter_var($upload['size'] ?? null, FILTER_VALIDATE_INT);
    if ($actualBytes === false || $actualBytes < 1 || $actualBytes > PROFILE_PHOTO_MAX_BYTES || $reportedBytes === false || $reportedBytes > PROFILE_PHOTO_MAX_BYTES) {
        throw new InvalidArgumentException('Choose an image no larger than 2 MB.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($temporaryPath);
    $allowedMime = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($mime, $allowedMime, true)) {
        throw new InvalidArgumentException('Use a JPEG, PNG or WebP image.');
    }

    $imageInfo = @getimagesize($temporaryPath);
    if (!is_array($imageInfo) || !isset($imageInfo[0], $imageInfo[1]) || $imageInfo[0] < 1 || $imageInfo[1] < 1 || $imageInfo[0] * $imageInfo[1] > PROFILE_PHOTO_MAX_PIXELS) {
        throw new InvalidArgumentException('The image dimensions are invalid or too large.');
    }
    if (!extension_loaded('gd') || !function_exists('imagecreatefromstring') || !function_exists('imagewebp')) {
        throw new RuntimeException('Secure image processing is temporarily unavailable. Please contact support.');
    }

    $source = @imagecreatefromstring((string)file_get_contents($temporaryPath));
    if (!$source instanceof GdImage) throw new InvalidArgumentException('The selected file is not a valid image.');
    $sourceWidth = imagesx($source);
    $sourceHeight = imagesy($source);
    if ($sourceWidth < 1 || $sourceHeight < 1 || $sourceWidth * $sourceHeight > PROFILE_PHOTO_MAX_PIXELS) {
        imagedestroy($source);
        throw new InvalidArgumentException('The image dimensions are invalid or too large.');
    }

    $realDirectory = profile_photo_storage_directory($directory, $publicDirectory);
    $output = imagecreatetruecolor(PROFILE_PHOTO_OUTPUT_SIZE, PROFILE_PHOTO_OUTPUT_SIZE);
    if (!$output instanceof GdImage) {
        imagedestroy($source);
        throw new RuntimeException('The image could not be processed.');
    }
    imagealphablending($output, false);
    imagesavealpha($output, true);
    $transparent = imagecolorallocatealpha($output, 0, 0, 0, 127);
    imagefilledrectangle($output, 0, 0, PROFILE_PHOTO_OUTPUT_SIZE, PROFILE_PHOTO_OUTPUT_SIZE, $transparent);

    $cropSize = min($sourceWidth, $sourceHeight);
    $sourceX = (int)floor(($sourceWidth - $cropSize) / 2);
    $sourceY = (int)floor(($sourceHeight - $cropSize) / 2);
    imagecopyresampled($output, $source, 0, 0, $sourceX, $sourceY, PROFILE_PHOTO_OUTPUT_SIZE, PROFILE_PHOTO_OUTPUT_SIZE, $cropSize, $cropSize);
    imagedestroy($source);

    $key = bin2hex(random_bytes(24)).'.webp';
    $temporaryOutput = tempnam($realDirectory, '.campusflow-photo-');
    if ($temporaryOutput === false) {
        imagedestroy($output);
        throw new RuntimeException('The image could not be stored.');
    }
    try {
        if (!imagewebp($output, $temporaryOutput, 86)) throw new RuntimeException('The image could not be encoded.');
        chmod($temporaryOutput, 0640);
        $outputBytes = filesize($temporaryOutput);
        if ($outputBytes === false || $outputBytes < 1 || $outputBytes > PROFILE_PHOTO_MAX_BYTES) {
            throw new RuntimeException('The processed image is too large.');
        }
        if (!rename($temporaryOutput, $realDirectory.DIRECTORY_SEPARATOR.$key)) {
            throw new RuntimeException('The processed image could not be saved.');
        }
        return $key;
    } finally {
        imagedestroy($output);
        if (is_file($temporaryOutput)) @unlink($temporaryOutput);
    }
}

function remove_profile_photo_file(string $directory, string $key, string $publicDirectory): bool
{
    if (!profile_photo_key_is_valid($key)) return false;
    $realDirectory = profile_photo_storage_directory($directory, $publicDirectory);
    $path = $realDirectory.DIRECTORY_SEPARATOR.$key;
    if (!file_exists($path)) return true;
    if (is_link($path) || !is_file($path)) return false;
    return @unlink($path);
}

function replace_user_profile_photo(PDO $db, int $userId, array $upload, string $directory, string $publicDirectory): string
{
    if ($userId < 1) throw new ProfilePhotoUnauthorized('Authentication required.');
    $newKey = store_profile_photo($upload, $directory, $publicDirectory);
    $oldKey = null;
    try {
        $db->beginTransaction();
        $query = $db->prepare('SELECT profile_photo_key FROM users WHERE id=? FOR UPDATE');
        $query->execute([$userId]);
        $oldKey = $query->fetchColumn();
        if ($oldKey === false) throw new RuntimeException('The account no longer exists.');
        $update = $db->prepare('UPDATE users SET profile_photo_key=? WHERE id=?');
        $update->execute([$newKey, $userId]);
        $db->commit();
    } catch (Throwable $error) {
        if ($db->inTransaction()) $db->rollBack();
        remove_profile_photo_file($directory, $newKey, $publicDirectory);
        throw $error;
    }
    if (is_string($oldKey) && profile_photo_key_is_valid($oldKey) && $oldKey !== $newKey) {
        remove_profile_photo_file($directory, $oldKey, $publicDirectory);
    }
    return $newKey;
}

function clear_user_profile_photo(PDO $db, int $userId, string $directory, string $publicDirectory): bool
{
    if ($userId < 1) throw new ProfilePhotoUnauthorized('Authentication required.');
    $db->beginTransaction();
    try {
        $query = $db->prepare('SELECT profile_photo_key FROM users WHERE id=? FOR UPDATE');
        $query->execute([$userId]);
        $key = $query->fetchColumn();
        if ($key === false) throw new RuntimeException('The account no longer exists.');
        $db->prepare('UPDATE users SET profile_photo_key=NULL WHERE id=?')->execute([$userId]);
        $db->commit();
    } catch (Throwable $error) {
        if ($db->inTransaction()) $db->rollBack();
        throw $error;
    }
    if (is_string($key) && profile_photo_key_is_valid($key)) remove_profile_photo_file($directory, $key, $publicDirectory);
    return is_string($key) && profile_photo_key_is_valid($key);
}

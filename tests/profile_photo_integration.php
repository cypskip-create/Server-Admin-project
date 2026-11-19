<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Services/AuthService.php';
require dirname(__DIR__).'/app/Services/ProfilePhotoService.php';
require dirname(__DIR__).'/config/security.php';

$host = getenv('TEST_DB_HOST') ?: '';
$name = getenv('TEST_DB_NAME') ?: '';
$user = getenv('TEST_DB_USER') ?: '';
$pass = getenv('TEST_DB_PASS') ?: '';
if ($host === '' || $name === '' || $user === '') {
    echo "SKIP: set TEST_DB_HOST, TEST_DB_NAME and TEST_DB_USER to run profile photo database tests.\n";
    exit(0);
}
if (!extension_loaded('gd')) {
    echo "SKIP: install PHP GD to run profile photo integration tests.\n";
    exit(0);
}

$pdo = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$assert = static function (bool $condition, string $label): void {
    if (!$condition) throw new RuntimeException('FAIL: '.$label);
    echo "PASS: $label\n";
};
$expectFailure = static function (callable $action, string $label) use ($assert): Throwable {
    try { $action(); }
    catch (Throwable $error) { $assert(true, $label); return $error; }
    $assert(false, $label);
    throw new LogicException('Unreachable');
};
$makeUpload = static function (int $red, int $green, int $blue, string $name = 'avatar.png', string $format = 'png'): array {
    $image = imagecreatetruecolor(90, 60);
    imagefill($image, 0, 0, imagecolorallocate($image, $red, $green, $blue));
    $temporaryPath = tempnam(sys_get_temp_dir(), 'campusflow-profile-photo-');
    match ($format) {
        'jpeg' => imagejpeg($image, $temporaryPath, 90),
        'webp' => imagewebp($image, $temporaryPath, 88),
        default => imagepng($image, $temporaryPath),
    };
    imagedestroy($image);
    return ['error'=>UPLOAD_ERR_OK,'tmp_name'=>$temporaryPath,'size'=>filesize($temporaryPath),'name'=>$name,'type'=>'application/octet-stream'];
};

$suffix = bin2hex(random_bytes(5));
$createdUsers = [];
$temporaryFiles = [];
$storage = sys_get_temp_dir().DIRECTORY_SEPARATOR.'campusflow-photo-integration-'.bin2hex(random_bytes(8));
$public = dirname(__DIR__).DIRECTORY_SEPARATOR.'public';
try {
    $first = ['full_name'=>'Photo Test Owner','username'=>'photoOwner'.$suffix,'email'=>'photo-owner-'.$suffix.'@example.test','student_number'=>'PO'.$suffix,'password'=>'StrongPhotoPass123','confirm'=>'StrongPhotoPass123'];
    $second = ['full_name'=>'Photo Test Other','username'=>'photoOther'.$suffix,'email'=>'photo-other-'.$suffix.'@example.test','student_number'=>'PX'.$suffix,'password'=>'StrongPhotoPass123','confirm'=>'StrongPhotoPass123'];
    $ownerId = register_student($pdo, $first);
    $otherId = register_student($pdo, $second);
    $createdUsers = [$ownerId, $otherId];

    $_SESSION = ['user'=>['id'=>$ownerId,'role'=>'student']];
    $_POST = ['user_id'=>(string)$otherId];
    $sessionOwner = profile_photo_user_id($_SESSION['user']);
    $assert($sessionOwner === $ownerId && $sessionOwner !== (int)$_POST['user_id'], 'a manipulated user_id cannot change the authenticated photo owner');
    $expectFailure(static fn() => profile_photo_user_id(null), 'photo API rejects an unauthenticated requester');
    $assert(!csrf_token_is_valid(null) && !csrf_token_is_valid('wrong'), 'photo mutation CSRF checks reject missing and invalid tokens');

    $initialUpload = $makeUpload(30, 90, 210, '../../public/profile.php');
    $temporaryFiles[] = $initialUpload['tmp_name'];
    $firstKey = replace_user_profile_photo($pdo, $sessionOwner, $initialUpload, $storage, $public);
    $storedPath = $storage.DIRECTORY_SEPARATOR.$firstKey;
    $row = $pdo->prepare('SELECT profile_photo_key FROM users WHERE id=?');
    $row->execute([$ownerId]);
    $assert(profile_photo_key_is_valid($firstKey) && $row->fetchColumn() === $firstKey && is_file($storedPath), 'successful upload stores a random private file and matching database reference');
    $imageInfo = getimagesize($storedPath);
    $assert(($imageInfo['mime'] ?? '') === 'image/webp' && $imageInfo[0] === 512 && $imageInfo[1] === 512, 'uploaded image is safely decoded, square-cropped and normalized to WebP');
    $realStorage = realpath($storage);
    $realStoredPath = realpath($storedPath);
    $assert($realStorage !== false && $realStoredPath !== false && dirname($realStoredPath) === $realStorage && basename($realStoredPath) === $firstKey && !is_executable($realStoredPath), 'traversal filenames never control the output path or create executable files');

    $otherRow = $pdo->prepare('SELECT profile_photo_key FROM users WHERE id=?');
    $otherRow->execute([$otherId]);
    $assert($otherRow->fetchColumn() === null, 'another student profile remains unchanged by the upload');

    $fake = tempnam(sys_get_temp_dir(), 'campusflow-fake-photo-');
    $temporaryFiles[] = $fake;
    file_put_contents($fake, "\x89PNG\r\n\x1a\nnot an image");
    $expectFailure(static fn() => replace_user_profile_photo($pdo, $ownerId, ['error'=>UPLOAD_ERR_OK,'tmp_name'=>$fake,'size'=>filesize($fake),'name'=>'fake.png','type'=>'image/png'], $storage, $public), 'fake image content is rejected by the upload pipeline');
    $row->execute([$ownerId]);
    $assert($row->fetchColumn() === $firstKey && is_file($storedPath), 'failed replacement leaves the existing profile photo and database reference intact');

    $oversize = tempnam(sys_get_temp_dir(), 'campusflow-large-photo-');
    $temporaryFiles[] = $oversize;
    file_put_contents($oversize, str_repeat('X', PROFILE_PHOTO_MAX_BYTES + 1));
    $expectFailure(static fn() => replace_user_profile_photo($pdo, $ownerId, ['error'=>UPLOAD_ERR_OK,'tmp_name'=>$oversize,'size'=>filesize($oversize),'name'=>'large.png','type'=>'image/png'], $storage, $public), 'oversized photo is rejected without changing the saved reference');

    $php = tempnam(sys_get_temp_dir(), 'campusflow-executable-photo-');
    $temporaryFiles[] = $php;
    file_put_contents($php, '<?php echo "no"; ?>');
    $expectFailure(static fn() => replace_user_profile_photo($pdo, $ownerId, ['error'=>UPLOAD_ERR_OK,'tmp_name'=>$php,'size'=>filesize($php),'name'=>'avatar.php','type'=>'image/jpeg'], $storage, $public), 'executable PHP content is rejected even with an image MIME claim');

    $secondUpload = $makeUpload(210, 80, 35, 'avatar.webp', 'webp');
    $temporaryFiles[] = $secondUpload['tmp_name'];
    $secondKey = replace_user_profile_photo($pdo, $ownerId, $secondUpload, $storage, $public);
    $row->execute([$ownerId]);
    $assert($secondKey !== $firstKey && $row->fetchColumn() === $secondKey && is_file($storage.DIRECTORY_SEPARATOR.$secondKey), 'replacement atomically points the account to the new photo');
    $assert(!file_exists($storedPath), 'replaced photo file is removed after the new reference commits');

    $thirdUpload = $makeUpload(80, 170, 120, 'avatar.jpg', 'jpeg');
    $temporaryFiles[] = $thirdUpload['tmp_name'];
    $thirdKey = replace_user_profile_photo($pdo, $ownerId, $thirdUpload, $storage, $public);
    $row->execute([$ownerId]);
    $assert($thirdKey !== $secondKey && $row->fetchColumn() === $thirdKey && is_file($storage.DIRECTORY_SEPARATOR.$thirdKey), 'JPEG input can replace a WebP photo using the private normalized format');
    $assert(!file_exists($storage.DIRECTORY_SEPARATOR.$secondKey), 'previous WebP file is removed after JPEG replacement commits');

    $removed = clear_user_profile_photo($pdo, $ownerId, $storage, $public);
    $row->execute([$ownerId]);
    $assert($removed && $row->fetchColumn() === null, 'photo removal clears the database reference');
    $assert(!file_exists($storage.DIRECTORY_SEPARATOR.$thirdKey), 'photo removal deletes only the current stored photo');
    $orphanAttempt = $makeUpload(20, 30, 40);
    $temporaryFiles[] = $orphanAttempt['tmp_name'];
    $expectFailure(static fn() => replace_user_profile_photo($pdo, 9_999_999_999, $orphanAttempt, $storage, $public), 'database failure rolls back and removes a newly written orphan file');
    $files = is_dir($storage) ? array_values(array_diff(scandir($storage) ?: [], ['.','..'])) : [];
    $assert($files === [], 'photo storage contains no executable or orphan test files after cleanup');
} finally {
    if ($pdo->inTransaction()) $pdo->rollBack();
    foreach ($createdUsers as $id) $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
    foreach ($temporaryFiles as $path) if (is_string($path) && is_file($path)) @unlink($path);
    if (is_dir($storage)) {
        foreach (new DirectoryIterator($storage) as $entry) if ($entry->isFile() && !$entry->isDot()) @unlink($entry->getPathname());
        @rmdir($storage);
    }
    $_SESSION = [];
    $_POST = [];
}


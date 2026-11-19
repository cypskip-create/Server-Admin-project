<?php
declare(strict_types=1);
require dirname(__DIR__).'/config/security.php';
require dirname(__DIR__).'/app/Services/ProfilePhotoService.php';

$assert = static function (bool $condition, string $label): void {
    if (!$condition) throw new RuntimeException('FAIL: '.$label);
    echo "PASS: $label\n";
};
$rejects = static function (callable $action, string $label, ?string $message = null) use ($assert): Throwable {
    try { $action(); }
    catch (Throwable $error) {
        $assert($message === null || str_contains($error->getMessage(), $message), $label);
        return $error;
    }
    $assert(false, $label);
    throw new LogicException('Unreachable');
};

$_SESSION = [];
$assert(!csrf_token_is_valid(null) && !csrf_token_is_valid(''), 'photo forms reject missing CSRF values');
$_SESSION['csrf'] = 'profile-photo-test-token';
$assert(!csrf_token_is_valid(null) && !csrf_token_is_valid('invalid'), 'photo forms reject missing and invalid CSRF tokens');
$assert(csrf_token_is_valid('profile-photo-test-token'), 'photo form accepts its matching CSRF token');
$rejects(static fn() => profile_photo_user_id(null), 'photo access requires authentication', 'Authentication required');
$rejects(static fn() => profile_photo_user_id(['id'=>'not-an-id']), 'photo access rejects invalid session identities', 'Authentication required');
$assert(profile_photo_user_id(['id'=>37,'role'=>'student']) === 37, 'photo ownership comes from the authenticated session identity');
$assert(profile_photo_key_is_valid(str_repeat('a', 48).'.webp'), 'random WebP storage key shape is accepted');
$assert(!profile_photo_key_is_valid('../profile.php') && !profile_photo_key_is_valid('user.jpg'), 'path traversal and original filenames are never accepted as storage keys');
$assert(PROFILE_PHOTO_MAX_BYTES === 5 * 1024 * 1024, 'photo upload limit is exactly 5 MiB');
$versionA = str_repeat('a', 48).'.webp';
$versionB = str_repeat('b', 48).'.webp';
$assert(profile_photo_url($versionA) !== profile_photo_url($versionB), 'each replacement gets a distinct cache-versioned URL');
$assert(profile_photo_version_matches($versionA, $versionA) && !profile_photo_version_matches($versionB, $versionA), 'photo endpoint accepts the current version and rejects stale cached URLs');

$temporaryRoot = sys_get_temp_dir().DIRECTORY_SEPARATOR.'campusflow-photo-unit-'.bin2hex(random_bytes(8));
$storage = $temporaryRoot.DIRECTORY_SEPARATOR.'private';
$public = $temporaryRoot.DIRECTORY_SEPARATOR.'public';
mkdir($public, 0750, true);
$temporaryFiles = [];
$rejectUpload = static function (array $upload, string $label) use ($storage, $public, $rejects): void {
    $rejects(static fn() => store_profile_photo($upload, $storage, $public), $label);
};
try {
    $rejectUpload(['error'=>UPLOAD_ERR_NO_FILE], 'missing photo is rejected');
    $oversizePath = tempnam(sys_get_temp_dir(), 'campusflow-oversize-');
    $temporaryFiles[] = $oversizePath;
    file_put_contents($oversizePath, str_repeat('A', PROFILE_PHOTO_MAX_BYTES + 1));
    $largeError = $rejects(static fn() => store_profile_photo(['error'=>UPLOAD_ERR_OK,'tmp_name'=>$oversizePath,'size'=>PROFILE_PHOTO_MAX_BYTES + 1,'name'=>'large.png','type'=>'image/png'], $storage, $public), 'oversized image is rejected');
    $assert(str_contains($largeError->getMessage(), 'smaller than 5 MB'), 'oversize validation shows the 5 MB guidance');
    $iniError = $rejects(static fn() => store_profile_photo(['error'=>UPLOAD_ERR_INI_SIZE], $storage, $public), 'server upload limit failures are rejected');
    $assert(str_contains($iniError->getMessage(), 'Your image is too large. Please choose a photo smaller than 5 MB.'), 'server upload-limit failures use the requested message');
    $partialError = $rejects(static fn() => store_profile_photo(['error'=>UPLOAD_ERR_PARTIAL], $storage, $public), 'interrupted uploads are rejected');
    $assert(str_contains($partialError->getMessage(), 'interrupted'), 'interrupted uploads receive a clear message');

    $svgPath = tempnam(sys_get_temp_dir(), 'campusflow-svg-');
    $temporaryFiles[] = $svgPath;
    file_put_contents($svgPath, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
    $rejectUpload(['error'=>UPLOAD_ERR_OK,'tmp_name'=>$svgPath,'size'=>filesize($svgPath),'name'=>'avatar.svg','type'=>'image/svg+xml'], 'unsupported SVG content is rejected');

    $fakeImagePath = tempnam(sys_get_temp_dir(), 'campusflow-fake-');
    $temporaryFiles[] = $fakeImagePath;
    file_put_contents($fakeImagePath, "\x89PNG\r\n\x1a\nnot a real image");
    $rejectUpload(['error'=>UPLOAD_ERR_OK,'tmp_name'=>$fakeImagePath,'size'=>filesize($fakeImagePath),'name'=>'fake.png','type'=>'image/png'], 'fake image contents are rejected');

    $phpPath = tempnam(sys_get_temp_dir(), 'campusflow-php-');
    $temporaryFiles[] = $phpPath;
    file_put_contents($phpPath, '<?php echo "executable"; ?>');
    $rejectUpload(['error'=>UPLOAD_ERR_OK,'tmp_name'=>$phpPath,'size'=>filesize($phpPath),'name'=>'../../public/shell.php','type'=>'image/jpeg'], 'executable contents disguised as an image are rejected');

    $assert(!file_exists($public.DIRECTORY_SEPARATOR.'shell.php'), 'manipulated upload filenames cannot write into the web root');
    $rejects(static fn() => profile_photo_storage_directory($public, $public), 'photo storage inside the public root is refused', 'outside the public web root');

    if (!extension_loaded('gd')) {
        echo "SKIP: GD image decoding is unavailable for valid-image transformation checks.\n";
    } else {
        $image = imagecreatetruecolor(64, 48);
        $color = imagecolorallocate($image, 42, 105, 219);
        imagefill($image, 0, 0, $color);
        $validPath = tempnam(sys_get_temp_dir(), 'campusflow-valid-');
        $temporaryFiles[] = $validPath;
        imagepng($image, $validPath);
        imagedestroy($image);
        $validUpload = ['error'=>UPLOAD_ERR_OK,'tmp_name'=>$validPath,'size'=>filesize($validPath),'name'=>'../../profile.php','type'=>'text/html'];
        $key = store_profile_photo($validUpload, $storage, $public);
        $storedPath = $storage.DIRECTORY_SEPARATOR.$key;
        $info = getimagesize($storedPath);
        $assert(profile_photo_key_is_valid($key) && is_file($storedPath), 'valid raster image gets a random server-side filename despite a traversal name');
        $assert(($info['mime'] ?? '') === 'image/webp' && $info[0] === 512 && $info[1] === 512, 'photo is decoded, cropped, resized and re-encoded as a 512px WebP');
        $assert(remove_profile_photo_file($storage, $key, $public) && !file_exists($storedPath), 'photo removal only deletes a validated stored image');
        $sameFileKey = store_profile_photo($validUpload, $storage, $public);
        $sameFileAgainKey = store_profile_photo($validUpload, $storage, $public);
        $assert($sameFileAgainKey !== $sameFileKey, 'selecting the same file repeatedly creates a new cache version');
        remove_profile_photo_file($storage, $sameFileKey, $public);
        remove_profile_photo_file($storage, $sameFileAgainKey, $public);
        foreach (['jpeg','webp'] as $format) {
            $sourceImage = imagecreatetruecolor(48, 64);
            imagefill($sourceImage, 0, 0, imagecolorallocate($sourceImage, 90, 160, 200));
            $sourcePath = tempnam(sys_get_temp_dir(), 'campusflow-'.$format.'-');
            $temporaryFiles[] = $sourcePath;
            if ($format === 'jpeg') imagejpeg($sourceImage, $sourcePath, 90);
            else imagewebp($sourceImage, $sourcePath, 88);
            imagedestroy($sourceImage);
            $formatKey = store_profile_photo(['error'=>UPLOAD_ERR_OK,'tmp_name'=>$sourcePath,'size'=>filesize($sourcePath),'name'=>'untrusted.php','type'=>'text/html'], $storage, $public);
            $formatInfo = getimagesize($storage.DIRECTORY_SEPARATOR.$formatKey);
            $assert(($formatInfo['mime'] ?? '') === 'image/webp' && remove_profile_photo_file($storage, $formatKey, $public), ucfirst($format).' input is decoded and stored as inert WebP');
        }
    }
} finally {
    foreach ($temporaryFiles as $path) if (is_string($path) && is_file($path)) @unlink($path);
    if (is_dir($storage)) {
        foreach (new DirectoryIterator($storage) as $entry) if ($entry->isFile() && !$entry->isDot()) @unlink($entry->getPathname());
        @rmdir($storage);
    }
    @rmdir($public);
    @rmdir($temporaryRoot);
}

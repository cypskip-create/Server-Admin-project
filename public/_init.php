<?php
require_once dirname(__DIR__).'/config/bootstrap.php';
require_once dirname(__DIR__).'/app/Services/AuthService.php';
require_once dirname(__DIR__).'/app/Services/RequestRules.php';
require_once dirname(__DIR__).'/app/Services/ProfilePhotoService.php';

function current_profile_photo_key(): ?string
{
    static $loaded = false;
    static $key = null;
    if ($loaded) return $key;
    $loaded = true;
    $id = profile_photo_user_id($_SESSION['user'] ?? null);
    $query = db()->prepare('SELECT profile_photo_key FROM users WHERE id=?');
    $query->execute([$id]);
    $value = $query->fetchColumn();
    $key = profile_photo_key_is_valid($value) ? $value : null;
    return $key;
}

function user_initials(string $name): string
{
    $parts = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $initials = '';
    foreach (array_slice($parts, 0, 2) as $part) $initials .= mb_strtoupper(mb_substr($part, 0, 1, 'UTF-8'), 'UTF-8');
    return $initials !== '' ? $initials : 'CF';
}

function user_avatar_markup(string $name, string $class = 'avatar', string $alt = ''): string
{
    $key = current_profile_photo_key();
    if ($key !== null) {
        $version = substr(hash('sha256', $key), 0, 12);
        return '<img class="'.e($class).'" src="/profile-photo.php?v='.$version.'" alt="'.e($alt).'" width="48" height="48" decoding="async">';
    }
    return '<span class="'.e($class).' avatar-initials" aria-label="'.e('Initials '.user_initials($name)).'">'.e(user_initials($name)).'</span>';
}

function flash_error(string $message): void { $_SESSION['flash_error'] = $message; }

function asset_version(string $asset): string
{
    static $versions = [];
    if (!isset($versions[$asset])) {
        $path = __DIR__.'/assets/'.$asset;
        $versions[$asset] = is_file($path) ? substr(hash_file('sha256', $path), 0, 12) : '1';
    }
    return $versions[$asset];
}

function page_start(string $title): void
{
    $message = $_SESSION['flash'] ?? '';
    $error = $_SESSION['flash_error'] ?? '';
    unset($_SESSION['flash'], $_SESSION['flash_error']);
    $loggedIn = !empty($_SESSION['user']);
    $name = $loggedIn ? (string)($_SESSION['user']['full_name'] ?? 'Student') : '';
    $avatar = $loggedIn ? user_avatar_markup($name, 'avatar avatar-small') : '';
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#ffffff"><title>'.e($title).' · CampusFlow</title><link rel="stylesheet" href="/assets/style.css?v='.e(asset_version('style.css')).'"><script src="/assets/app.js?v='.e(asset_version('app.js')).'" defer></script></head><body>';
    echo '<header class="site-header"><div class="header-inner"><a class="brand" href="/" aria-label="CampusFlow home">Campus<span>Flow</span></a><button class="menu-toggle" type="button" aria-controls="primary-nav" aria-expanded="false" aria-label="Open navigation menu"><span></span><span></span><span></span></button>';
    echo '<nav class="primary-nav" id="primary-nav" aria-label="Primary navigation"><a href="/">Home</a>';
    if ($loggedIn) {
        echo '<a href="/dashboard.php">Dashboard</a><a href="/requests.php">Requests</a><a href="/notifications.php">Notifications</a><a href="/profile.php">Profile</a>';
        if (($_SESSION['user']['role'] ?? '') === 'admin') echo '<a href="/admin.php">Admin</a>';
        echo '<span class="nav-account">'.$avatar.'<span class="nav-account-name">'.e($name).'</span></span>';
        echo '<form method="post" action="/logout.php" class="nav-logout"><input type="hidden" name="csrf" value="'.e(csrf()).'"><button type="submit">Sign out</button></form>';
    } else {
        echo '<a href="/login.php">Sign in</a><a class="button small nav-cta" href="/signup.php">Create account</a>';
    }
    echo '</nav></div></header><main>';
    if ($message !== '') echo '<p class="notice" role="status">'.e($message).'</p>';
    if ($error !== '') echo '<p class="error" role="alert">'.e($error).'</p>';
}

function page_end(): void
{
    echo '</main><footer>CampusFlow · Student services, made simpler</footer></body></html>';
}

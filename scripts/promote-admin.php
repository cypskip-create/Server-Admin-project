#!/usr/bin/env php
<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not found.\n");
}
if (PHP_OS_FAMILY !== 'Windows' && (!function_exists('posix_geteuid') || posix_geteuid() !== 0)) {
    fwrite(STDERR, "Run this command as root on the application server.\n");
    exit(1);
}
if ($argc !== 2 || !preg_match('/\A[A-Za-z0-9_]{3,40}\z/', $argv[1])) {
    fwrite(STDERR, "Usage: sudo php scripts/promote-admin.php <existing-username>\n");
    exit(2);
}

require dirname(__DIR__).'/config/bootstrap.php';
require dirname(__DIR__).'/app/Services/AdminService.php';
$username = $argv[1];
$lookup = db()->prepare('SELECT role FROM users WHERE username=?');
$lookup->execute([$username]);
$role = $lookup->fetchColumn();
if ($role === false) {
    fwrite(STDERR, "No existing user has that username. No changes were made.\n");
    exit(1);
}
if ($role === 'admin') {
    fwrite(STDOUT, "That account is already an administrator.\n");
    exit(0);
}

fwrite(STDOUT, "Promote existing account '$username' to CampusFlow administrator? Type PROMOTE $username to confirm: ");
$confirmation = fgets(STDIN);
if (!is_string($confirmation) || trim($confirmation) !== 'PROMOTE '.$username) {
    fwrite(STDERR, "Confirmation did not match. No changes were made.\n");
    exit(1);
}

if (!promote_existing_user_to_admin(db(), $username)) {
    fwrite(STDERR, "The account could not be promoted. No changes were made.\n");
    exit(1);
}
error_log('CampusFlow administrator role granted to existing username '.$username.' by server-side CLI.');
fwrite(STDOUT, "Administrator role enabled for the existing account '$username'. No password was changed.\n");

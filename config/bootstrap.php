<?php
declare(strict_types=1);
$envFile = dirname(__DIR__).'/.env';
$env = is_file($envFile) ? (parse_ini_file($envFile, false, INI_SCANNER_RAW) ?: []) : [];
function cfg(string $key, mixed $default=null): mixed { global $env; return $env[$key] ?? $default; }
date_default_timezone_set('Africa/Nairobi');
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_set_cookie_params(['httponly'=>true,'secure'=>filter_var(cfg('SESSION_SECURE','false'), FILTER_VALIDATE_BOOLEAN) || $isHttps,'samesite'=>'Lax','path'=>'/']);
session_start();
if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:; form-action 'self'; frame-ancestors 'none'; base-uri 'self'");
}
if (cfg('APP_ENV', 'production') === 'production') {
    ini_set('display_errors', '0');
    set_exception_handler(static function (Throwable $error): void {
        error_log((string)$error);
        if (!headers_sent()) http_response_code(500);
        echo 'CampusFlow could not complete that request. Please try again later.';
    });
}
require_once __DIR__.'/db.php';
require_once __DIR__.'/security.php';

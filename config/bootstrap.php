<?php
declare(strict_types=1);
$env = parse_ini_file(dirname(__DIR__).'/.env', false, INI_SCANNER_RAW) ?: [];
function cfg(string $key, mixed $default=null): mixed { global $env; return $env[$key] ?? $default; }
date_default_timezone_set('Africa/Nairobi');
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params(['httponly'=>true,'secure'=>filter_var(cfg('SESSION_SECURE','false'), FILTER_VALIDATE_BOOLEAN) || $isHttps,'samesite'=>'Lax','path'=>'/']);
session_start();
require_once __DIR__.'/db.php';
require_once __DIR__.'/security.php';

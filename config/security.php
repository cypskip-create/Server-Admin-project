<?php
function e(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function csrf(): string { if(empty($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function csrf_token_is_valid(mixed $candidate): bool
{
    $expected = $_SESSION['csrf'] ?? null;
    return is_string($expected) && $expected !== ''
        && is_string($candidate) && $candidate !== ''
        && hash_equals($expected, $candidate);
}
function check_csrf(): void
{
    if (!csrf_token_is_valid($_POST['csrf'] ?? null)) {
        http_response_code(419);
        exit('Form expired. Refresh and try again.');
    }
}
function require_login(): void { if(empty($_SESSION['user'])) { header('Location: /login.php'); exit; } }
function is_admin_user(mixed $user): bool { return is_array($user) && ($user['role'] ?? null) === 'admin'; }
function require_admin(): void { require_login(); if(!is_admin_user($_SESSION['user']??null)) { http_response_code(403); exit('Administrator access required.'); } }
function flash(string $s): void { $_SESSION['flash']=$s; }

<?php
function e(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function csrf(): string { if(empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function check_csrf(): void { if(!hash_equals($_SESSION['csrf']??'', $_POST['csrf']??'')) { http_response_code(419); exit('Form expired. Refresh and try again.'); } }
function require_login(): void { if(empty($_SESSION['user'])) { header('Location: /login.php'); exit; } }
function require_admin(): void { require_login(); if(($_SESSION['user']['role']??'')!=='admin') { http_response_code(403); exit('Administrator access required.'); } }
function flash(string $s): void { $_SESSION['flash']=$s; }

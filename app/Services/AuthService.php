<?php
declare(strict_types=1);

function validate_registration(array $input): array
{
    $errors = [];
    $name = trim((string)($input['full_name'] ?? ''));
    $username = trim((string)($input['username'] ?? ''));
    $email = strtolower(trim((string)($input['email'] ?? '')));
    $studentNumber = strtoupper(trim((string)($input['student_number'] ?? '')));
    $password = (string)($input['password'] ?? '');

    if (!preg_match("/^[\p{L}\p{M}][\p{L}\p{M} .’'-]{1,119}$/u", $name)) $errors[] = 'Enter a valid full name (2–120 characters).';
    if (!preg_match('/^[A-Za-z0-9_]{3,40}$/', $username)) $errors[] = 'Username must be 3–40 letters, digits or underscores.';
    if (strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address of at most 190 characters.';
    if (!preg_match('/^[A-Z0-9][A-Z0-9\/-]{2,39}$/', $studentNumber)) $errors[] = 'Student number must be 3–40 letters, digits, slashes or hyphens.';
    if (strlen($password) < 12 || strlen($password) > 72) $errors[] = 'Password must be 12–72 bytes.';
    if (!hash_equals($password, (string)($input['confirm'] ?? ''))) $errors[] = 'Passwords do not match.';
    return ['errors'=>$errors,'values'=>['full_name'=>$name,'username'=>$username,'email'=>$email,'student_number'=>$studentNumber,'password'=>$password]];
}

function register_student(PDO $db, array $input): int
{
    $validated = validate_registration($input);
    if ($validated['errors']) throw new InvalidArgumentException(implode(' ', $validated['errors']));
    $v = $validated['values'];
    $query = $db->prepare("INSERT INTO users(full_name,username,email,student_number,password_hash,role) VALUES(?,?,?,?,?,'student')");
    $query->execute([$v['full_name'],$v['username'],$v['email'],$v['student_number'],password_hash($v['password'], PASSWORD_DEFAULT)]);
    return (int)$db->lastInsertId();
}

function authenticate_student(PDO $db, string $identity, string $password, string $ipAddress): array
{
    $db->exec('DELETE FROM login_attempts WHERE updated_at < CURRENT_TIMESTAMP - INTERVAL 1 DAY');
    $bucket = hash('sha256', $ipAddress."\0".strtolower(trim($identity)));
    $query = $db->prepare('SELECT failures,window_started_at,blocked_until FROM login_attempts WHERE bucket=?');
    $query->execute([$bucket]);
    $attempt = $query->fetch();
    if ($attempt && $attempt['blocked_until'] && strtotime($attempt['blocked_until']) > time()) return ['status'=>'throttled'];

    $query = $db->prepare('SELECT id,full_name,username,email,role,password_hash FROM users WHERE username=? OR email=?');
    $query->execute([trim($identity),strtolower(trim($identity))]);
    $user = $query->fetch();
    if ($user && password_verify($password, $user['password_hash'])) {
        $db->prepare('DELETE FROM login_attempts WHERE bucket=?')->execute([$bucket]);
        unset($user['password_hash']);
        return ['status'=>'authenticated','user'=>$user];
    }

    $withinWindow = $attempt && strtotime($attempt['window_started_at']) > time()-900;
    $failures = $withinWindow ? (int)$attempt['failures']+1 : 1;
    $blockedUntil = $failures >= 8 ? date('Y-m-d H:i:s',time()+900) : null;
    $db->prepare('INSERT INTO login_attempts(bucket,failures,window_started_at,blocked_until) VALUES(?,?,CURRENT_TIMESTAMP,?) ON DUPLICATE KEY UPDATE failures=VALUES(failures),window_started_at=CURRENT_TIMESTAMP,blocked_until=VALUES(blocked_until)')->execute([$bucket,$failures,$blockedUntil]);
    return ['status'=>$failures >= 8 ? 'throttled' : 'invalid'];
}

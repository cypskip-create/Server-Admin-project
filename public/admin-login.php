<?php
require __DIR__.'/_init.php';

if (is_admin_user($_SESSION['user'] ?? null)) {
    header('Location: /admin.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $identity = is_string($_POST['identity'] ?? null) ? trim($_POST['identity']) : '';
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    try {
        $result = authenticate_admin(db(), $identity, $password, (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        if ($result['status'] === 'authenticated') {
            session_regenerate_id(true);
            $_SESSION['user'] = $result['user'];
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
            header('Location: /admin.php');
            exit;
        }
        $error = $result['status'] === 'throttled'
            ? 'Too many attempts. Wait 15 minutes before trying again.'
            : 'The administrator username/email or password is incorrect.';
    } catch (Throwable $exception) {
        error_log((string)$exception);
        $error = 'Administrator sign in is temporarily unavailable. Please try again later.';
    }
}

page_start('Administrator sign in');
?>
<section class="form-wrap" aria-labelledby="admin-login-title">
    <div class="eyebrow">CAMPUSFLOW ADMINISTRATION</div>
    <h1 id="admin-login-title">Administrator sign in</h1>
    <p>Use an authorized CampusFlow administrator account.</p>
    <?php if ($error !== ''): ?><p class="error" role="alert"><?=e($error)?></p><?php endif; ?>
    <form method="post" autocomplete="on">
        <input type="hidden" name="csrf" value="<?=e(csrf())?>">
        <label for="admin-identity">Username or email
            <input id="admin-identity" name="identity" autocomplete="username" autocapitalize="none" spellcheck="false" required>
        </label>
        <label for="admin-password">Password
            <input id="admin-password" type="password" name="password" autocomplete="current-password" required>
        </label>
        <button class="button" type="submit">Sign in to administration →</button>
    </form>
    <p><a href="/login.php">Student sign in</a></p>
</section>
<?php page_end(); ?>

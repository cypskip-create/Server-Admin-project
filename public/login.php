<?php
require __DIR__.'/_init.php';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $identity = is_string($_POST['identity'] ?? null) ? trim($_POST['identity']) : '';
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    try {
        $result = authenticate_student(db(), $identity, $password, (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        if ($result['status'] === 'authenticated') {
            session_regenerate_id(true);
            $_SESSION['user'] = $result['user'];
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
            header('Location: /dashboard.php');
            exit;
        }
        $error = $result['status'] === 'throttled' ? 'Too many attempts. Wait 15 minutes before trying again.' : 'The username/email or password is incorrect.';
    } catch (Throwable $exception) {
        error_log((string)$exception);
        $error = 'Sign in is temporarily unavailable. Please try again later.';
    }
}
page_start('Sign in');
?>
<section class="form-wrap" aria-labelledby="login-title">
    <div class="eyebrow">WELCOME BACK</div>
    <h1 id="login-title">Sign in</h1>
    <p>Your campus support desk is one step away.</p>
    <?php if ($error !== ''): ?><p class="error" role="alert"><?=e($error)?></p><?php endif; ?>
    <form method="post" autocomplete="on">
        <input type="hidden" name="csrf" value="<?=e(csrf())?>">
        <label for="identity">Username or email<input id="identity" name="identity" autocomplete="username" autocapitalize="none" spellcheck="false" value="<?=e(is_string($_POST['identity'] ?? null) ? $_POST['identity'] : '')?>" required></label>
        <label for="password">Password<input id="password" type="password" name="password" autocomplete="current-password" required></label>
        <button class="button" type="submit">Sign in →</button>
    </form>
    <p>New here? <a href="/signup.php">Create an account</a></p>
</section>
<?php page_end(); ?>

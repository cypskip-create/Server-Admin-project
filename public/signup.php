<?php
require __DIR__.'/_init.php';
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $validation = validate_registration($_POST);
    $errors = $validation['errors'];
    if (!$errors) {
        try {
            register_student(db(), $_POST);
            flash('Account created. Sign in to continue.');
            header('Location: /login.php');
            exit;
        } catch (PDOException $error) {
            if ($error->getCode() === '23000') $errors[] = 'That username, email or student number is already registered.';
            else { error_log((string)$error); $errors[] = 'Registration could not be completed. Please try again later.'; }
        } catch (Throwable $error) {
            error_log((string)$error);
            $errors[] = 'Registration could not be completed. Please try again later.';
        }
    }
}
page_start('Create account');
?>
<section class="form-wrap" aria-labelledby="signup-title">
    <div class="eyebrow">STUDENT ACCOUNT</div>
    <h1 id="signup-title">Join CampusFlow</h1>
    <p>Use your official student details to get started.</p>
    <?php foreach ($errors as $error): ?><p class="error" role="alert"><?=e($error)?></p><?php endforeach; ?>
    <form method="post" autocomplete="on">
        <input type="hidden" name="csrf" value="<?=e(csrf())?>">
        <label for="full-name">Full name<input id="full-name" name="full_name" autocomplete="name" maxlength="120" value="<?=e(is_string($_POST['full_name'] ?? null) ? $_POST['full_name'] : '')?>" required></label>
        <label for="username">Username<input id="username" name="username" autocomplete="username" autocapitalize="none" spellcheck="false" minlength="3" maxlength="40" value="<?=e(is_string($_POST['username'] ?? null) ? $_POST['username'] : '')?>" required></label>
        <label for="email">Email address<input id="email" type="email" name="email" autocomplete="email" inputmode="email" autocapitalize="none" maxlength="190" value="<?=e(is_string($_POST['email'] ?? null) ? $_POST['email'] : '')?>" required></label>
        <label for="student-number">Student number<input id="student-number" name="student_number" autocomplete="off" autocapitalize="characters" maxlength="40" value="<?=e(is_string($_POST['student_number'] ?? null) ? $_POST['student_number'] : '')?>" required></label>
        <label for="password">Password<input id="password" type="password" name="password" autocomplete="new-password" minlength="12" maxlength="72" required></label>
        <label for="confirm-password">Confirm password<input id="confirm-password" type="password" name="confirm" autocomplete="new-password" minlength="12" maxlength="72" required></label>
        <button class="button" type="submit">Create account →</button>
    </form>
</section>
<?php page_end(); ?>

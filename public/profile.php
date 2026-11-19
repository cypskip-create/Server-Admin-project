<?php
require __DIR__.'/_init.php';
require_login();
$userId = profile_photo_user_id($_SESSION['user']);
$errors = [];
$query = db()->prepare('SELECT full_name,username,email,student_number,profile_photo_key FROM users WHERE id=?');
$query->execute([$userId]);
$profile = $query->fetch();
if (!$profile) {
    http_response_code(404);
    page_start('Profile unavailable');
    echo '<div class="empty"><h1>Profile unavailable</h1><p>This account could not be loaded.</p></div>';
    page_end();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $name = is_string($_POST['full_name'] ?? null) ? trim($_POST['full_name']) : '';
    $email = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
    $student = is_string($_POST['student_number'] ?? null) ? trim($_POST['student_number']) : '';
    $current = is_string($_POST['current_password'] ?? null) ? $_POST['current_password'] : '';
    $new = is_string($_POST['new_password'] ?? null) ? $_POST['new_password'] : '';
    if (mb_strlen($name) < 2 || mb_strlen($name) > 120) $errors[] = 'Enter a name between 2 and 120 characters.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email.';
    if ($student === '') $errors[] = 'Student number is required.';
    if ($new !== '' && (strlen($new) < 12 || strlen($new) > 72)) $errors[] = 'New password must be 12–72 bytes.';
    if ($new !== '' && $current === '') $errors[] = 'Enter your current password to change it.';
    if (!$errors) {
        try {
            if ($new !== '') {
                $passwordQuery = db()->prepare('SELECT password_hash FROM users WHERE id=?');
                $passwordQuery->execute([$userId]);
                if (!password_verify($current, (string)$passwordQuery->fetchColumn())) {
                    $errors[] = 'Current password is incorrect.';
                } else {
                    db()->prepare('UPDATE users SET full_name=?,email=?,student_number=?,password_hash=? WHERE id=?')->execute([$name,$email,$student,password_hash($new,PASSWORD_DEFAULT),$userId]);
                }
            } else {
                db()->prepare('UPDATE users SET full_name=?,email=?,student_number=? WHERE id=?')->execute([$name,$email,$student,$userId]);
            }
            if (!$errors) {
                $_SESSION['user']['full_name'] = $name;
                $_SESSION['user']['email'] = $email;
                flash('Profile updated.');
                header('Location: /profile.php');
                exit;
            }
        } catch (PDOException $error) {
            $errors[] = $error->getCode() === '23000' ? 'That email or student number is already in use.' : 'Profile could not be updated.';
        }
    }
    $profile['full_name'] = $name;
    $profile['email'] = $email;
    $profile['student_number'] = $student;
}

$hasPhoto = profile_photo_key_is_valid($profile['profile_photo_key']);
page_start('My profile');
?>
<section class="form-wrap wide profile-wrap">
    <div class="eyebrow">ACCOUNT SETTINGS</div>
    <h1>My profile</h1>
    <?php foreach ($errors as $error): ?><p class="error" role="alert"><?=e($error)?></p><?php endforeach; ?>

    <section class="photo-card" aria-labelledby="photo-heading">
        <div class="photo-card-copy">
            <div class="photo-preview-frame" id="photo-preview-frame" data-initials="<?=e(user_initials((string)$profile['full_name']))?>">
                <?=user_avatar_markup((string)$profile['full_name'], 'profile-avatar', 'Profile photo of '.(string)$profile['full_name'])?>
            </div>
            <div><h2 id="photo-heading">Profile photo</h2><p class="hint">Personalize your CampusFlow account.</p></div>
        </div>
        <form class="photo-upload-form" id="photo-upload-form" action="/profile-photo.php" method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?=e(csrf())?>">
            <input type="hidden" name="photo_action" value="upload">
            <label for="profile-photo-input">Choose a photo</label>
            <input id="profile-photo-input" name="profile_photo" type="file" accept="image/jpeg,image/png,image/webp" aria-describedby="photo-guidance" required>
            <p class="hint" id="photo-guidance">JPEG, PNG or WebP · maximum 2 MB. Your image is resized and stored privately.</p>
            <div class="photo-actions">
                <button class="button" id="photo-upload-button" type="submit">Change photo</button>
                <?php if ($hasPhoto): ?>
                    <button class="button button-secondary" type="submit" form="remove-photo-form">Remove photo</button>
                <?php endif; ?>
            </div>
        </form>
        <?php if ($hasPhoto): ?>
            <form id="remove-photo-form" action="/profile-photo.php" method="post" hidden>
                <input type="hidden" name="csrf" value="<?=e(csrf())?>">
                <input type="hidden" name="photo_action" value="remove">
            </form>
        <?php endif; ?>
        <p class="photo-preview-status" id="photo-preview-status" aria-live="polite"></p>
    </section>

    <form method="post" class="profile-details-form">
        <input type="hidden" name="csrf" value="<?=e(csrf())?>">
        <label for="full-name">Full name
            <input id="full-name" name="full_name" maxlength="120" autocomplete="name" value="<?=e($profile['full_name'])?>" required>
        </label>
        <label for="username">Username
            <input id="username" value="<?=e($profile['username'])?>" autocomplete="username" disabled>
        </label>
        <label for="email">Email
            <input id="email" type="email" name="email" maxlength="190" autocomplete="email" value="<?=e($profile['email'])?>" required>
        </label>
        <label for="student-number">Student number
            <input id="student-number" name="student_number" maxlength="40" autocomplete="off" value="<?=e($profile['student_number'])?>" required>
        </label>
        <hr>
        <h2>Change password</h2>
        <label for="current-password">Current password
            <input id="current-password" type="password" name="current_password" autocomplete="current-password">
        </label>
        <label for="new-password">New password <span class="hint">(leave blank to keep your current password)</span>
            <input id="new-password" type="password" name="new_password" minlength="12" maxlength="72" autocomplete="new-password">
        </label>
        <button class="button" type="submit">Save profile</button>
    </form>
</section>
<?php page_end(); ?>

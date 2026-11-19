<?php
require __DIR__.'/_init.php';
require_login();
$errors = [];
$categories = ['IT Support','Library Services','Accommodation','Academic Support','General Administration'];
$form = ['category'=>'','title'=>'','description'=>'','priority'=>'normal'];
$recommendation = 'normal';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    foreach ($form as $key => $unused) $form[$key] = is_string($_POST[$key] ?? null) ? trim($_POST[$key]) : '';
    $recommendation = recommend_priority($form['category'], $form['title'], $form['description']);
    if (!in_array($form['category'], $categories, true)) $errors[] = 'Choose a listed category.';
    if (mb_strlen($form['title']) < 4 || mb_strlen($form['title']) > 120) $errors[] = 'Title must be 4–120 characters.';
    if (mb_strlen($form['description']) < 10 || mb_strlen($form['description']) > 5000) $errors[] = 'Description must be 10–5000 characters.';
    if (!in_array($form['priority'], ['low','normal','high'], true)) $errors[] = 'Choose a valid priority.';
    if (!$errors) {
        try {
            $tracking = create_service_request(db(), (int)$_SESSION['user']['id'], $form['category'], $form['title'], $form['description'], $form['priority']);
            flash('Request submitted. Tracking ID: '.$tracking);
            header('Location: /requests.php');
            exit;
        } catch (Throwable $error) {
            error_log((string)$error);
            $errors[] = 'Your request could not be submitted. Please try again.';
        }
    }
}
page_start('New request');
?>
<section class="form-wrap wide" aria-labelledby="request-title">
    <div class="eyebrow">CAMPUS SUPPORT</div>
    <h1 id="request-title">What can we help with?</h1>
    <?php foreach ($errors as $error): ?><p class="error" role="alert"><?=e($error)?></p><?php endforeach; ?>
    <form method="post" id="request-form">
        <input type="hidden" name="csrf" value="<?=e(csrf())?>">
        <label for="category">Category<select id="category" name="category" required><option value="">Choose a category</option><?php foreach ($categories as $category): ?><option value="<?=e($category)?>" <?=$form['category'] === $category ? 'selected' : ''?>><?=e($category)?></option><?php endforeach; ?></select></label>
        <label for="title">Request title<input id="title" name="title" maxlength="120" autocomplete="off" value="<?=e($form['title'])?>" required></label>
        <label for="description">What happened?<textarea id="description" name="description" rows="6" maxlength="5000" required><?=e($form['description'])?></textarea></label>
        <label for="priority">Priority<select id="priority" name="priority"><?php foreach (['normal'=>'Normal','high'=>'High','low'=>'Low'] as $value => $label): ?><option value="<?=e($value)?>" <?=$form['priority'] === $value ? 'selected' : ''?>><?=e($label)?></option><?php endforeach; ?></select></label>
        <p class="hint" id="priority-hint" aria-live="polite">Server recommendation: <strong><?=e($recommendation)?></strong>. High priority has a 2-day initial response target, normal 5 days, low 8 days. The recommendation uses urgency, security and outage keywords; you choose the final priority.</p>
        <button class="button" type="submit">Submit request →</button>
    </form>
</section>
<script src="/assets/request-hints.js?v=<?=e(asset_version('request-hints.js'))?>" defer></script>
<?php page_end(); ?>

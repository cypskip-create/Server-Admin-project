<?php
require __DIR__.'/_init.php';
require_login();
$userId = profile_photo_user_id($_SESSION['user']);
$isAdmin = is_admin_user($_SESSION['user']);
if ($isAdmin) require_admin();
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$id = is_int($id) && $id > 0 ? $id : 0;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$isAdmin) {
        http_response_code(403);
        exit('Administrator access required.');
    }
    check_csrf();
    $newStatus = is_string($_POST['status'] ?? null) ? $_POST['status'] : '';
    $note = is_string($_POST['admin_note'] ?? null) ? trim($_POST['admin_note']) : '';
    if ($id < 1 || !in_array($newStatus, ADMIN_REQUEST_STATUSES, true)) {
        $errors[] = 'Choose a valid request status.';
    } elseif (mb_strlen($note, 'UTF-8') > 225) {
        $errors[] = 'Response must be 225 characters or fewer.';
    } else {
        try {
            $currentQuery = db()->prepare('SELECT status FROM service_requests WHERE id=?');
            $currentQuery->execute([$id]);
            $currentStatus = $currentQuery->fetchColumn();
            if ($currentStatus === false) {
                http_response_code(404);
                $errors[] = 'This request could not be found.';
            } elseif ($currentStatus === $newStatus && $note === '') {
                $errors[] = 'Choose a different status or add a response before saving.';
            } elseif (!update_request_status(db(), $id, $userId, $newStatus, $note)) {
                http_response_code(404);
                $errors[] = 'This request could not be found.';
            } else {
                flash('Request update saved and the student has been notified.');
                header('Location: /request.php?id='.$id, true, 303);
                exit;
            }
        } catch (InvalidArgumentException $exception) {
            $errors[] = $exception->getMessage();
        } catch (Throwable $exception) {
            error_log((string)$exception);
            $errors[] = 'The request update could not be saved. Please try again.';
        }
    }
}

if ($isAdmin) {
    $query = db()->prepare('SELECT r.*,u.full_name,u.username FROM service_requests r JOIN users u ON u.id=r.user_id WHERE r.id=?');
    $query->execute([$id]);
} else {
    $query = db()->prepare('SELECT r.*,NULL AS full_name,NULL AS username FROM service_requests r WHERE r.id=? AND r.user_id=?');
    $query->execute([$id,$userId]);
}
$request = $query->fetch();
if (!$request) {
    http_response_code(404);
    page_start('Request not found');
    echo '<div class="empty"><h1>Request not found</h1><p>This request may have been removed or belongs to another account.</p><a href="'.($isAdmin?'/admin.php':'/requests.php').'">Back to '.($isAdmin?'administration':'requests').'</a></div>';
    page_end();
    exit;
}
$historyQuery = db()->prepare('SELECT h.old_status,h.new_status,h.note,h.created_at,u.username,u.role FROM request_status_history h JOIN users u ON u.id=h.changed_by WHERE h.request_id=? ORDER BY h.created_at DESC,h.id DESC');
$historyQuery->execute([$id]);
$history = $historyQuery->fetchAll();
$due = !empty($request['sla_due_at']) ? strtotime((string)$request['sla_due_at']) : null;
$responded = !empty($request['first_response_at']) ? strtotime((string)$request['first_response_at']) : null;
$sla = $responded ? ($due && $responded <= $due ? 'met' : 'breached') : ($due && time() > $due ? 'breached' : 'on track');
page_start('Request '.e($request['tracking_id']));
?>
<div class="row"><div><div class="eyebrow">TRACKING ID · <?=e($request['tracking_id'])?></div><h1><?=e($request['title'])?></h1></div><a href="<?=$isAdmin?'/admin.php':'/requests.php'?>">← <?=$isAdmin?'Admin dashboard':'All requests'?></a></div>
<?php foreach ($errors as $error): ?><p class="error" role="alert"><?=e($error)?></p><?php endforeach; ?>
<div class="detail-grid">
    <section class="panel request-detail-panel">
        <div class="request-detail-heading"><span class="pill"><?=e(ucwords(str_replace('_',' ',$request['status'])))?></span><?php if ($isAdmin): ?><span>Student: <strong><?=e($request['full_name'])?> (@<?=e($request['username'])?>)</strong></span><?php endif; ?></div>
        <p><?=nl2br(e($request['description']))?></p>
        <div class="request-meta">
            <span>Category<strong><?=e($request['category'])?></strong></span>
            <span>Chosen priority<strong><?=e(ucfirst($request['priority']))?></strong></span>
            <span>Suggested priority<strong><?=e(ucfirst($request['recommended_priority']))?></strong></span>
            <span>Initial response SLA<strong><?=e(ucfirst($sla))?></strong></span>
            <span>Response due<strong><?=e($request['sla_due_at'] ?? 'Unavailable')?></strong></span>
            <span>Submitted<strong><?=e($request['created_at'])?></strong></span>
        </div>
    </section>
    <?php if ($isAdmin): ?>
        <section class="panel admin-response-panel" aria-labelledby="admin-response-title">
            <h2 id="admin-response-title">Update this request</h2>
            <p class="hint">Status changes and your response are saved together and shown to the student.</p>
            <form method="post" action="/request.php?id=<?=e($id)?>">
                <input type="hidden" name="csrf" value="<?=e(csrf())?>">
                <label for="admin-request-status">Status
                    <select id="admin-request-status" name="status" required><?php foreach (ADMIN_REQUEST_STATUSES as $status): ?><option value="<?=e($status)?>" <?=$status === $request['status'] ? 'selected' : ''?>><?=e(ucwords(str_replace('_',' ',$status)))?></option><?php endforeach; ?></select>
                </label>
                <label for="admin-note">Response to the student <span class="hint">(optional, 225 characters maximum; appears in the student's timeline and notifications)</span>
                    <textarea id="admin-note" name="admin_note" rows="4" maxlength="225" placeholder="Write a short update for the student"></textarea>
                </label>
                <button class="button" type="submit">Save update and notify student</button>
            </form>
        </section>
    <?php endif; ?>
    <section class="panel timeline-panel"><h2>Status timeline</h2>
        <?php if (!$history): ?><p class="hint">No status history is available.</p><?php else: ?>
            <ol class="timeline">
                <?php foreach ($history as $item): ?>
                    <li><b><?php if ($item['old_status']): ?><?=e(ucwords(str_replace('_',' ',$item['old_status'])))?> → <?php endif; ?><?=e(ucwords(str_replace('_',' ',$item['new_status'])))?></b><?php if ($item['note']): ?><p><?=e($item['note'])?></p><?php endif; ?><small><?=e($item['created_at'])?> · <?=e($item['role']==='admin'?'Administrator':'Student')?></small></li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </section>
</div>
<?php page_end(); ?>

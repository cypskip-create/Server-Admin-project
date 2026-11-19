<?php
require __DIR__.'/_init.php';
require_admin();
$statuses = ['submitted','in_review','in_progress','completed','declined'];
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    $newStatus = is_string($_POST['status'] ?? null) ? $_POST['status'] : '';
    if (!$id || !in_array($newStatus, $statuses, true)) {
        $error = 'Choose a valid request and status.';
    } else {
        try {
            if (!update_request_status(db(), (int)$id, (int)$_SESSION['user']['id'], $newStatus)) $error = 'Request not found.';
            else {
                flash('Request status updated.');
                header('Location: /admin.php');
                exit;
            }
        } catch (Throwable $exception) {
            error_log((string)$exception);
            $error = 'Status update failed. Please try again.';
        }
    }
}
$filter = is_string($_GET['status'] ?? null) ? $_GET['status'] : '';
$search = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
$where = [];
$parameters = [];
if (in_array($filter, $statuses, true)) { $where[] = 'r.status=?'; $parameters[] = $filter; }
if ($search !== '') {
    $where[] = '(r.tracking_id LIKE ? OR r.title LIKE ? OR u.username LIKE ? OR u.full_name LIKE ?)';
    $like = '%'.$search.'%';
    array_push($parameters, $like, $like, $like, $like);
}
$sql = 'SELECT r.*,u.full_name,u.username FROM service_requests r JOIN users u ON u.id=r.user_id'.($where ? ' WHERE '.implode(' AND ', $where) : '').' ORDER BY r.created_at DESC';
$query = db()->prepare($sql);
$query->execute($parameters);
$rows = $query->fetchAll();
page_start('Request administration');
?>
<div class="eyebrow">CAMPUSFLOW ADMIN</div><h1>Request queue</h1>
<?php if ($error !== ''): ?><p class="error" role="alert"><?=e($error)?></p><?php endif; ?>
<form method="get" class="filters" role="search">
    <label for="admin-search">Search
        <input id="admin-search" name="q" value="<?=e($search)?>" placeholder="Student, title or tracking ID" autocomplete="off">
    </label>
    <label for="admin-status">Status
        <select id="admin-status" name="status"><option value="">All statuses</option><?php foreach ($statuses as $status): ?><option value="<?=e($status)?>" <?=$filter === $status ? 'selected' : ''?>><?=e(ucwords(str_replace('_', ' ', $status)))?></option><?php endforeach; ?></select>
    </label>
    <button class="button small" type="submit">Filter requests</button>
</form>
<?php if (!$rows): ?>
    <div class="empty"><h2>No matching requests</h2><p>Try a different search or status filter.</p></div>
<?php else: ?>
    <p class="table-scroll-hint">Swipe the table horizontally to see all columns →</p>
    <div class="table-wrap">
        <table><thead><tr><th>Student</th><th>Request</th><th>Tracking</th><th>Priority</th><th>Status</th><th>Update</th></tr></thead><tbody>
        <?php foreach ($rows as $request): ?>
            <tr>
                <td><?=e($request['full_name'])?><small>@<?=e($request['username'])?></small></td>
                <td><a href="/request.php?id=<?=e($request['id'])?>"><?=e($request['title'])?></a><small><?=e($request['category'])?></small></td>
                <td><?=e($request['tracking_id'])?></td>
                <td><?=e(ucfirst($request['priority']))?></td>
                <td><span class="pill"><?=e(str_replace('_', ' ', $request['status']))?></span></td>
                <td><form method="post" class="inline"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="id" value="<?=e($request['id'])?>"><label class="sr-only" for="table-status-<?=e($request['id'])?>">Status for <?=e($request['tracking_id'])?></label><select id="table-status-<?=e($request['id'])?>" name="status"><?php foreach ($statuses as $status): ?><option value="<?=e($status)?>" <?=$status === $request['status'] ? 'selected' : ''?>><?=e(ucwords(str_replace('_', ' ', $status)))?></option><?php endforeach; ?></select><button class="button small" type="submit">Save</button></form></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table>
    </div>
    <div class="admin-request-cards" aria-label="Requests">
        <?php foreach ($rows as $request): ?>
            <article class="admin-request-card">
                <div class="admin-request-heading"><div><span class="eyebrow"><?=e($request['tracking_id'])?></span><h2><a href="/request.php?id=<?=e($request['id'])?>"><?=e($request['title'])?></a></h2></div><span class="pill"><?=e(str_replace('_', ' ', $request['status']))?></span></div>
                <div class="admin-request-meta"><span>Student<strong><?=e($request['full_name'])?> · @<?=e($request['username'])?></strong></span><span>Category<strong><?=e($request['category'])?></strong></span><span>Priority<strong><?=e(ucfirst($request['priority']))?></strong></span></div>
                <form method="post" class="inline"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="id" value="<?=e($request['id'])?>"><label class="sr-only" for="mobile-status-<?=e($request['id'])?>">Update status for <?=e($request['tracking_id'])?></label><select id="mobile-status-<?=e($request['id'])?>" name="status"><?php foreach ($statuses as $status): ?><option value="<?=e($status)?>" <?=$status === $request['status'] ? 'selected' : ''?>><?=e(ucwords(str_replace('_', ' ', $status)))?></option><?php endforeach; ?></select><button class="button small" type="submit">Save status</button></form>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php page_end(); ?>

<?php
require __DIR__.'/_init.php';
require_login();
$query = db()->prepare('SELECT * FROM service_requests WHERE user_id=? ORDER BY created_at DESC');
$query->execute([$_SESSION['user']['id']]);
$rows = $query->fetchAll();
page_start('My requests');
?>
<div class="row page-heading"><div><div class="eyebrow">YOUR SUPPORT HISTORY</div><h1>My requests</h1></div><a class="button" href="/new-request.php">New request +</a></div>
<?php if (!$rows): ?>
    <div class="empty"><p>No requests yet.</p><a href="/new-request.php">Create your first request →</a></div>
<?php else: ?>
    <p class="table-scroll-hint">Swipe the table horizontally to see all columns →</p>
    <div class="table-wrap">
        <table><thead><tr><th>Tracking ID</th><th>Request</th><th>Category</th><th>Priority</th><th>Status</th><th>Expected response</th><th>Submitted</th></tr></thead><tbody>
        <?php foreach ($rows as $request): ?>
            <tr>
                <td><?=e($request['tracking_id'])?></td>
                <td><a href="/request.php?id=<?=e($request['id'])?>"><?=e($request['title'])?></a></td>
                <td><?=e($request['category'])?></td>
                <td><?=e(ucfirst($request['priority']))?></td>
                <td><span class="pill"><?=e(str_replace('_', ' ', $request['status']))?></span></td>
                <td>About <?=e($request['expected_days'])?> days</td>
                <td><?=e($request['created_at'])?></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table>
    </div>
    <div class="request-cards" aria-label="Service requests">
        <?php foreach ($rows as $request): ?>
            <article class="request-card">
                <div class="request-card-heading">
                    <div><span class="eyebrow"><?=e($request['tracking_id'])?></span><h2><a href="/request.php?id=<?=e($request['id'])?>"><?=e($request['title'])?></a></h2></div>
                    <span class="pill"><?=e(str_replace('_', ' ', $request['status']))?></span>
                </div>
                <div class="request-card-meta">
                    <span>Category<strong><?=e($request['category'])?></strong></span>
                    <span>Priority<strong><?=e(ucfirst($request['priority']))?></strong></span>
                    <span>Expected response<strong>About <?=e($request['expected_days'])?> days</strong></span>
                    <span>Submitted<strong><?=e($request['created_at'])?></strong></span>
                </div>
                <p><a href="/request.php?id=<?=e($request['id'])?>">View tracking timeline →</a></p>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php page_end(); ?>

<?php
require __DIR__.'/_init.php';
require_login();
$userId = (int)$_SESSION['user']['id'];
$query = db()->prepare("SELECT COUNT(*) total,SUM(status IN ('submitted','in_review','in_progress')) open_count,SUM(status='completed') done FROM service_requests WHERE user_id=?");
$query->execute([$userId]);
$stats = $query->fetch();
$query = db()->prepare('SELECT status,COUNT(*) amount FROM service_requests WHERE user_id=? GROUP BY status');
$query->execute([$userId]);
$breakdown = [];
foreach ($query->fetchAll() as $row) $breakdown[$row['status']] = (int)$row['amount'];
$query = db()->prepare('SELECT id,tracking_id,title,category,priority,status,created_at FROM service_requests WHERE user_id=? ORDER BY created_at DESC LIMIT 5');
$query->execute([$userId]);
$recent = $query->fetchAll();
$total = (int)($stats['total'] ?? 0);
$name = (string)($_SESSION['user']['full_name'] ?? 'Student');
page_start('Dashboard');
?>
<section class="greeting" aria-labelledby="dashboard-greeting">
    <?=user_avatar_markup($name, 'avatar avatar-large', 'Profile photo of '.$name)?>
    <div class="greeting-copy">
        <div class="eyebrow">STUDENT SPACE</div>
        <h1 id="dashboard-greeting">Hello, <?=e(explode(' ', $name)[0])?>.</h1>
        <p>Here’s your campus support at a glance.</p>
    </div>
</section>

<section class="stats" aria-label="Request statistics">
    <article><small>ALL REQUESTS</small><strong><?=$total?></strong></article>
    <article><small>IN PROGRESS</small><strong><?=e($stats['open_count'] ?? 0)?></strong></article>
    <article><small>COMPLETED</small><strong><?=e($stats['done'] ?? 0)?></strong></article>
</section>

<section class="panel chart-panel" aria-labelledby="status-overview-title">
    <div class="row"><h2 id="status-overview-title">Request status overview</h2><a href="/requests.php">See requests →</a></div>
    <?php if (!$total): ?>
        <p class="hint">Your request status chart will appear after your first submission.</p>
    <?php else: ?>
        <?php foreach (['submitted'=>'Submitted','in_review'=>'In review','in_progress'=>'In progress','completed'=>'Completed','declined'=>'Declined'] as $status => $label): $count = $breakdown[$status] ?? 0; $percentage = (int)round(($count / $total) * 100); ?>
            <div class="bar-row"><span><?=e($label)?></span><div class="bar-track" role="img" aria-label="<?=$percentage?> percent"><i style="width:<?=$percentage?>%"></i></div><b><?=$count?></b></div>
        <?php endforeach; ?>
    <?php endif; ?>
</section>

<div class="row"><h2>Recent requests</h2><a href="/requests.php">View all →</a></div>
<?php if (!$recent): ?>
    <div class="empty"><h3>Your support desk is ready</h3><p>Submit a request and track it here.</p><a class="button" href="/new-request.php">New request →</a></div>
<?php else: ?>
    <p class="table-scroll-hint">Swipe the table horizontally to see all columns →</p>
    <div class="table-wrap">
        <table><thead><tr><th>Tracking ID</th><th>Request</th><th>Status</th><th>Date</th></tr></thead><tbody>
        <?php foreach ($recent as $request): ?><tr><td><?=e($request['tracking_id'])?></td><td><a href="/request.php?id=<?=e($request['id'])?>"><?=e($request['title'])?></a></td><td><span class="pill"><?=e(str_replace('_', ' ', $request['status']))?></span></td><td><?=e($request['created_at'])?></td></tr><?php endforeach; ?>
        </tbody></table>
    </div>
    <div class="request-cards" aria-label="Recent service requests">
        <?php foreach ($recent as $request): ?>
            <article class="request-card">
                <div class="request-card-heading"><div><span class="eyebrow"><?=e($request['tracking_id'])?></span><h2><a href="/request.php?id=<?=e($request['id'])?>"><?=e($request['title'])?></a></h2></div><span class="pill"><?=e(str_replace('_', ' ', $request['status']))?></span></div>
                <div class="request-card-meta"><span>Category<strong><?=e($request['category'])?></strong></span><span>Priority<strong><?=e(ucfirst($request['priority']))?></strong></span><span>Submitted<strong><?=e($request['created_at'])?></strong></span></div>
                <p><a href="/request.php?id=<?=e($request['id'])?>">View request details →</a></p>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<p><a class="button" href="/new-request.php">Submit a service request</a> <a href="/profile.php">Edit profile</a></p>
<?php page_end(); ?>

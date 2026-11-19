<?php
require __DIR__.'/_init.php';
require_admin();

$summary = admin_dashboard_summary(db());
$categories = admin_request_categories(db());
$statuses = ADMIN_REQUEST_STATUSES;
$validStatus = is_string($_GET['status'] ?? null) && in_array($_GET['status'], $statuses, true) ? $_GET['status'] : '';
$validCategory = is_string($_GET['category'] ?? null) && in_array($_GET['category'], $categories, true) ? $_GET['category'] : '';
$validPriority = is_string($_GET['priority'] ?? null) && in_array($_GET['priority'], ['low','normal','high'], true) ? $_GET['priority'] : '';
$search = is_string($_GET['q'] ?? null) ? trim(mb_substr($_GET['q'], 0, 100)) : '';
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
$page = is_int($page) && $page > 0 ? min($page, 10000) : 1;
$result = admin_find_requests(db(), ['status'=>$validStatus,'category'=>$validCategory,'priority'=>$validPriority,'search'=>$search], $page);
$recent = admin_recent_requests(db());
$urgent = admin_urgent_requests(db());
$slaWatch = admin_sla_watch_requests(db());
$pageCount = max(1, (int)ceil($result['total'] / $result['per_page']));
$queryArgs = array_filter(['q'=>$search,'status'=>$validStatus,'category'=>$validCategory,'priority'=>$validPriority], static fn($value): bool=>$value !== '');
$statusLabel = static fn(string $status): string => ucwords(str_replace('_', ' ', $status));
$deadlineLabel = static function (array $request): string {
    if (!empty($request['first_response_at'])) return 'Responded '.date('M j, Y', strtotime((string)$request['first_response_at']));
    if (empty($request['sla_due_at'])) return 'No deadline';
    $due = strtotime((string)$request['sla_due_at']);
    if ($due < time()) return 'Overdue · '.date('M j, Y', $due);
    return 'Due '.date('M j, Y', $due);
};

page_start('Administrator dashboard');
?>
<div class="admin-dashboard" aria-labelledby="admin-title">
    <div class="admin-page-heading">
        <div><div class="eyebrow">CAMPUSFLOW ADMINISTRATION</div><h1 id="admin-title">Service desk overview</h1></div>
        <a class="button small" href="#request-queue">Manage requests</a>
    </div>

    <section class="admin-stat-grid" aria-label="Request and student totals">
        <?php foreach ([
            ['Registered students',$summary['students']],['Submitted requests',$summary['total_requests']],
            ['Pending',$summary['submitted']],['In review',$summary['in_review']],
            ['In progress',$summary['in_progress']],['Completed',$summary['completed']],['Declined',$summary['declined']],
            ['Urgent',$summary['urgent']],['Overdue',$summary['overdue']],['Due in 24 hours',$summary['due_soon']],
        ] as [$label,$value]): ?>
            <article class="admin-stat-card"><span><?=e($label)?></span><strong><?=e($value)?></strong></article>
        <?php endforeach; ?>
    </section>

    <div class="admin-section-grid">
        <section class="panel admin-list-panel" aria-labelledby="urgent-title">
            <div class="row"><h2 id="urgent-title">Urgent attention</h2><span class="pill"><?=$summary['urgent']?> open</span></div>
            <?php if (!$urgent): ?><p class="hint">No open high-priority requests.</p><?php else: ?>
                <ul class="admin-compact-list">
                    <?php foreach ($urgent as $request): ?>
                        <li><a href="/request.php?id=<?=e($request['id'])?>"><strong><?=e($request['title'])?></strong><span><?=e($request['tracking_id'])?> · <?=e($request['full_name'])?> · <?=e(ucfirst($request['status']))?></span></a><small><?=e($deadlineLabel($request))?></small></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
        <section class="panel admin-list-panel" aria-labelledby="sla-title">
            <div class="row"><h2 id="sla-title">Response deadlines</h2><span class="pill"><?=$summary['overdue']?> overdue</span></div>
            <?php if (!$slaWatch): ?><p class="hint">No unanswered requests are due in the next 24 hours.</p><?php else: ?>
                <ul class="admin-compact-list">
                    <?php foreach ($slaWatch as $request): ?>
                        <li><a href="/request.php?id=<?=e($request['id'])?>"><strong><?=e($request['title'])?></strong><span><?=e($request['tracking_id'])?> · <?=e($request['full_name'])?> · <?=e(ucfirst($request['priority']))?> priority</span></a><small><?=e($deadlineLabel($request))?></small></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>

    <section class="panel admin-list-panel" aria-labelledby="recent-title">
        <div class="row"><h2 id="recent-title">Recent submissions</h2><a href="#request-queue">View request queue</a></div>
        <?php if (!$recent): ?><p class="hint">No service requests have been submitted.</p><?php else: ?>
            <ul class="admin-compact-list recent-list">
                <?php foreach ($recent as $request): ?>
                    <li><a href="/request.php?id=<?=e($request['id'])?>"><strong><?=e($request['title'])?></strong><span><?=e($request['tracking_id'])?> · <?=e($request['full_name'])?> (@<?=e($request['username'])?>) · <?=e($request['category'])?></span></a><small><?=e(date('M j, Y H:i', strtotime((string)$request['created_at'])))?></small></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section id="request-queue" class="admin-queue" aria-labelledby="queue-title">
        <div class="admin-page-heading"><div><div class="eyebrow">STUDENT SERVICES</div><h2 id="queue-title">All requests</h2></div><span class="hint"><?=$result['total']?> matching</span></div>
        <form method="get" class="filters admin-filters" role="search">
            <label for="admin-search">Search students and requests
                <input id="admin-search" name="q" value="<?=e($search)?>" maxlength="100" placeholder="Name, username, title or tracking ID" autocomplete="off">
            </label>
            <label for="admin-status">Status
                <select id="admin-status" name="status"><option value="">All statuses</option><?php foreach ($statuses as $status): ?><option value="<?=e($status)?>" <?=$validStatus === $status ? 'selected' : ''?>><?=e($statusLabel($status))?></option><?php endforeach; ?></select>
            </label>
            <label for="admin-category">Category
                <select id="admin-category" name="category"><option value="">All categories</option><?php foreach ($categories as $category): ?><option value="<?=e($category)?>" <?=$validCategory === $category ? 'selected' : ''?>><?=e($category)?></option><?php endforeach; ?></select>
            </label>
            <label for="admin-priority">Priority
                <select id="admin-priority" name="priority"><option value="">All priorities</option><?php foreach (['high','normal','low'] as $priority): ?><option value="<?=e($priority)?>" <?=$validPriority === $priority ? 'selected' : ''?>><?=e(ucfirst($priority))?></option><?php endforeach; ?></select>
            </label>
            <button class="button small" type="submit">Apply filters</button>
        </form>

        <?php if (!$result['rows']): ?>
            <div class="empty"><h3>No matching requests</h3><p>Clear one or more filters to see other requests.</p></div>
        <?php else: ?>
            <div class="table-wrap admin-table-wrap">
                <table><thead><tr><th>Student</th><th>Request</th><th>Priority</th><th>Status</th><th>Response deadline</th><th>Submitted</th></tr></thead><tbody>
                    <?php foreach ($result['rows'] as $request): ?>
                        <tr>
                            <td><?=e($request['full_name'])?><small>@<?=e($request['username'])?></small></td>
                            <td><a href="/request.php?id=<?=e($request['id'])?>"><?=e($request['title'])?></a><small><?=e($request['tracking_id'])?> · <?=e($request['category'])?></small></td>
                            <td><?=e(ucfirst($request['priority']))?></td><td><span class="pill"><?=e($statusLabel($request['status']))?></span></td>
                            <td><?=e($deadlineLabel($request))?></td><td><?=e(date('M j, Y H:i', strtotime((string)$request['created_at'])))?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody></table>
            </div>
            <div class="admin-request-cards" aria-label="Request list">
                <?php foreach ($result['rows'] as $request): ?>
                    <article class="admin-request-card">
                        <div class="admin-request-heading"><div><span class="eyebrow"><?=e($request['tracking_id'])?></span><h3><a href="/request.php?id=<?=e($request['id'])?>"><?=e($request['title'])?></a></h3></div><span class="pill"><?=e($statusLabel($request['status']))?></span></div>
                        <p class="admin-card-description"><?=e(mb_strimwidth((string)$request['description'], 0, 180, '…', 'UTF-8'))?></p>
                        <div class="admin-request-meta"><span>Student<strong><?=e($request['full_name'])?> · @<?=e($request['username'])?></strong></span><span>Category<strong><?=e($request['category'])?></strong></span><span>Priority<strong><?=e(ucfirst($request['priority']))?></strong></span><span>Response<strong><?=e($deadlineLabel($request))?></strong></span></div>
                        <a class="button small" href="/request.php?id=<?=e($request['id'])?>">Open request details</a>
                    </article>
                <?php endforeach; ?>
            </div>
            <?php if ($pageCount > 1): ?>
                <nav class="pagination" aria-label="Request pages">
                    <?php if ($page > 1): ?><a class="button button-secondary small" href="?<?=e(http_build_query($queryArgs + ['page'=>$page-1]))?>">Previous</a><?php endif; ?>
                    <span>Page <?=$page?> of <?=$pageCount?></span>
                    <?php if ($page < $pageCount): ?><a class="button button-secondary small" href="?<?=e(http_build_query($queryArgs + ['page'=>$page+1]))?>">Next</a><?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</div>
<?php page_end(); ?>

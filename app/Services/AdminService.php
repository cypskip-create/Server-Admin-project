<?php
declare(strict_types=1);

const ADMIN_REQUEST_STATUSES = ['submitted','in_review','in_progress','completed','declined'];
const ADMIN_FINAL_REQUEST_STATUSES = ['completed','declined'];

function promote_existing_user_to_admin(PDO $db, string $username): bool
{
    $db->beginTransaction();
    try {
        $query = $db->prepare('SELECT role FROM users WHERE username=? FOR UPDATE');
        $query->execute([$username]);
        $role = $query->fetchColumn();
        if ($role === false) {
            $db->rollBack();
            return false;
        }
        if ($role !== 'admin') {
            $db->prepare("UPDATE users SET role='admin' WHERE username=?")->execute([$username]);
        }
        $db->commit();
        return true;
    } catch (Throwable $error) {
        if ($db->inTransaction()) $db->rollBack();
        throw $error;
    }
}

function admin_dashboard_summary(PDO $db): array
{
    $students = (int)$db->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn();
    $query = $db->query("SELECT
        COUNT(*) AS total_requests,
        COALESCE(SUM(status='submitted'),0) AS submitted,
        COALESCE(SUM(status='in_review'),0) AS in_review,
        COALESCE(SUM(status='in_progress'),0) AS in_progress,
        COALESCE(SUM(status='completed'),0) AS completed,
        COALESCE(SUM(status='declined'),0) AS declined,
        COALESCE(SUM(priority='high' AND status NOT IN ('completed','declined')),0) AS urgent,
        COALESCE(SUM(sla_due_at < CURRENT_TIMESTAMP AND first_response_at IS NULL AND status NOT IN ('completed','declined')),0) AS overdue,
        COALESCE(SUM(sla_due_at >= CURRENT_TIMESTAMP AND sla_due_at <= DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 24 HOUR) AND first_response_at IS NULL AND status NOT IN ('completed','declined')),0) AS due_soon
        FROM service_requests");
    $summary = $query->fetch(PDO::FETCH_ASSOC) ?: [];
    $summary['students'] = $students;
    foreach (['total_requests','submitted','in_review','in_progress','completed','declined','urgent','overdue','due_soon'] as $key) {
        $summary[$key] = (int)($summary[$key] ?? 0);
    }
    return $summary;
}

/** @return list<string> */
function admin_request_categories(PDO $db): array
{
    return array_map('strval', $db->query('SELECT DISTINCT category FROM service_requests ORDER BY category')->fetchAll(PDO::FETCH_COLUMN));
}

/** @return array{total:int,page:int,per_page:int,rows:array} */
function admin_find_requests(PDO $db, array $filters, int $page = 1, int $perPage = 30): array
{
    $page = max(1, min(10000, $page));
    $perPage = max(1, min(100, $perPage));
    $conditions = [];
    $parameters = [];
    $status = $filters['status'] ?? '';
    $category = $filters['category'] ?? '';
    $priority = $filters['priority'] ?? '';
    $search = trim((string)($filters['search'] ?? ''));
    if (is_string($status) && in_array($status, ADMIN_REQUEST_STATUSES, true)) {
        $conditions[] = 'r.status=?';
        $parameters[] = $status;
    }
    if (is_string($category) && $category !== '' && mb_strlen($category) <= 40) {
        $conditions[] = 'r.category=?';
        $parameters[] = $category;
    }
    if (is_string($priority) && in_array($priority, ['low','normal','high'], true)) {
        $conditions[] = 'r.priority=?';
        $parameters[] = $priority;
    }
    if ($search !== '') {
        $search = mb_substr($search, 0, 100);
        $conditions[] = '(r.tracking_id LIKE ? OR r.title LIKE ? OR r.description LIKE ? OR u.username LIKE ? OR u.full_name LIKE ?)';
        $like = '%'.$search.'%';
        array_push($parameters, $like, $like, $like, $like, $like);
    }
    $where = $conditions ? ' WHERE '.implode(' AND ', $conditions) : '';
    $count = $db->prepare('SELECT COUNT(*) FROM service_requests r JOIN users u ON u.id=r.user_id'.$where);
    $count->execute($parameters);
    $total = (int)$count->fetchColumn();
    $offset = ($page - 1) * $perPage;
    $query = $db->prepare('SELECT r.id,r.tracking_id,r.user_id,r.category,r.title,r.description,r.priority,r.recommended_priority,r.status,r.expected_days,r.sla_due_at,r.first_response_at,r.created_at,r.updated_at,u.full_name,u.username
        FROM service_requests r JOIN users u ON u.id=r.user_id'.$where.'
        ORDER BY (r.sla_due_at < CURRENT_TIMESTAMP AND r.first_response_at IS NULL AND r.status NOT IN (\'completed\',\'declined\')) DESC,
                 (r.priority=\'high\' AND r.status NOT IN (\'completed\',\'declined\')) DESC, r.created_at DESC
        LIMIT ? OFFSET ?');
    foreach ($parameters as $index => $value) $query->bindValue($index + 1, $value, PDO::PARAM_STR);
    $query->bindValue(count($parameters) + 1, $perPage, PDO::PARAM_INT);
    $query->bindValue(count($parameters) + 2, $offset, PDO::PARAM_INT);
    $query->execute();
    return ['total'=>$total,'page'=>$page,'per_page'=>$perPage,'rows'=>$query->fetchAll()];
}

/** @return list<array<string,mixed>> */
function admin_recent_requests(PDO $db, int $limit = 8): array
{
    $query = $db->prepare('SELECT r.id,r.tracking_id,r.title,r.category,r.priority,r.status,r.created_at,u.full_name,u.username
        FROM service_requests r JOIN users u ON u.id=r.user_id ORDER BY r.created_at DESC,r.id DESC LIMIT ?');
    $query->bindValue(1, max(1, min(30, $limit)), PDO::PARAM_INT);
    $query->execute();
    return $query->fetchAll();
}

/** @return list<array<string,mixed>> */
function admin_urgent_requests(PDO $db, int $limit = 8): array
{
    $query = $db->prepare("SELECT r.id,r.tracking_id,r.title,r.priority,r.status,r.sla_due_at,r.first_response_at,u.full_name,u.username
        FROM service_requests r JOIN users u ON u.id=r.user_id
        WHERE r.priority='high' AND r.status NOT IN ('completed','declined')
        ORDER BY (r.sla_due_at < CURRENT_TIMESTAMP AND r.first_response_at IS NULL) DESC,r.created_at ASC LIMIT ?");
    $query->bindValue(1, max(1, min(30, $limit)), PDO::PARAM_INT);
    $query->execute();
    return $query->fetchAll();
}

/** @return list<array<string,mixed>> */
function admin_sla_watch_requests(PDO $db, int $limit = 8): array
{
    $query = $db->prepare("SELECT r.id,r.tracking_id,r.title,r.priority,r.status,r.sla_due_at,u.full_name,u.username
        FROM service_requests r JOIN users u ON u.id=r.user_id
        WHERE r.sla_due_at <= DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 24 HOUR)
          AND r.first_response_at IS NULL AND r.status NOT IN ('completed','declined')
        ORDER BY r.sla_due_at ASC,r.created_at ASC LIMIT ?");
    $query->bindValue(1, max(1, min(30, $limit)), PDO::PARAM_INT);
    $query->execute();
    return $query->fetchAll();
}

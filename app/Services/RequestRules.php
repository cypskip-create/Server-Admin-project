<?php
declare(strict_types=1);

function recommend_priority(string $category, string $title, string $description): string
{
    $text = mb_strtolower($title.' '.$description, 'UTF-8');
    if (preg_match('/\b(urgent|emergency|security breach|data breach|system down|cannot access|locked out|safety risk)\b/u', $text)) {
        return 'high';
    }
    if ($category === 'General Administration' && preg_match('/\b(information|question|where|when|guidance)\b/u', $text)) {
        return 'low';
    }
    return 'normal';
}

function response_sla_days(string $priority): int
{
    return match ($priority) {
        'high' => 2,
        'low' => 8,
        default => 5,
    };
}

/** @return array{total:int,open:int,completed:int,submitted:int,in_review:int,in_progress:int,declined:int} */
function student_request_summary(PDO $db, int $userId): array
{
    $query = $db->prepare("SELECT COUNT(*) AS total,
        COALESCE(SUM(status IN ('submitted','in_review','in_progress')),0) AS open_count,
        COALESCE(SUM(status='completed'),0) AS completed,
        COALESCE(SUM(status='submitted'),0) AS submitted,
        COALESCE(SUM(status='in_review'),0) AS in_review,
        COALESCE(SUM(status='in_progress'),0) AS in_progress,
        COALESCE(SUM(status='declined'),0) AS declined
        FROM service_requests WHERE user_id=?");
    $query->execute([$userId]);
    $summary = $query->fetch(PDO::FETCH_ASSOC) ?: [];
    $result = [
        'total'=>(int)($summary['total'] ?? 0),
        'open'=>(int)($summary['open_count'] ?? 0),
        'completed'=>(int)($summary['completed'] ?? 0),
        'submitted'=>(int)($summary['submitted'] ?? 0),
        'in_review'=>(int)($summary['in_review'] ?? 0),
        'in_progress'=>(int)($summary['in_progress'] ?? 0),
        'declined'=>(int)($summary['declined'] ?? 0),
    ];
    return $result;
}

function create_service_request(PDO $db, int $userId, string $category, string $title, string $description, string $priority): string
{
    $recommendation = recommend_priority($category, $title, $description);
    $days = response_sla_days($priority);
    $dueAt = (new DateTimeImmutable('now'))->modify('+'.$days.' days')->format('Y-m-d H:i:s');
    $tracking = 'CF-'.strtoupper(bin2hex(random_bytes(5)));
    $db->beginTransaction();
    try {
        $insert = $db->prepare('INSERT INTO service_requests(tracking_id,user_id,category,title,description,priority,recommended_priority,expected_days,sla_due_at) VALUES(?,?,?,?,?,?,?,?,?)');
        $insert->execute([$tracking,$userId,$category,$title,$description,$priority,$recommendation,$days,$dueAt]);
        $id = (int)$db->lastInsertId();
        $history = $db->prepare("INSERT INTO request_status_history(request_id,changed_by,new_status,note) VALUES(?,?,'submitted','Request received')");
        $history->execute([$id,$userId]);
        $db->commit();
        return $tracking;
    } catch (Throwable $error) {
        if ($db->inTransaction()) $db->rollBack();
        throw $error;
    }
}

/** @return array{status_changed:bool,response_saved:bool,old_status:string,new_status:string}|null */
function update_request_status(PDO $db, int $requestId, int $actorId, string $newStatus, string $note = ''): ?array
{
    $allowed = ['submitted','in_review','in_progress','completed','declined'];
    if (!in_array($newStatus, $allowed, true)) throw new InvalidArgumentException('Unsupported request status.');
    $note = trim($note);
    if (mb_strlen($note, 'UTF-8') > 225) throw new InvalidArgumentException('Administrator response must be 225 characters or fewer.');
    if ($newStatus === 'declined' && $note === '') throw new InvalidArgumentException('A reason is required when declining a request.');
    $db->beginTransaction();
    try {
        $actorQuery = $db->prepare('SELECT role FROM users WHERE id=?');
        $actorQuery->execute([$actorId]);
        if ($actorQuery->fetchColumn() !== 'admin') throw new RuntimeException('Administrator authorization required for request updates.');
        $query=$db->prepare('SELECT user_id,status FROM service_requests WHERE id=? FOR UPDATE');
        $query->execute([$requestId]);$request=$query->fetch();
        if (!$request) {$db->rollBack();return null;}
        $oldStatus=$request['status'];
        $statusChanged = $oldStatus !== $newStatus;
        $responseSaved = $note !== '';
        if (!$statusChanged && !$responseSaved) {
            throw new InvalidArgumentException('Choose a different status or add a response before saving.');
        }
        if ($statusChanged) {
            $db->prepare("UPDATE service_requests SET status=?,first_response_at=CASE WHEN first_response_at IS NULL AND (?<>'submitted' OR ?<>'') THEN CURRENT_TIMESTAMP ELSE first_response_at END WHERE id=?")
                ->execute([$newStatus,$newStatus,$note,$requestId]);
        } elseif ($responseSaved) {
            $db->prepare('UPDATE service_requests SET first_response_at=COALESCE(first_response_at,CURRENT_TIMESTAMP) WHERE id=?')->execute([$requestId]);
        }
        $historyNote = $note !== '' ? $note : 'Status updated by administrator';
        $db->prepare('INSERT INTO request_status_history(request_id,changed_by,old_status,new_status,note) VALUES(?,?,?,?,?)')->execute([$requestId,$actorId,$oldStatus,$newStatus,$historyNote]);
        $statusLabel = str_replace('_',' ',$newStatus);
        $message = $statusChanged
            ? 'Status updated to '.$statusLabel.'.'
            : 'Administrator response received. Status remains '.$statusLabel.'.';
        if ($note !== '') $message .= ' '.$note;
        $notificationType = $statusChanged ? 'status_changed' : 'admin_response';
        $db->prepare('INSERT INTO notifications(user_id,request_id,notification_type,message) VALUES(?,?,?,?)')->execute([$request['user_id'],$requestId,$notificationType,$message]);
        $db->commit();
        error_log('CampusFlow admin request update: actor='.$actorId.' request='.$requestId.' from='.$oldStatus.' to='.$newStatus.' response='.($responseSaved?'yes':'no').'.');
        return ['status_changed'=>$statusChanged,'response_saved'=>$responseSaved,'old_status'=>$oldStatus,'new_status'=>$newStatus];
    } catch (Throwable $error) {
        if ($db->inTransaction()) $db->rollBack();
        throw $error;
    }
}

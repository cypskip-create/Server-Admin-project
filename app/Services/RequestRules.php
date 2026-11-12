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

function update_request_status(PDO $db, int $requestId, int $actorId, string $newStatus): bool
{
    $allowed = ['submitted','in_review','in_progress','completed','declined'];
    if (!in_array($newStatus, $allowed, true)) throw new InvalidArgumentException('Unsupported request status.');
    $db->beginTransaction();
    try {
        $query=$db->prepare('SELECT user_id,status FROM service_requests WHERE id=? FOR UPDATE');
        $query->execute([$requestId]);$request=$query->fetch();
        if (!$request) {$db->rollBack();return false;}
        $oldStatus=$request['status'];
        if ($oldStatus!==$newStatus) {
            $db->prepare("UPDATE service_requests SET status=?,first_response_at=CASE WHEN first_response_at IS NULL AND ?<>'submitted' THEN CURRENT_TIMESTAMP ELSE first_response_at END WHERE id=?")->execute([$newStatus,$newStatus,$requestId]);
            $db->prepare('INSERT INTO request_status_history(request_id,changed_by,old_status,new_status,note) VALUES(?,?,?,?,?)')->execute([$requestId,$actorId,$oldStatus,$newStatus,'Status updated by administrator']);
            $message='Your request status changed to '.str_replace('_',' ',$newStatus).'.';
            $db->prepare('INSERT INTO notifications(user_id,request_id,notification_type,message) VALUES(?,?,?,?)')->execute([$request['user_id'],$requestId,'status_changed',$message]);
        }
        $db->commit();return true;
    } catch (Throwable $error) {
        if ($db->inTransaction()) $db->rollBack();
        throw $error;
    }
}

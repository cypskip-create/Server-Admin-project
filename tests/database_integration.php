<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Services/AuthService.php';
require dirname(__DIR__).'/app/Services/RequestRules.php';
$host=getenv('TEST_DB_HOST')?:'';$name=getenv('TEST_DB_NAME')?:'';$user=getenv('TEST_DB_USER')?:'';$pass=getenv('TEST_DB_PASS')?:'';
if($host===''||$name===''||$user===''){fwrite(STDOUT,"SKIP: set TEST_DB_HOST, TEST_DB_NAME and TEST_DB_USER to run MariaDB integration tests.\n");exit(0);}
$pdo=new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4",$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$assert=static function(bool $condition,string $label):void{if(!$condition)throw new RuntimeException('FAIL: '.$label);echo "PASS: $label\n";};
$suffix=bin2hex(random_bytes(5));$created=[];$bucket='';
try{
 $input=['full_name'=>'Campus Test Student','username'=>'studentCNSA'.$suffix,'email'=>'student'.$suffix.'@example.test','student_number'=>'CT'.$suffix,'password'=>'StrongCampusPass123','confirm'=>'StrongCampusPass123'];$bucket=hash('sha256','127.0.0.99'."\0".strtolower($input['username']));
 $assert(validate_registration($input)['errors']===[],'valid registration passes server validation');
 $bad=$input;$bad['email']='bad-address';$bad['password']='short';$assert(count(validate_registration($bad)['errors'])>=2,'invalid email and weak password fail validation');
 $studentId=register_student($pdo,$input);$created[]=$studentId;
 try{register_student($pdo,$input);throw new RuntimeException('FAIL: duplicate registration was accepted');}catch(PDOException $duplicate){$assert($duplicate->getCode()==='23000','database uniqueness constraint rejects duplicate registration');}
 $stored=$pdo->prepare('SELECT password_hash FROM users WHERE id=?');$stored->execute([$studentId]);$hash=$stored->fetchColumn();$assert(is_string($hash)&&$hash!==$input['password']&&password_verify($input['password'],$hash),'registration stores a verifiable hash, never plaintext');
 $auth=authenticate_student($pdo,$input['username'],$input['password'],'127.0.0.1');$assert($auth['status']==='authenticated'&&!isset($auth['user']['password_hash']),'valid login returns a safe session user');
 $assert(authenticate_student($pdo,$input['username'],'not-the-password','127.0.0.2')['status']==='invalid','incorrect password is rejected');
 for($i=0;$i<8;$i++)$lockout=authenticate_student($pdo,$input['username'],'wrong','127.0.0.99');
 $assert($lockout['status']==='throttled','repeated failed login attempts are throttled');
 $assert(authenticate_student($pdo,$input['username'],$input['password'],'127.0.0.99')['status']==='throttled','throttle blocks valid credentials during cooldown');
 $owner2=$input;$owner2['username']='studentCNSB'.$suffix;$owner2['email']='other'.$suffix.'@example.test';$owner2['student_number']='OT'.$suffix;$otherId=register_student($pdo,$owner2);$created[]=$otherId;
 $auth2=authenticate_student($pdo,$owner2['username'],$owner2['password'],'127.0.0.3');$assert($auth2['status']==='authenticated'&&!isset($auth2['user']['password_hash']),'second student account also authenticates with a safe session user');
 $tracking=create_service_request($pdo,$studentId,'IT Support','Emergency account access','Urgent lockout: cannot access the campus system after security breach.','normal');
 $requestQuery=$pdo->prepare('SELECT * FROM service_requests WHERE tracking_id=?');$requestQuery->execute([$tracking]);$request=$requestQuery->fetch();
 $assert($request&&$request['recommended_priority']==='high','server-side rules recommend high priority for urgent security issues');
 $assert((int)$request['expected_days']===5&&strtotime($request['sla_due_at'])>time(),'chosen priority creates a persisted response SLA due time');
 $history=$pdo->prepare('SELECT COUNT(*) FROM request_status_history WHERE request_id=?');$history->execute([$request['id']]);$assert((int)$history->fetchColumn()===1,'request creation writes initial status history');
 $ownership=$pdo->prepare('SELECT COUNT(*) FROM service_requests WHERE id=? AND user_id=?');$ownership->execute([$request['id'],$otherId]);$assert((int)$ownership->fetchColumn()===0,'request ownership query hides another student data');
 $assert(update_request_status($pdo,(int)$request['id'],$otherId,'in_review'),'status service updates the request');
 $notice=$pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND request_id=?');$notice->execute([$studentId,$request['id']]);$assert((int)$notice->fetchColumn()===1,'status update notification persists for request owner');
 $fresh=$pdo->prepare('SELECT first_response_at FROM service_requests WHERE id=?');$fresh->execute([$request['id']]);$assert($fresh->fetchColumn()!==null,'first response timestamp records SLA response');
} finally {
 if($pdo->inTransaction())$pdo->rollBack();
 foreach($created as $id)$pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
 $pdo->prepare('DELETE FROM login_attempts WHERE bucket=?')->execute([$bucket]);
}

<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Services/AuthService.php';
require dirname(__DIR__).'/app/Services/RequestRules.php';
require dirname(__DIR__).'/app/Services/AdminService.php';
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
 $roleQuery=$pdo->prepare('SELECT role,password_hash FROM users WHERE id=?');$roleQuery->execute([$otherId]);$beforePromotion=$roleQuery->fetch();
 $assert(promote_existing_user_to_admin($pdo,$owner2['username']),'server-side promotion helper promotes an existing account');
 $roleQuery->execute([$otherId]);$afterPromotion=$roleQuery->fetch();$assert($afterPromotion['role']==='admin'&&$afterPromotion['password_hash']===$beforePromotion['password_hash'],'promotion changes only the selected account role and preserves its password hash');
 $adminAuth=authenticate_admin($pdo,$owner2['username'],$owner2['password'],'127.0.0.4');$assert($adminAuth['status']==='authenticated'&&$adminAuth['user']['role']==='admin'&&!isset($adminAuth['user']['password_hash']),'dedicated admin authentication accepts an administrator and omits password hash');
 $studentAdminAuth=authenticate_admin($pdo,$input['username'],$input['password'],'127.0.0.5');$assert($studentAdminAuth['status']==='invalid','dedicated admin authentication rejects an ordinary student');
 $adminAtStudentLogin=authenticate_student($pdo,$owner2['username'],$owner2['password'],'127.0.0.6');$assert($adminAtStudentLogin['status']==='invalid','administrator accounts cannot authenticate through the student-only login handler');
 $assert(!promote_existing_user_to_admin($pdo,'missing-'.$suffix),'promotion command refuses a nonexistent account');
 $tracking=create_service_request($pdo,$studentId,'IT Support','Emergency account access','Urgent lockout: cannot access the campus system after security breach.','normal');
 $requestQuery=$pdo->prepare('SELECT * FROM service_requests WHERE tracking_id=?');$requestQuery->execute([$tracking]);$request=$requestQuery->fetch();
 $assert($request&&$request['recommended_priority']==='high','server-side rules recommend high priority for urgent security issues');
 $assert((int)$request['expected_days']===5&&strtotime($request['sla_due_at'])>time(),'chosen priority creates a persisted response SLA due time');
 $history=$pdo->prepare('SELECT COUNT(*) FROM request_status_history WHERE request_id=?');$history->execute([$request['id']]);$assert((int)$history->fetchColumn()===1,'request creation writes initial status history');
 $studentSummary=student_request_summary($pdo,$studentId);$assert($studentSummary===['total'=>1,'open'=>1,'completed'=>0,'submitted'=>1,'in_review'=>0,'in_progress'=>0,'declined'=>0],'student dashboard starts with one submitted, open request');
 $ownership=$pdo->prepare('SELECT COUNT(*) FROM service_requests WHERE id=? AND user_id=?');$ownership->execute([$request['id'],$otherId]);$assert((int)$ownership->fetchColumn()===0,'request ownership query hides another student data');
 try{update_request_status($pdo,(int)$request['id'],$studentId,'in_review');throw new RuntimeException('FAIL: student changed a request status');}catch(RuntimeException $denied){$assert(str_contains($denied->getMessage(),'Administrator authorization required'),'request service rejects a student administrator action');}
 $freshStatus=$pdo->prepare('SELECT status FROM service_requests WHERE id=?');$freshStatus->execute([$request['id']]);$assert($freshStatus->fetchColumn()==='submitted','unauthorized status attempt leaves the database unchanged');
 try{update_request_status($pdo,(int)$request['id'],$otherId,'invalid-status');throw new RuntimeException('FAIL: unsupported request status was accepted');}catch(InvalidArgumentException $invalid){$assert(str_contains($invalid->getMessage(),'Unsupported request status'),'request service validates status values server-side');}
 try{update_request_status($pdo,(int)$request['id'],$otherId,'in_review',str_repeat('x',226));throw new RuntimeException('FAIL: oversized administrator response was accepted');}catch(InvalidArgumentException $invalid){$assert(str_contains($invalid->getMessage(),'225 characters'),'request service enforces response length server-side');}
 try{update_request_status($pdo,(int)$request['id'],$otherId,'submitted');throw new RuntimeException('FAIL: no-op admin update was accepted');}catch(InvalidArgumentException $invalid){$assert(str_contains($invalid->getMessage(),'different status or add a response'),'same-status save requires an explicit response');}
 try{update_request_status($pdo,(int)$request['id'],$otherId,'declined');throw new RuntimeException('FAIL: decline without a reason was accepted');}catch(InvalidArgumentException $invalid){$assert(str_contains($invalid->getMessage(),'reason is required'),'decline requires a reason');}
 $update=update_request_status($pdo,(int)$request['id'],$otherId,'in_review','We are reviewing your request.');
 $assert($update!==null&&$update['status_changed']&&$update['response_saved']&&$update['old_status']==='submitted'&&$update['new_status']==='in_review','admin can change status and respond in one transaction');
 $studentSummary=student_request_summary($pdo,$studentId);$assert($studentSummary===['total'=>1,'open'=>1,'completed'=>0,'submitted'=>0,'in_review'=>1,'in_progress'=>0,'declined'=>0],'student dashboard moves the request count from Submitted to In Review');
 $freshStatus->execute([$request['id']]);$assert($freshStatus->fetchColumn()==='in_review','In Review status is persisted in the request row');
 $notice=$pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND request_id=?');$notice->execute([$studentId,$request['id']]);$assert((int)$notice->fetchColumn()===1,'status update notification persists for the correct student');
 $noticeMessage=$pdo->prepare('SELECT notification_type,message FROM notifications WHERE user_id=? AND request_id=? ORDER BY id DESC LIMIT 1');$noticeMessage->execute([$studentId,$request['id']]);$noticeRow=$noticeMessage->fetch();$assert($noticeRow['notification_type']==='status_changed'&&str_contains($noticeRow['message'],'We are reviewing your request.'),'status notification identifies the update and includes the response');
 $timeline=$pdo->prepare('SELECT changed_by,old_status,new_status,note FROM request_status_history WHERE request_id=? ORDER BY id DESC LIMIT 1');$timeline->execute([$request['id']]);$timelineRow=$timeline->fetch();$assert((int)$timelineRow['changed_by']===$otherId&&$timelineRow['old_status']==='submitted'&&$timelineRow['new_status']==='in_review'&&$timelineRow['note']==='We are reviewing your request.','admin action and response are saved to the request timeline');
 $fresh=$pdo->prepare('SELECT first_response_at FROM service_requests WHERE id=?');$fresh->execute([$request['id']]);$assert($fresh->fetchColumn()!==null,'first response timestamp records SLA response');
 $adminMatches=admin_find_requests($pdo,['status'=>'in_review','search'=>$tracking],1,10);$assert($adminMatches['total']===1&&$adminMatches['rows'][0]['tracking_id']===$tracking,'admin search and status filter return the matching request');
 $recent=admin_recent_requests($pdo,10);$assert((bool)array_filter($recent,static fn(array $item):bool=>$item['tracking_id']===$tracking),'admin dashboard recent submissions query includes the new request');
 $progressUpdate=update_request_status($pdo,(int)$request['id'],$otherId,'in_progress','The technical team is resolving the login issue.');$assert($progressUpdate!==null&&$progressUpdate['status_changed'],'admin moves the same request to In Progress');
 $studentSummary=student_request_summary($pdo,$studentId);$assert($studentSummary===['total'=>1,'open'=>1,'completed'=>0,'submitted'=>0,'in_review'=>0,'in_progress'=>1,'declined'=>0],'student dashboard moves the request count from In Review to In Progress');
 $completedUpdate=update_request_status($pdo,(int)$request['id'],$otherId,'completed');$assert($completedUpdate!==null&&$completedUpdate['status_changed'],'admin completes the same request');
 $studentSummary=student_request_summary($pdo,$studentId);$assert($studentSummary===['total'=>1,'open'=>0,'completed'=>1,'submitted'=>0,'in_review'=>0,'in_progress'=>0,'declined'=>0],'completed request leaves the open count and appears only in Completed');
 $freshStatus->execute([$request['id']]);$assert($freshStatus->fetchColumn()==='completed','Completed status is persisted in the request row');
 $noticeMessage->execute([$studentId,$request['id']]);$completedNotice=$noticeMessage->fetch();$assert($completedNotice['notification_type']==='status_changed'&&str_contains($completedNotice['message'],'Status updated to completed.'),'completion sends a status notification to the student');
 $timeline->execute([$request['id']]);$completedTimeline=$timeline->fetch();$assert($completedTimeline['old_status']==='in_progress'&&$completedTimeline['new_status']==='completed','completion status transition is recorded in history');

 $declineTracking=create_service_request($pdo,$studentId,'Library Services','Verification decline test','Disposable integration test request for the decline workflow.','normal');
 $requestQuery->execute([$declineTracking]);$declineRequest=$requestQuery->fetch();
 $declineUpdate=update_request_status($pdo,(int)$declineRequest['id'],$otherId,'declined','This test request was declined with a reason.');$assert($declineUpdate!==null&&$declineUpdate['status_changed'],'admin can decline a request with an explanation');
 $studentSummary=student_request_summary($pdo,$studentId);$assert($studentSummary===['total'=>2,'open'=>0,'completed'=>1,'submitted'=>0,'in_review'=>0,'in_progress'=>0,'declined'=>1],'declined request appears only in Declined and does not increase open count');
 $noticeMessage->execute([$studentId,$declineRequest['id']]);$declineNotice=$noticeMessage->fetch();$assert($declineNotice['notification_type']==='status_changed'&&str_contains($declineNotice['message'],'with a reason'),'student notification contains the decline explanation');
 $timeline->execute([$declineRequest['id']]);$declineTimeline=$timeline->fetch();$assert($declineTimeline['old_status']==='submitted'&&$declineTimeline['new_status']==='declined'&&$declineTimeline['note']==='This test request was declined with a reason.','decline reason is visible in request history');

 $responseTracking=create_service_request($pdo,$studentId,'General Administration','Verification response-only test','Disposable integration test request for response-only behavior.','normal');
 $requestQuery->execute([$responseTracking]);$responseRequest=$requestQuery->fetch();
 $responseUpdate=update_request_status($pdo,(int)$responseRequest['id'],$otherId,'submitted','We received your request and will review it shortly.');
 $assert($responseUpdate!==null&&!$responseUpdate['status_changed']&&$responseUpdate['response_saved']&&$responseUpdate['old_status']==='submitted'&&$responseUpdate['new_status']==='submitted','response-only action clearly leaves request status unchanged');
 $freshStatus->execute([$responseRequest['id']]);$assert($freshStatus->fetchColumn()==='submitted','response-only action does not change service_requests.status');
 $fresh->execute([$responseRequest['id']]);$assert($fresh->fetchColumn()!==null,'response-only action records the initial response SLA timestamp');
 $noticeMessage->execute([$studentId,$responseRequest['id']]);$responseNotice=$noticeMessage->fetch();$assert($responseNotice['notification_type']==='admin_response'&&str_contains($responseNotice['message'],'Status remains submitted.')&&str_contains($responseNotice['message'],'We received your request'),'response-only notification states that the status stayed unchanged');
 $timeline->execute([$responseRequest['id']]);$responseTimeline=$timeline->fetch();$assert((int)$responseTimeline['changed_by']===$otherId&&$responseTimeline['old_status']==='submitted'&&$responseTimeline['new_status']==='submitted'&&$responseTimeline['note']==='We received your request and will review it shortly.','response-only action is preserved in the student timeline');
 $studentSummary=student_request_summary($pdo,$studentId);$assert($studentSummary===['total'=>3,'open'=>1,'completed'=>1,'submitted'=>1,'in_review'=>0,'in_progress'=>0,'declined'=>1],'student overview counts each request once after all status transitions');
 $summary=admin_dashboard_summary($pdo);$assert($summary['total_requests']>=3&&$summary['completed']>=1&&$summary['declined']>=1&&$summary['students']>=1,'administrator dashboard counts are read from MariaDB after status transitions');
 $adminMatches=admin_find_requests($pdo,['status'=>'completed','search'=>$tracking],1,10);$assert($adminMatches['total']===1&&$adminMatches['rows'][0]['tracking_id']===$tracking,'admin filters find the request at its latest persisted status');
} finally {
 if($pdo->inTransaction())$pdo->rollBack();
 foreach($created as $id)$pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
 $pdo->prepare('DELETE FROM login_attempts WHERE bucket=?')->execute([$bucket]);
}

<?php
declare(strict_types=1);
require dirname(__DIR__).'/config/security.php';
$assert=static function(bool $condition,string $label):void{if(!$condition)throw new RuntimeException('FAIL: '.$label);echo "PASS: $label\n";};
$_SESSION=[];
$assert(!csrf_token_is_valid(null),'CSRF token missing is rejected');
$assert(!csrf_token_is_valid(''),'CSRF token empty is rejected');
$assert(!csrf_token_is_valid(''),'missing session token cannot validate empty form token');
$_SESSION['csrf']='session-token';
$assert(!csrf_token_is_valid(null),'missing submitted token is rejected');
$assert(!csrf_token_is_valid(''),'empty submitted token is rejected');
$assert(!csrf_token_is_valid('other-token'),'mismatched token is rejected');
$assert(!csrf_token_is_valid(['session-token']),'non-string token is rejected');
$assert(csrf_token_is_valid('session-token'),'matching non-empty token is accepted');
$assert(!is_admin_user(null),'anonymous context is not an administrator');
$assert(!is_admin_user(['role'=>'student']),'student context is not an administrator');
$assert(is_admin_user(['role'=>'admin']),'admin context is authorized');

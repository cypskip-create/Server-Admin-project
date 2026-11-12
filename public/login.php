<?php
require __DIR__.'/_init.php';
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 check_csrf(); $key=trim($_POST['identity']??''); $password=$_POST['password']??'';
 $bucket=hash('sha256',($_SERVER['REMOTE_ADDR']??'unknown')."\0".mb_strtolower($key));
 $db=db(); $db->prepare('DELETE FROM login_attempts WHERE updated_at < CURRENT_TIMESTAMP - INTERVAL 1 DAY')->execute();
 $q=$db->prepare('SELECT failures,blocked_until FROM login_attempts WHERE bucket=?');$q->execute([$bucket]);$attempt=$q->fetch();
 $blocked=$attempt && $attempt['blocked_until'] && strtotime($attempt['blocked_until'])>time();
 if($blocked){$error='Too many attempts. Wait before trying again.';}
 else{
  $q=$db->prepare('SELECT id,full_name,username,email,role,password_hash FROM users WHERE username=? OR email=?');$q->execute([$key,$key]);$u=$q->fetch();
  if($u&&password_verify($password,$u['password_hash'])){
   $db->prepare('DELETE FROM login_attempts WHERE bucket=?')->execute([$bucket]);session_regenerate_id(true);unset($u['password_hash']);$_SESSION['user']=$u;$_SESSION['csrf']=bin2hex(random_bytes(32));header('Location: /dashboard.php');exit;
  }
  $failures=($attempt&&strtotime($attempt['blocked_until']??'1970-01-01')>time())?8:(($attempt&&strtotime($attempt['window_started_at'])>time()-900)?((int)$attempt['failures']+1):1);
  $blockedUntil=$failures>=8?date('Y-m-d H:i:s',time()+900):null;
  $db->prepare('INSERT INTO login_attempts(bucket,failures,window_started_at,blocked_until) VALUES(?,?,CURRENT_TIMESTAMP,?) ON DUPLICATE KEY UPDATE failures=VALUES(failures),window_started_at=CURRENT_TIMESTAMP,blocked_until=VALUES(blocked_until)')->execute([$bucket,$failures,$blockedUntil]);
  $error='The username/email or password is incorrect.';
 }
}
page_start('Sign in');?><section class="form-wrap"><div class="eyebrow">WELCOME BACK</div><h1>Sign in</h1><p>Your campus support desk is one step away.</p><?php if($error)echo '<p class="error">'.e($error).'</p>';?><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><label>Username or email<input name="identity" autocomplete="username" required></label><label>Password<input type="password" name="password" autocomplete="current-password" required></label><button class="button">Sign in →</button></form><p>New here? <a href="/signup.php">Create an account</a></p></section><?php page_end();?>

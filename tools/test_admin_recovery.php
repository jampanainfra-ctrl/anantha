<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
session_id('recovery-test-'.bin2hex(random_bytes(8)));
require_once __DIR__.'/../includes/admin_auth.php';
$database='anantha_recovery_test_'.bin2hex(random_bytes(5));
$settingsPath=sys_get_temp_dir().'/'.$database.'.php';putenv('ANANTHA_MAIL_SETTINGS_PATH='.$settingsPath);
putenv('ANANTHA_APP_ORIGIN=https://admin.example.test');
$checks=0;
function recovery_check(bool $ok,string $label):void{global $checks;if(!$ok)throw new RuntimeException('FAILED: '.$label);$checks++;}
function recovery_rejected(callable $operation,string $label):void{try{$operation();}catch(RuntimeException $e){recovery_check(true,$label);return;}throw new RuntimeException('FAILED: '.$label);}
function recovery_latest_token():string{
    $body=(string)db()->query("SELECT body_text FROM email_outbox WHERE event_type='admin_password_reset' ORDER BY id DESC LIMIT 1")->fetchColumn();
    if(!preg_match('/token=([a-f0-9]{64})/',$body,$matches))throw new RuntimeException('Reset token not in queued email');return $matches[1];
}
try{
    db()->exec('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');db()->exec('USE `'.$database.'`');
    $schema=preg_replace('/^(CREATE DATABASE[^;]+;|USE\s+anantha;)[\r\n]*/mi','',file_get_contents(__DIR__.'/../database.sql'));
    if(preg_match('/\bUSE\s+anantha\b/i',$schema))throw new RuntimeException('Unsafe test schema');db()->exec($schema);
    ensure_admin_recovery_schema();ensure_mail_schema();
    $originalHash=password_hash('Original-test-password',PASSWORD_DEFAULT);
    db()->prepare("INSERT INTO admin_users(name,email,password_hash) VALUES ('Recovery Test','recovery@example.test',?)")->execute([$originalHash]);$id=(int)db()->lastInsertId();
    $csrf=recovery_csrf_token();recovery_check(strlen($csrf)===64&&recovery_check_csrf($csrf),'CSRF token accepted');recovery_check(!recovery_check_csrf('wrong'),'invalid CSRF token rejected');
    request_admin_password_reset('nobody@example.test','127.0.0.1');recovery_check((int)db()->query('SELECT COUNT(*) FROM admin_password_resets')->fetchColumn()===0,'unknown account creates no token');recovery_check((int)db()->query('SELECT COUNT(*) FROM email_outbox')->fetchColumn()===0,'unknown account creates no email');
    $_SERVER['HTTP_HOST']='attacker.example.test';request_admin_password_reset('RECOVERY@EXAMPLE.TEST','127.0.0.1');$first=recovery_latest_token();
    recovery_check(valid_admin_reset_token($first),'valid requested token accepted');
    $row=db()->query('SELECT * FROM admin_password_resets ORDER BY id DESC LIMIT 1')->fetch();recovery_check($row['token_hash']===hash('sha256',$first)&&!in_array($first,$row,true),'only token hash stored in reset table');
    $mail=db()->query('SELECT * FROM email_outbox ORDER BY id DESC LIMIT 1')->fetch();recovery_check($mail['recipient']==='recovery@example.test','reset sent to registered admin rather than general alert address');
    recovery_check(str_contains($mail['body_text'],'https://admin.example.test/anantha/ceoadmin/reset-password/')&&!str_contains($mail['body_text'],'attacker.example.test'),'reset origin does not trust Host header');
    recovery_check(str_contains($mail['body_html'],'>Reset password</a>'),'email has clickable reset button');
    request_admin_password_reset('recovery@example.test','127.0.0.1');$second=recovery_latest_token();recovery_check($second!==$first&&!valid_admin_reset_token($first)&&valid_admin_reset_token($second),'new request invalidates previous link');
    recovery_rejected(fn()=>complete_admin_password_reset($second,'short','short'),'short password rejected');
    recovery_rejected(fn()=>complete_admin_password_reset($second,'A-new-test-password','Different-password'),'password confirmation mismatch rejected');
    recovery_check(valid_admin_reset_token($second),'validation failure does not consume token');
    db()->prepare('UPDATE admin_password_resets SET expires_at=DATE_SUB(NOW(),INTERVAL 1 MINUTE) WHERE token_hash=?')->execute([hash('sha256',$second)]);recovery_check(!valid_admin_reset_token($second),'expired token rejected');
    recovery_rejected(fn()=>complete_admin_password_reset($second,'A-new-test-password','A-new-test-password'),'expired token cannot reset password');
    recovery_check(!valid_admin_reset_token('invalid'),'malformed token rejected');
    request_admin_password_reset('recovery@example.test','127.0.0.1');$third=recovery_latest_token();
    $_SESSION['admin_id']=$id;$_SESSION['admin_session_version']=1;recovery_check((bool)admin_user(),'original admin session accepted');
    complete_admin_password_reset($third,'A-new-test-password','A-new-test-password');
    $stmt=db()->prepare('SELECT * FROM admin_users WHERE id=?');$stmt->execute([$id]);$admin=$stmt->fetch();
    recovery_check(password_verify('A-new-test-password',$admin['password_hash'])&&!password_verify('Original-test-password',$admin['password_hash']),'new password saved as hash and old password invalid');
    recovery_check(!valid_admin_reset_token($third),'used token rejected');recovery_rejected(fn()=>complete_admin_password_reset($third,'Another-test-password','Another-test-password'),'token cannot be replayed');
    recovery_check(empty($_SESSION['admin_id']),'reset does not automatically sign in');
    $_SESSION['admin_id']=$id;$_SESSION['admin_session_version']=1;recovery_check(admin_user()===null,'previous sessions revoked');
    $_SESSION['admin_id']=$id;$_SESSION['admin_session_version']=(int)$admin['session_version'];recovery_check((bool)admin_user(),'new session version accepted');
    recovery_check((int)db()->query("SELECT COUNT(*) FROM email_outbox WHERE event_type='admin_password_changed'")->fetchColumn()===1,'password change notification queued');
    recovery_check((int)db()->query("SELECT COUNT(*) FROM email_outbox WHERE body_text LIKE '%A-new-test-password%'")->fetchColumn()===0,'password never emailed');
    db()->exec('DELETE FROM admin_reset_limits');$before=(int)db()->query('SELECT COUNT(*) FROM admin_password_resets')->fetchColumn();
    for($i=0;$i<8;$i++)request_admin_password_reset('recovery@example.test','192.0.2.10');
    recovery_check((int)db()->query('SELECT COUNT(*) FROM admin_password_resets')->fetchColumn()===$before+5,'account requests capped at five per hour');
    db()->exec('DELETE FROM admin_reset_limits');$before=(int)db()->query('SELECT COUNT(*) FROM admin_password_resets')->fetchColumn();
    for($i=0;$i<20;$i++)request_admin_password_reset('missing'.$i.'@example.test','192.0.2.20');request_admin_password_reset('recovery@example.test','192.0.2.20');
    recovery_check((int)db()->query('SELECT COUNT(*) FROM admin_password_resets')->fetchColumn()===$before,'IP requests capped at twenty per hour');
    recovery_check(!mail_settings()['enabled'],'test delivery remains paused');
    echo 'PASS: '.$checks." admin recovery checks (isolated database; no emails sent).\n";
}finally{
    if(db()->inTransaction())db()->rollBack();db()->exec('USE `anantha`');db()->exec('DROP DATABASE IF EXISTS `'.$database.'`');
    if(is_file($settingsPath))unlink($settingsPath);session_destroy();putenv('ANANTHA_MAIL_SETTINGS_PATH');putenv('ANANTHA_APP_ORIGIN');
}
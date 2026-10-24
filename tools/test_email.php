<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../includes/config.php';
if(($argv[1] ?? '')==='--action'){
    $database=$argv[2] ?? '';
    if(!preg_match('/^anantha_mail_test_[a-f0-9]+$/',$database))exit(2);
    db()->exec('USE `'.$database.'`');
    $_POST=json_decode(base64_decode($argv[3]),true);$_GET=[];$_FILES=[];$_SERVER['REQUEST_METHOD']='POST';
    session_id('mail-test-'.bin2hex(random_bytes(8)));require_once __DIR__.'/../includes/admin_auth.php';
    $_SESSION['admin_id']=(int)db()->query('SELECT id FROM admin_users LIMIT 1')->fetchColumn();
    $_POST['csrf_token']=referral_csrf_token();
    register_shutdown_function(function(){echo json_encode($_SESSION['flash'] ?? []);session_destroy();});
    require __DIR__.'/../admin/actions.php';exit;
}
$database='anantha_mail_test_'.bin2hex(random_bytes(5));
$settingsPath=sys_get_temp_dir().'/'.$database.'.php';
putenv('ANANTHA_MAIL_SETTINGS_PATH='.$settingsPath);
$checks=0;
function mail_check(bool $ok,string $label): void {global $checks;if(!$ok)throw new RuntimeException('FAILED: '.$label);$checks++;}
function mail_count():int{return (int)db()->query('SELECT COUNT(*) FROM email_outbox')->fetchColumn();}
function mail_test_settings(bool $enabled):void{
    save_mail_settings(['transport'=>'smtp','host'=>'127.0.0.1','port'=>2525,'security'=>'none','from_email'=>'sender@example.test','from_name'=>'Anantha Test','admin_email'=>'admin@example.test','customer_emails'=>'1','admin_alerts'=>'1']+($enabled?['enabled'=>'1']:[]));
}
try{
    db()->exec('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');db()->exec('USE `'.$database.'`');
    $schema=preg_replace('/^(CREATE DATABASE[^;]+;|USE\s+anantha;)[\r\n]*/mi','',file_get_contents(__DIR__.'/../database.sql'));
    if(preg_match('/\bUSE\s+anantha\b/i',$schema))throw new RuntimeException('Unsafe test schema');
    db()->exec($schema);ensure_booking_enquiry_schema();ensure_payment_verification_schema();ensure_referral_schema();ensure_mail_schema();
    mail_check(mail_settings()['enabled']===false,'delivery paused by default');
    mail_check(count(mail_configuration_errors(mail_settings()))>0,'incomplete connection detected');
    $s=mail_settings();$s['transport']='smtp';$s['host']='smtp.example.test';$s['security']='none';
    mail_check(count(mail_configuration_errors($s))>0,'unencrypted remote server rejected');
    [$html,$text]=mail_template('Test <heading>','Hello <script>', ['Value'=>'<img src=x onerror=alert(1)>']);
    mail_check(!str_contains($html,'<script>')&&!str_contains($html,'<img src=x'),'email HTML escaped');
    mail_check(str_contains($text,'<script>'),'plain text alternative retained');
    mail_check(!queue_mail('invalid','test','bad-address','customer','Test','Intro',[]),'invalid recipients skipped');
    mail_check(queue_mail('dedup','test','customer@example.test','customer','Test','Intro',[]),'first event queued');
    mail_check(!queue_mail('dedup','test','customer@example.test','customer','Test','Intro',[]),'duplicate recipient event suppressed');
    $called=0;$r=process_mail_queue(10,function()use(&$called){$called++;});
    mail_check($r['paused']&&$called===0,'paused worker does not send');
    mail_test_settings(true);
    mail_check(mail_configuration_errors(mail_settings())===[],'valid local SMTP settings accepted');
    $r=process_mail_queue(10,function($message)use(&$called){$called++;mail_check(str_contains($message['body_html'],'Anantha'),'worker receives branded email');});
    mail_check($r['sent']===1&&$called===1,'queued event sent once');
    process_mail_queue(10,function()use(&$called){$called++;});mail_check($called===1,'sent messages not resent');
    queue_mail('failure','test','customer@example.test','customer','Failure','Intro',[]);
    $r=process_mail_queue(1,function(){throw new RuntimeException('secret-password-should-never-be-logged');});
    $failed=db()->query("SELECT * FROM email_outbox WHERE status='failed'")->fetch();
    mail_check($r['failed']===1&&(int)$failed['attempts']===1,'failure retained with retry count');
    mail_check(!str_contains($failed['last_error'],'secret-password'),'exception details and credentials excluded from logs');
    $r=process_mail_queue(1,function()use(&$called){$called++;});mail_check($r['sent']===0,'retry backoff respected');
    db()->exec("UPDATE email_outbox SET next_attempt_at=NOW() WHERE status='failed'");
    $r=process_mail_queue(1,function(){});mail_check($r['sent']===1,'failed message retried successfully');
    db()->exec('DELETE FROM email_outbox');
    db()->prepare("INSERT INTO event_bookings(booking_reference,client_name,phone,email,event_date,event_type,guests,proposal_amount) VALUES ('MAIL-ONE','Email Customer','9000011111','customer@example.test','2027-01-10','Wedding',100,50000)")->execute();$id=(int)db()->lastInsertId();member_referral_code($id);
    notify_booking_change($id,[],'public');
    mail_check(mail_count()===2,'booking creates customer acknowledgement and admin alert');
    $email=db()->query("SELECT * FROM email_outbox WHERE audience='customer'")->fetch();
    mail_check(str_contains($email['body_text'],'50,000.00')&&str_contains($email['body_text'],'Your referral code'),'booking email includes total and referral code');
    $before=mail_booking_snapshot($id);notify_booking_change($id,$before);mail_check(mail_count()===2,'unchanged booking does not produce another update');
    db()->exec("UPDATE event_bookings SET booking_status='confirmed' WHERE id=$id");notify_booking_change($id,$before);
    mail_check(mail_count()===4,'booking status change notifies both audiences');
    $before=mail_booking_snapshot($id);
    db()->exec("INSERT INTO payments(booking_id,amount,payment_mode,paid_on,verification_status) VALUES ($id,5000,'cash',CURDATE(),'pending')");$paymentId=(int)db()->lastInsertId();
    notify_payment_changes($id,$before);mail_check(mail_count()===6,'payment recorded notifies both audiences');
    $email=db()->query("SELECT * FROM email_outbox WHERE audience='customer' ORDER BY id DESC LIMIT 1")->fetch();mail_check(str_contains($email['body_text'],'not a verified payment receipt'),'pending payment not represented as verified receipt');
    $before=mail_booking_snapshot($id);notify_payment_changes($id,$before);mail_check(mail_count()===6,'unchanged payment not emailed twice');
    db()->exec("UPDATE payments SET verification_status='verified' WHERE id=$paymentId");notify_payment_changes($id,$before);mail_check(mail_count()===8,'verified payment sends receipt and admin update');
    $before=mail_booking_snapshot($id);db()->exec("UPDATE event_bookings SET email=NULL WHERE id=$id");notify_booking_change($id,$before);mail_check(mail_count()===9,'customer without email skipped while admin notified');
    notify_public_review(100,'Review Customer',5);notify_public_review(100,'Review Customer',5);mail_check(mail_count()===10,'review admin alert is deduplicated');
    db()->exec("UPDATE event_bookings SET email='customer@example.test' WHERE id=$id");
    $logId=(int)db()->query('SELECT COALESCE(MAX(id),0) FROM referral_logs')->fetchColumn();member_referral_code($id,true);notify_referral_activity($id,$logId);$afterReferral=mail_count();
    mail_check($afterReferral>10,'referral replacement creates notifications');notify_referral_activity($id,$logId);mail_check(mail_count()===$afterReferral,'referral logs do not create duplicate emails');
    $route=function(array $post)use($database):array{
        $pipes=[];$process=proc_open([PHP_BINARY,__FILE__,'--action',$database,base64_encode(json_encode($post))],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
        $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
        if(proc_close($process)!==0 || $err!=='')throw new RuntimeException($err.' '.$out);
        $result=json_decode($out,true);if(!is_array($result))throw new RuntimeException($out);return $result;
    };
    $beforeCount=mail_count();
    $result=$route(['action'=>'save_package','package_name'=>'Email Test Package','guest_min'=>'1','guest_max'=>'50','price'=>'1000','discount_percent'=>'0','display_order'=>'99','is_active'=>'1']);
    mail_check(($result['type'] ?? '')==='ok' && mail_count()===$beforeCount+1,'completed package action queues admin alert');
    $beforeCount=mail_count();$result=$route(['action'=>'save_booking_details','client_name'=>'','phone'=>'9000011111']);
    mail_check(($result['type'] ?? '')==='error' && mail_count()===$beforeCount,'rejected booking action queues no notification');
    $beforeCount=mail_count();$result=$route(['action'=>'quick_update_booking_status','booking_id'=>$id,'booking_status'=>'event_over','payment_status'=>'advance_paid']);
    mail_check(($result['type'] ?? '')==='ok' && mail_count()>=$beforeCount+2,'committed booking action queues customer and admin updates');
    save_mail_settings(['transport'=>'php_mail','from_email'=>'from@example.test','from_name'=>'Anantha Native Test','admin_email'=>'to@example.test']);
    mail_check(mail_configuration_errors(mail_settings())===[],'native mail requires From and To without SMTP credentials');
    $message=['recipient'=>'customer@example.test','subject'=>'Native transport test','body_html'=>'<p>Test</p>','body_text'=>'Test','event_key'=>str_repeat('a',64)];
    $mailer=create_application_mailer($message);
    mail_check($mailer->Mailer==='mail','native transport uses PHP mail');
    mail_check($mailer->From==='from@example.test'&&$mailer->getToAddresses()[0][0]==='customer@example.test','configured From and customer To used');
    mail_check(mail_settings()['admin_email']==='to@example.test','admin To address stored separately');
    $payload=application_php_mail_payload($message,mail_settings());
    mail_check($payload['to']==='customer@example.test'&&str_contains($payload['headers']['From'],'<from@example.test>'),'native PHP mail uses configured From and recipient To');
    mail_check(str_contains($payload['headers']['Content-Type'],'multipart/alternative')&&str_contains($payload['body'],base64_encode('<p>Test</p>'))&&str_contains($payload['body'],base64_encode('Test')),'native email includes HTML and plain-text alternatives');
    $unicode=$message;$unicode['subject']='Anantha — payment received';$unicode['body_text']='Received ₹500.';
    $payload=application_php_mail_payload($unicode,mail_settings());
    mail_check(mb_decode_mimeheader($payload['subject'])===$unicode['subject']&&str_contains($payload['body'],base64_encode($unicode['body_text'])),'native email preserves Unicode subjects and body text');
    foreach(['recipient'=>"customer@example.test\r\nBcc: other@example.test",'subject'=>"Test\r\nBcc: other@example.test"] as $key=>$value){
        $unsafe=$message;$unsafe[$key]=$value;$rejected=false;
        try{application_php_mail_payload($unsafe,mail_settings());}catch(RuntimeException $e){$rejected=true;}
        mail_check($rejected,'native mail rejects '.$key.' header injection');
    }
    $unsafe=mail_settings();$unsafe['from_name']="Sender\x00name";
    mail_check(count(mail_configuration_errors($unsafe))>0,'native sender name rejects null bytes');
    db()->exec('DELETE FROM email_outbox');
    if(isset($argv[1])){
        save_mail_settings(['transport'=>'smtp','enabled'=>'1','host'=>'127.0.0.1','port'=>(int)$argv[1],'security'=>'none','from_email'=>'sender@example.test','from_name'=>'Anantha Test','admin_email'=>'admin@example.test']);
        queue_mail('smtp-test','test','sink@example.test','admin','Local SMTP delivery','Test only. No external mail server.', ['Result'=>'Local SMTP test']);
        $r=process_mail_queue(1);mail_check($r['sent']===1,'real PHPMailer SMTP exchange with local test server');
        save_mail_settings(['transport'=>'php_mail','enabled'=>'1','from_email'=>'sender@example.test','from_name'=>'Native Test','admin_email'=>'admin@example.test']);
        queue_mail('native-test','test','native-sink@example.test','admin','Native PHP mail delivery','Local test only.', ['Result'=>'Native PHP mail test']);
        $r=process_mail_queue(1);mail_check($r['sent']===1,'real native PHP mail exchange with local test server');
    }
    echo 'PASS: '.$checks." email checks (isolated database; no external email sent).\n";
}finally{
    if(db()->inTransaction())db()->rollBack();db()->exec('USE `anantha`');db()->exec('DROP DATABASE IF EXISTS `'.$database.'`');
    if(is_file($settingsPath))unlink($settingsPath);putenv('ANANTHA_MAIL_SETTINGS_PATH');
}

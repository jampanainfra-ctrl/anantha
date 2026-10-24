<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../includes/config.php';
if(($argv[1] ?? '')==='--action'){
    $testDatabase=$argv[2] ?? '';
    if(!preg_match('/^anantha_returning_test_[a-f0-9]+$/D',$testDatabase))exit(2);
    db()->exec('USE `'.$testDatabase.'`');
    $_POST=json_decode(base64_decode($argv[3]),true);$_GET=[];$_FILES=[];$_SERVER['REQUEST_METHOD']='POST';
    session_id('returning-admin-test-'.bin2hex(random_bytes(8)));require_once __DIR__.'/../includes/admin_auth.php';
    $_SESSION['admin_id']=(int)db()->query('SELECT id FROM admin_users LIMIT 1')->fetchColumn();$_POST['csrf_token']=referral_csrf_token();
    register_shutdown_function(function(){echo json_encode($_SESSION['flash'] ?? []);session_destroy();});
    require __DIR__.'/../admin/actions.php';exit;
}
$database='anantha_returning_test_'.bin2hex(random_bytes(5));
$privateSettings=sys_get_temp_dir().'/'.$database.'.php';
file_put_contents($privateSettings,"<?php return ['enabled'=>false];");
putenv('ANANTHA_MAIL_SETTINGS_PATH='.$privateSettings);
session_id('returning-test-'.bin2hex(random_bytes(10)));
require_once __DIR__.'/../includes/admin_auth.php';
$checks=0;
function customer_check(bool $condition,string $label):void{global $checks;if(!$condition)throw new RuntimeException('FAILED: '.$label);$checks++;}
function customer_rejects(callable $action,string $label):void{$rejected=false;try{$action();}catch(RuntimeException $e){$rejected=true;}customer_check($rejected,$label);}
function customer_row(int $id):array{$s=db()->prepare('SELECT * FROM event_bookings WHERE id=?');$s->execute([$id]);return $s->fetch();}
function customer_submit(array $post):array{
    $_POST=$post;$_GET=[];$_SERVER['REQUEST_METHOD']='POST';
    ob_start();require __DIR__.'/../availability.php';ob_end_clean();
    return ['error'=>$formError,'success'=>$success,'id'=>$bookingId ?? 0];
}
try{
    db()->exec('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');db()->exec('USE `'.$database.'`');
    $schema=preg_replace('/^(CREATE DATABASE[^;]+;|USE\s+anantha;)[\r\n]*/mi','',file_get_contents(__DIR__.'/../database.sql'));
    if(preg_match('/\bUSE\s+anantha\b/i',$schema))throw new RuntimeException('Unsafe test schema');
    db()->exec($schema);ensure_booking_enquiry_schema();ensure_payment_verification_schema();ensure_terms_schema();ensure_package_schema();ensure_referral_schema();
    $reference=generate_unique_booking_reference('Returning Test');
    db()->prepare("INSERT INTO event_bookings(booking_reference,client_name,phone,whatsapp_number,email,pin_code,area_from,city_district,client_state,event_date,event_type,guests,proposal_amount,aadhaar_number,note,booking_status) VALUES (?, 'Returning Test','+91 9000012345','9000012345','returning@example.test','530045','Saved area','Saved city','Saved state',DATE_SUB(CURDATE(),INTERVAL 1 DAY),'Wedding',80,50000,'private-identity','Original private note','event_over')")->execute([$reference]);
    $originalId=(int)db()->lastInsertId();member_referral_code($originalId);
    $original=customer_row($originalId);
    $unknown=returning_customer_lookup('9000012345','');
    customer_check(!isset($unknown['customer'])&&$unknown['verification_required'],'phone alone reveals no customer data');
    customer_rejects(fn()=>returning_customer_lookup('9000012345','INCORRECT'),'wrong reference rejected');
    customer_rejects(fn()=>returning_customer_lookup('9000012346',$reference),'wrong phone rejected');
    customer_rejects(fn()=>returning_customer_lookup(' 9000012345',$reference),'invalid phone formatting rejected');
    $found=returning_customer_lookup('9000012345',$reference);
    customer_check($found['customer']['client_name']==='Returning Test'&&$found['customer']['city_district']==='Saved city','matching phone and reference load contact details');
    customer_check(!isset($found['customer']['aadhaar_number'],$found['customer']['proposal_amount'],$found['customer']['note'],$found['customer']['event_date']),'identity, pricing, notes and event fields excluded');
    customer_check(array_keys($found['customer'])===['client_name','phone','whatsapp_number','email','pin_code','area_from','city_district','client_state'],'response contains only approved contact fields');
    customer_check(returning_customer_context('9000012345')!==null&&returning_customer_context('9000012346')===null,'verification bound to the matching phone');
    customer_check(duplicate_booking_contact_message('9000012345','returning@example.test')!=='','unverified duplicates still blocked');
    customer_check(duplicate_booking_contact_message('9000012345','returning@example.test',null,'9000012345',false,'9000012345')==='','verified existing contacts accepted for another event');
    db()->prepare("INSERT INTO event_bookings(booking_reference,client_name,phone,email,event_date) VALUES (?,'Other customer','9000098765','other@example.test',DATE_ADD(CURDATE(),INTERVAL 2 DAY))")->execute([generate_unique_booking_reference('Other customer')]);
    customer_check(duplicate_booking_contact_message('9000012345','other@example.test',null,'',false,'9000012345')!=='','another customer email remains blocked');
    $package=active_packages()[0];[$min,$max]=package_guest_bounds($package['guest_range'],300);
    $post=['enquire'=>'1','customer_token'=>referral_csrf_token(),'name'=>'Returning Test','phone'=>'9000012345','whatsapp_number'=>'9000012345','email'=>'returning@example.test','pin_code'=>'530045','area_from'=>'Updated area','city_district'=>'Saved city','client_state'=>'Saved state','event_date'=>date('Y-m-d',strtotime('+5 days')),'event_type'=>'Birthday','guests'=>max(1,$min),'package_id'=>$package['id'],'note'=>'Second event note','referred_by_reference'=>'','accept_terms'=>array_column(booking_required_terms(3),'id')];
    $bad=$post;$bad['customer_token']='invalid';$r=customer_submit($bad);customer_check($r['error']!==''&&$r['id']===0,'repeat enquiry rejects invalid CSRF');
    $bad=$post;$bad['email']='other@example.test';$r=customer_submit($bad);customer_check($r['error']!==''&&$r['id']===0,'repeat enquiry rejects conflicting contact');
    $bad=$post;$bad['referred_by_reference']=member_referral_code($originalId)['code'];$r=customer_submit($bad);customer_check(str_contains($r['error'],'cannot claim a new-member referral')&&$r['id']===0,'repeat customer cannot claim new-member referral');
    $r=customer_submit($post);customer_check($r['error']===''&&$r['id']>$originalId,'second enquiry saves: '.$r['error']);
    $newId=(int)$r['id'];$second=customer_row($newId);
    customer_check((int)$second['returning_customer_booking_id']===$originalId,'new enquiry linked to previous booking');
    customer_check($second['booking_reference']!==$reference,'second enquiry has a unique reference');
    customer_check(customer_row($originalId)===$original,'original booking completely unchanged');
    customer_check($second['event_type']==='Birthday'&&$second['note']==='Second event note'&&$second['proposal_amount']===null,'new event details saved without copying original quote or notes');
    customer_check(!isset($_SESSION['returning_customer']),'verification consumed after successful save');
    $adminPost=$post+['action'=>'save_booking_details','booking_id'=>$newId,'client_name'=>'Returning Test Updated','booking_status'=>'pending','date_of_birth'=>''];
    $pipes=[];$process=proc_open([PHP_BINARY,__FILE__,'--action',$database,base64_encode(json_encode($adminPost))],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
    $output=stream_get_contents($pipes[1]);$stderr=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);
    $flash=json_decode($output,true);
    customer_check($exit===0&&$stderr===''&&($flash['type'] ?? '')==='ok','admin can edit the repeat booking: '.$output.$stderr);
    customer_check(customer_row($newId)['client_name']==='Returning Test Updated'&&customer_row($originalId)===$original,'admin edits stay on the selected enquiry');
    db()->prepare("INSERT INTO event_bookings(booking_reference,client_name,phone,email,event_date) VALUES (?,'Referred fixture','9000022222','referred@example.test',DATE_ADD(CURDATE(),INTERVAL 10 DAY))")->execute([generate_unique_booking_reference('Referred fixture')]);
    $referredId=(int)db()->lastInsertId();
    save_booking_referral($referredId,member_referral_code($originalId)['code']);
    customer_check(referral_bonus_available($newId)===referral_bonus_available($originalId)&&referral_bonus_available($newId)<rate_setting_value('referral_bonus_cap',5000),'returning booking does not reset lifetime referral bonus cap');
    $before=(int)db()->query('SELECT COUNT(*) FROM event_bookings')->fetchColumn();$r=customer_submit($post);
    customer_check($r['error']!==''&&(int)db()->query('SELECT COUNT(*) FROM event_bookings')->fetchColumn()===$before,'double submit does not create a third record');
    $again=returning_customer_lookup('9000012345',$reference);
    customer_check($again['customer']['area_from']==='Updated area','future verified lookup uses latest customer contact details');
    $r=customer_submit($post);customer_check($r['error']!==''&&(int)db()->query('SELECT COUNT(*) FROM event_bookings')->fetchColumn()===$before,'already reserved date remains blocked after re-verification');
    $_SESSION['returning_customer']['expires']=time()-1;
    customer_check(returning_customer_context('9000012345')===null,'expired verification cannot authorize a new enquiry');
    db()->exec('DELETE FROM customer_lookup_limits');
    for($i=0;$i<5;$i++){try{returning_customer_lookup('9000012345','WRONG');}catch(RuntimeException $e){}}
    customer_rejects(fn()=>returning_customer_lookup('9000012345',$reference),'lookup rate limit enforced');
    echo 'PASS: '.$checks." returning-customer checks (isolated database; no external messages).\n";
}finally{
    if(db()->inTransaction())db()->rollBack();db()->exec('USE `anantha`');db()->exec('DROP DATABASE IF EXISTS `'.$database.'`');
    if(is_file($privateSettings))unlink($privateSettings);putenv('ANANTHA_MAIL_SETTINGS_PATH');session_destroy();
}

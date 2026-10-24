<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
putenv('GOOGLE_PLACES_API_KEY=');putenv('GOOGLE_PLACE_ID=');
putenv('ANANTHA_GOOGLE_SETTINGS_PATH='.sys_get_temp_dir().'/anantha-google-disabled-'.bin2hex(random_bytes(8)).'.php');
require_once __DIR__.'/../includes/config.php';
if(in_array($argv[1] ?? '',['--action','--api-public','--api-admin','--login'],true)){
    $database=$argv[2] ?? '';if(!preg_match('/^anantha_management_test_[a-f0-9]+$/D',$database))exit(2);
    db()->exec('USE `'.$database.'`');$input=json_decode(base64_decode($argv[3]),true);
    session_id('management-worker-'.bin2hex(random_bytes(8)));require_once __DIR__.'/../includes/admin_layout.php';
    if(in_array($argv[1],['--action','--api-admin'],true))$_SESSION['admin_id']=(int)db()->query('SELECT id FROM admin_users LIMIT 1')->fetchColumn();
    $_SERVER['REQUEST_METHOD']=in_array($argv[1],['--action','--login'],true)?'POST':'GET';$_FILES=[];$_GET=[];$_POST=[];
    register_shutdown_function(function(){if(session_status()===PHP_SESSION_ACTIVE)session_destroy();});
    if($argv[1]==='--login'){
        $_POST=$input;if(!isset($_POST['csrf_token']))$_POST['csrf_token']=recovery_csrf_token();
        ob_start();register_shutdown_function(function(){ob_end_clean();echo json_encode(['signed_in'=>!empty($_SESSION['admin_id'])]);});require __DIR__.'/../admin/login.php';
    }elseif($argv[1]==='--action'){
        $_POST=$input;if(!isset($_POST['csrf_token']))$_POST['csrf_token']=referral_csrf_token();
        register_shutdown_function(function(){echo json_encode($_SESSION['flash'] ?? []);});require __DIR__.'/../admin/actions.php';
    }else{
        $endpoint=$input['endpoint'];if(!in_array($endpoint,['availability','reviews','gallery','bookings','payments','client-document'],true))exit(2);
        $_GET=$input['query'] ?? [];require __DIR__.'/../api/'.$endpoint.'.php';
    }exit;
}
$database='anantha_management_test_'.bin2hex(random_bytes(5));$settings=sys_get_temp_dir().'/'.$database.'.php';file_put_contents($settings,"<?php return ['enabled'=>false];");putenv('ANANTHA_MAIL_SETTINGS_PATH='.$settings);
$documentPath=__DIR__.'/../uploads/clients/management-test-'.bin2hex(random_bytes(8)).'.png';
$imageBytes=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Zl1sAAAAASUVORK5CYII=');
$checks=0;
function management_check(bool $ok,string $label):void{global $checks;if(!$ok)throw new RuntimeException('FAILED: '.$label);$checks++;}
function management_request(string $mode,array $input,bool $raw=false):mixed{
    global $database;$pipes=[];$p=proc_open([PHP_BINARY,__FILE__,$mode,$database,base64_encode(json_encode($input))],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
    $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($p)!==0||$err!=='')throw new RuntimeException($err.' '.$out);
    if($raw)return $out;$result=json_decode($out,true);if(!is_array($result))throw new RuntimeException($out);return $result;
}
try{
    db()->exec('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');db()->exec('USE `'.$database.'`');
    $schema=preg_replace('/^(CREATE DATABASE[^;]+;|USE\s+anantha;)[\r\n]*/mi','',file_get_contents(__DIR__.'/../database.sql'));if(preg_match('/\bUSE\s+anantha\b/i',$schema))throw new RuntimeException('Unsafe test schema');db()->exec($schema);
    ensure_booking_enquiry_schema();ensure_terms_schema();ensure_gallery_category_schema();ensure_blog_images_schema();
    db()->prepare('UPDATE admin_users SET email=?,password_hash=? WHERE id=(SELECT first_id FROM (SELECT MIN(id) AS first_id FROM admin_users) u)')->execute(['login-fixture@example.test',password_hash('Fixture-passphrase-for-tests',PASSWORD_DEFAULT)]);
    $r=management_request('--login',['email'=>'login-fixture@example.test','password'=>'Fixture-passphrase-for-tests','csrf_token'=>'invalid']);management_check(!$r['signed_in'],'login rejects invalid CSRF');
    $r=management_request('--login',['email'=>'login-fixture@example.test','password'=>'Wrong fixture passphrase']);management_check(!$r['signed_in'],'login rejects incorrect password');
    $r=management_request('--login',['email'=>'login-fixture@example.test','password'=>'Fixture-passphrase-for-tests']);management_check($r['signed_in'],'login accepts valid credentials');
    file_put_contents($documentPath,$imageBytes);$relative='uploads/clients/'.basename($documentPath);
    db()->prepare("INSERT INTO event_bookings(booking_reference,client_name,phone,event_date,event_type,aadhaar_image,booking_status) VALUES ('AUDIT-PRIVATE-CUSTOMER','Private Fixture Name','9000009876',DATE_ADD(CURDATE(),INTERVAL 3 DAY),'Private event',?,'confirmed')")->execute([$relative]);$bookingId=(int)db()->lastInsertId();
    $date=date('Y-m-d',strtotime('+3 days'));$r=management_request('--api-public',['endpoint'=>'availability','query'=>['date'=>$date,'exclude_booking_id'=>$bookingId]]);
    management_check(!$r['available']&&!isset($r['booking']['client_name'],$r['booking']['event_type']),'public calendar hides identity and ignores exclusion IDs');
    management_check(array_keys($r['booking'])===['event_date','booking_status'],'public booking response contains only calendar fields');
    $r=management_request('--api-admin',['endpoint'=>'availability','query'=>['date'=>$date,'exclude_booking_id'=>$bookingId]]);management_check($r['available'],'admin editor can exclude its own booking');
    $r=management_request('--api-public',['endpoint'=>'availability','query'=>['month'=>substr($date,0,7)]]);management_check(!str_contains(json_encode($r),'Private Fixture Name'),'monthly public response hides customer names');
    $r=management_request('--api-public',['endpoint'=>'availability','query'=>['month'=>'2026-99']]);management_check(!$r['ok'],'invalid month rejected');
    foreach(['bookings','payments','client-document'] as $endpoint){$r=management_request('--api-public',['endpoint'=>$endpoint,'query'=>['booking_id'=>$bookingId]]);management_check(!$r['ok'],'unauthenticated '.$endpoint.' denied');}
    $r=management_request('--api-admin',['endpoint'=>'bookings']);management_check($r['ok']&&count($r['bookings'])===1,'admin bookings API available');
    management_check(management_request('--api-admin',['endpoint'=>'client-document','query'=>['booking_id'=>$bookingId]],true)===$imageBytes,'authorized document endpoint serves the fixture image');
    $r=management_request('--action',['action'=>'save_review','client_name'=>'Venue feedback fixture','review_text'=>'Internal feedback','event_type'=>'Birthday','rating'=>'4','status'=>'published']);management_check($r['type']==='ok','admin review creation works');
    $reviewId=(int)db()->query('SELECT MAX(id) FROM reviews')->fetchColumn();
    $r=management_request('--api-public',['endpoint'=>'reviews']);management_check($r['source']==='google'&&!str_contains(json_encode($r),'Internal feedback'),'public reviews API does not expose private website feedback');
    $r=management_request('--action',['action'=>'review_status','review_id'=>$reviewId,'status'=>'hidden','csrf_token'=>'']);management_check($r['type']==='error'&&db()->query('SELECT status FROM reviews WHERE id='.$reviewId)->fetchColumn()==='published','missing CSRF blocks review mutation');
    $r=management_request('--action',['action'=>'review_status','review_id'=>$reviewId,'status'=>'hidden']);management_check($r['type']==='ok'&&db()->query('SELECT status FROM reviews WHERE id='.$reviewId)->fetchColumn()==='hidden','review moderation works with CSRF');
    db()->exec("INSERT INTO gallery_images(title,category,image_path,display_order,is_active) VALUES ('Gallery fixture','Events','assets/logo.jpeg',10,1)");$imageId=(int)db()->lastInsertId();
    $edit=['action'=>'save_gallery_image','image_id'=>$imageId,'title'=>'Edited gallery fixture','category'=>'Venue','display_order'=>'2'];
    $r=management_request('--action',$edit);management_check($r['type']==='ok','gallery metadata update accepted');
    $saved=db()->query('SELECT * FROM gallery_images WHERE id='.$imageId)->fetch();management_check($saved['title']==='Edited gallery fixture'&&$saved['category']==='Venue'&&(int)$saved['display_order']===2&&(int)$saved['is_active']===0,'gallery title, category, order and hidden state persisted');
    $r=management_request('--api-public',['endpoint'=>'gallery']);management_check(!in_array($imageId,array_map('intval',array_column($r['images'],'id')),true),'hidden gallery item excluded publicly');
    $edit['is_active']='1';management_request('--action',$edit);$r=management_request('--api-public',['endpoint'=>'gallery']);management_check(in_array($imageId,array_map('intval',array_column($r['images'],'id')),true),'gallery visibility can be restored');
    $bad=$edit;$bad['title']='';$r=management_request('--action',$bad);management_check($r['type']==='error'&&db()->query('SELECT title FROM gallery_images WHERE id='.$imageId)->fetchColumn()==='Edited gallery fixture','invalid image metadata rejected without losing prior data');
    $slug=array_key_first(seo_blog_pages());db()->prepare("INSERT INTO blog_images(page_slug,title,alt_text,image_path,display_order,is_active) VALUES (?,'Blog fixture','Old alt','assets/logo.jpeg',1,1)")->execute([$slug]);$blogId=(int)db()->lastInsertId();
    $r=management_request('--action',['action'=>'save_blog_image','image_id'=>$blogId,'title'=>'Edited blog fixture','alt_text'=>'Updated accessible description','display_order'=>3]);management_check($r['type']==='ok'&&db()->query('SELECT alt_text FROM blog_images WHERE id='.$blogId)->fetchColumn()==='Updated accessible description','blog image title and alt text editing works');
    $r=management_request('--action',['action'=>'save_term','title'=>'Audit extra term','term_text'=>'Required fixture term.','display_order'=>99,'is_active'=>'1','is_booking_required'=>'1']);management_check($r['type']==='error','fourth mandatory booking term rejected');
    $r=management_request('--action',['action'=>'save_term','title'=>'Audit optional term','term_text'=>'Optional fixture term.','display_order'=>99,'is_active'=>'1']);management_check($r['type']==='ok','optional term creation works');
    session_id('management-parent-'.bin2hex(random_bytes(8)));require_once __DIR__.'/../includes/admin_layout.php';
    $html=admin_csrf_forms('<form method="POST" action="/actions/"><input name="x"></form><form method="GET"></form>');management_check(substr_count($html,'name="csrf_token"')===1,'shared renderer protects POST forms without changing GET forms');
    management_check(admin_csrf_forms($html)===$html,'existing form token is not duplicated');
    $badFile=tempnam(sys_get_temp_dir(),'audit-upload-');file_put_contents($badFile,'not an image');$_FILES=['fixture'=>['error'=>UPLOAD_ERR_OK,'tmp_name'=>$badFile,'name'=>'bad.jpg']];
    $rejected=false;try{upload_image('fixture','uploads/gallery');}catch(RuntimeException $e){$rejected=true;}management_check($rejected,'invalid image content reports an error');unlink($badFile);
    $_FILES=['fixture'=>['error'=>UPLOAD_ERR_PARTIAL,'name'=>'partial.jpg']];$rejected=false;try{upload_image('fixture','uploads/gallery');}catch(RuntimeException $e){$rejected=true;}management_check($rejected,'partial upload reports a clear failure');
    $_FILES=[];management_check(upload_image('fixture','uploads/gallery')===null,'optional missing upload leaves existing image unchanged');
    db()->prepare("INSERT INTO event_bookings(booking_reference,client_name,phone,event_date,booking_status) VALUES ('AUDIT-LEGACY-SAME-DATE','Second fixture','9000009877',?,'confirmed')")->execute([$date]);$sameDateId=(int)db()->lastInsertId();
    $r=management_request('--action',['action'=>'quick_update_booking_status','booking_id'=>$bookingId,'booking_status'=>'cancelled','payment_status'=>'pending']);
    management_check($r['type']==='ok'&&db()->query('SELECT booking_status FROM event_bookings WHERE id='.$sameDateId)->fetchColumn()==='confirmed','cancelling a booking leaves other bookings on the same date unchanged');
    $r=management_request('--action',['action'=>'quick_update_booking_status','booking_id'=>$bookingId,'booking_status'=>'confirmed','payment_status'=>'pending']);management_check($r['type']==='error','cancelled booking cannot reopen on an occupied date');
    $newBooking=['action'=>'save_booking_details','client_name'=>'New date fixture','phone'=>'9000009878','event_date'=>$date,'booking_status'=>'pending'];
    $before=(int)db()->query('SELECT COUNT(*) FROM event_bookings')->fetchColumn();$r=management_request('--action',$newBooking);
    management_check($r['type']==='error'&&(int)db()->query('SELECT COUNT(*) FROM event_bookings')->fetchColumn()===$before,'direct admin submission cannot create a booking on a reserved date');
    $bad=$newBooking;$bad['event_date']=date('Y-m-d',strtotime('-1 day'));$r=management_request('--action',$bad);management_check($r['type']==='error','new booking cannot use a past date');
    $bad=$newBooking;$bad['event_date']='2026-02-31';$r=management_request('--action',$bad);management_check($r['type']==='error','invalid calendar date rejected by server');
    $bad=$newBooking;$bad['event_date']=date('Y-m-d',strtotime('+8 days'));$bad['guests']='301';$r=management_request('--action',$bad);management_check($r['type']==='error','guest count above capacity rejected by server');
    $r=management_request('--action',['action'=>'unknown_fixture_action']);management_check($r['type']==='error','unknown admin action reports a clear error');
    echo 'PASS: '.$checks." admin management/access checks (isolated database and fixture document; no external messages).\n";
}finally{
    if(db()->inTransaction())db()->rollBack();db()->exec('USE `anantha`');db()->exec('DROP DATABASE IF EXISTS `'.$database.'`');
    if(is_file($settings))unlink($settings);if(is_file($documentPath))unlink($documentPath);putenv('ANANTHA_MAIL_SETTINGS_PATH');if(session_status()===PHP_SESSION_ACTIVE)session_destroy();
}

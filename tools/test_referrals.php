<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Run: D:\xampp\php\php.exe tools/test_referrals.php
// Creates and removes only a uniquely named anantha_referral_test_* database.
require_once __DIR__ . '/../includes/config.php';
if (($argv[1] ?? '') === '--worker') {
    $database = $argv[2] ?? '';
    if (!preg_match('/^anantha_referral_test_[a-f0-9]+$/',$database)) exit(2);
    db()->exec('USE `' . $database . '`');
    ensure_referral_schema();
    try { save_booking_referral((int)$argv[3],$argv[4]); echo 'accepted'; }
    catch (RuntimeException $e) { echo 'rejected'; }
    exit;
}
if (in_array($argv[1] ?? '',['--action','--public'],true)) {
    $database=$argv[2] ?? '';
    if (!preg_match('/^anantha_referral_test_[a-f0-9]+$/',$database)) exit(2);
    db()->exec('USE `'.$database.'`');
    $_POST=json_decode(base64_decode($argv[3]),true); $_GET=[]; $_FILES=[];
    $_SERVER['REQUEST_METHOD']='POST';
    if ($argv[1]==='--action') {
        session_id('referral-test-'.bin2hex(random_bytes(8)));
        require_once __DIR__.'/../includes/admin_auth.php';
        $_SESSION['admin_id']=(int)db()->query('SELECT id FROM admin_users LIMIT 1')->fetchColumn();
        if (!isset($_POST['csrf_token'])) $_POST['csrf_token']=referral_csrf_token();
        register_shutdown_function(function(){echo json_encode($_SESSION['flash'] ?? []);session_destroy();});
        require __DIR__.'/../admin/actions.php';
    } else {
        ob_start(); require __DIR__.'/../availability.php'; ob_end_clean();
        echo json_encode(['error'=>$formError,'success'=>$success,'id'=>$bookingId ?? 0]);
    }
    exit;
}
$database='anantha_referral_test_'.bin2hex(random_bytes(5));
$checks=0;
function check(bool $condition,string $message): void {
    global $checks;
    if (!$condition) throw new RuntimeException('FAILED: '.$message);
    $checks++;
}
function rejects(callable $operation,string $message): void {
    $failed=false;
    try { $operation(); } catch (RuntimeException $e) { $failed=true; }
    check($failed,$message);
}
function fixture(string $name): int {
    static $n=0; $n++;
    db()->prepare("INSERT INTO event_bookings(booking_reference,client_name,phone,email,event_date,booking_status) VALUES (?,?,?,?,?,'confirmed')")
        ->execute([generate_unique_booking_reference($name),$name,(string)(9000000000+$n),'person'.$n.'@example.test',date('Y-m-d')]);
    $id=(int)db()->lastInsertId(); member_referral_code($id); return $id;
}
function booking(int $id): array { $s=db()->prepare('SELECT * FROM event_bookings WHERE id=?');$s->execute([$id]);return $s->fetch(); }
try {
    db()->exec('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    db()->exec('USE `'.$database.'`');
    $schema=file_get_contents(__DIR__.'/../database.sql');
    $schema=preg_replace('/^(CREATE DATABASE[^;]+;|USE\s+anantha;)[\r\n]*/mi','',$schema);
    if (preg_match('/\bUSE\s+anantha\b/i',$schema)) throw new RuntimeException('Unsafe test schema');
    db()->exec($schema);
    db()->exec("INSERT INTO event_bookings(booking_reference,client_name,phone,event_date,booking_status) VALUES ('ANT-LEG-1234','Legacy','8111111111',CURDATE(),'confirmed')");
    $legacyId=(int)db()->lastInsertId();
    db()->prepare("INSERT INTO event_bookings(booking_reference,client_name,phone,event_date,referred_by_reference,referral_eligible,referral_referrer_bonus) VALUES ('ANT-OLD-1234','Legacy child','8222222222',CURDATE(),'ANT-LEG-1234',1,2000)")->execute();
    ensure_referral_schema();
    $legacy=member_referral_code($legacyId);
    check($legacy['code']==='ANT-LEG-1234' && (int)$legacy['usage_count']===1,'legacy codes and usage migrated');
    db()->exec("UPDATE rate_card_settings SET setting_value=1 WHERE setting_key='referral_code_usage_limit'");
    db()->exec("UPDATE rate_card_settings SET setting_value=3000 WHERE setting_key='referral_bonus_cap'");
    db()->exec("UPDATE rate_card_settings SET setting_value=2 WHERE setting_key='referral_max_count'");
    $owner=fixture('Owner');$first=fixture('First');$second=fixture('Second');$third=fixture('Third');
    $originalBookingRef=booking($owner)['booking_reference'];
    $code=member_referral_code($owner);
    check($code['code']!==$originalBookingRef,'referral and booking references separate');
    check(resolve_referral_reference(booking($owner)['phone'])===$code['code'],'phone resolves current code');
    save_booking_referral($first,$code['code']);
    check((float)booking($first)['referral_referrer_bonus']===2000.0,'first bonus applied');
    $logCount=(int)db()->query('SELECT COUNT(*) FROM referral_logs')->fetchColumn();
    save_booking_referral($first,$code['code']);
    check((int)member_referral_code($owner)['usage_count']===1,'duplicate saves consume one use');
    check((int)db()->query('SELECT COUNT(*) FROM referral_logs')->fetchColumn()===$logCount,'duplicate save does not duplicate logs');
    check(referral_code_state(member_referral_code($owner))==='exhausted','limit exhausts code');
    rejects(fn()=>save_booking_referral($second,$code['code']),'exhausted code rejected');
    $replacement=member_referral_code($owner,true,(int)$code['id']);
    check($replacement['code']!==$code['code'],'manual replacement differs');
    check(booking($owner)['booking_reference']===$originalBookingRef,'booking reference never changed');
    check(resolve_referral_reference($code['code'])===null,'retired code rejected for new claims');
    save_booking_referral($first,$code['code']);
    check(booking($first)['referred_by_reference']===$code['code'],'historic retired referral remains editable');
    rejects(fn()=>member_referral_code($owner,true,(int)$code['id']),'duplicate admin replacement rejected');
    save_booking_referral($second,$replacement['code']);
    check((float)booking($second)['referral_referrer_bonus']===1000.0,'lifetime cap retained across replacements');
    $next=member_referral_code($owner,true,(int)$replacement['id']);
    save_booking_referral($third,$next['code']);
    check((float)booking($third)['referral_referrer_bonus']===0.0,'paid-referral limit cannot be bypassed by replacement');
    save_booking_referral($first,'');
    check(booking($first)['referred_by_reference']===null && (float)booking($first)['referral_referrer_bonus']===0.0,'removal clears stale bonus data');
    rejects(fn()=>save_booking_referral($first,$next['code']),'removed referral cannot be claimed again');
    $self=fixture('Self');$selfCode=member_referral_code($self);
    rejects(fn()=>save_booking_referral($self,$selfCode['code']),'self-referral rejected');
    $expired=fixture('Expired');$expiredCode=member_referral_code($expired);
    db()->prepare("UPDATE referral_codes SET expires_on=DATE_SUB(CURDATE(),INTERVAL 1 DAY) WHERE id=?")->execute([$expiredCode['id']]);
    check(resolve_referral_reference($expiredCode['code'])===null,'expired code rejected');
    $renewed=member_referral_code($expired,true,(int)$expiredCode['id']);
    check(referral_code_state($renewed)==='active','admin replacement renews validity');
    db()->prepare("UPDATE event_bookings SET booking_status='cancelled' WHERE id=?")->execute([$second]);sync_cancelled_referrals();
    check((float)booking($second)['referral_referrer_bonus']===0.0,'cancellation clears bonuses');
    check((int)member_referral_code($owner)['usage_count']===1,'cancellation does not reset consumed code slots');
    db()->prepare("UPDATE event_bookings SET booking_status='cancelled' WHERE id=?")->execute([$expired]);
    check(resolve_referral_reference($renewed['code'])===null,'cancelled member cannot refer');
    rejects(fn()=>member_referral_code($expired,true),'cancelled member cannot renew code');
    $rollbackOwner=fixture('Rollback owner');$rollbackChild=fixture('Rollback child');$rollbackCode=member_referral_code($rollbackOwner);
    db()->beginTransaction();save_booking_referral($rollbackChild,$rollbackCode['code']);db()->rollBack();
    check((int)member_referral_code($rollbackOwner)['usage_count']===0 && booking($rollbackChild)['referred_by_reference']===null,'rollback restores code counter and booking together');
    $duplicate=fixture('Duplicate contact');
    db()->prepare('UPDATE event_bookings SET whatsapp_number=? WHERE id=?')->execute([booking($rollbackOwner)['phone'],$duplicate]);
    rejects(fn()=>save_booking_referral($duplicate,$rollbackCode['code']),'cross-mobile/WhatsApp duplicate rejected');
    $codes=[];for($i=0;$i<500;$i++)$codes[]=generate_unique_booking_reference('Same name');
    check(count(array_unique($codes))===500,'500 generated booking references are distinct');
    $didReject=false;try {db()->prepare("INSERT INTO issued_reference_codes(code,kind) VALUES (?,'referral')")->execute([$codes[0]]);}catch(PDOException $e){$didReject=(int)($e->errorInfo[1]??0)===1062;}
    check($didReject,'database rejects duplicate reserved reference');
    $raceOwner=fixture('Concurrent owner');$raceA=fixture('Concurrent A');$raceB=fixture('Concurrent B');$raceCode=member_referral_code($raceOwner);
    $workers=[];
    foreach([$raceA,$raceB] as $id){$pipes=[];$process=proc_open([PHP_BINARY,__FILE__,'--worker',$database,(string)$id,$raceCode['code']],[1=>['pipe','w'],2=>['pipe','w']],$pipes);$workers[]=[$process,$pipes];}
    $outcomes=[];foreach($workers as [$process,$pipes]){$outcomes[]=trim(stream_get_contents($pipes[1]));$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);check(proc_close($process)===0,'parallel worker finished: '.$error);}
    sort($outcomes);check($outcomes===['accepted','rejected'],'two concurrent claims for one slot accept exactly one');
    check((int)member_referral_code($raceOwner)['usage_count']===1,'concurrent counter does not exceed limit');
    // Exercise real action handlers and the public enquiry route against this same isolated database.
    $runRoute = function(string $mode,array $post) use ($database): array {
        $pipes=[];$process=proc_open([PHP_BINARY,__FILE__,$mode,$database,base64_encode(json_encode($post))],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
        $output=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
        if(proc_close($process)!==0 || $error!=='') throw new RuntimeException('Route failed: '.$error.' '.$output);
        $result=json_decode($output,true);if(!is_array($result))throw new RuntimeException('Invalid route response: '.$output);return $result;
    };
    $routeOwner=fixture('Route owner');$routeCode=member_referral_code($routeOwner);
    $actionMember=fixture('Action member');
    $payload=booking($actionMember);
    $payload=array_merge($payload,['action'=>'save_booking_details','booking_id'=>$actionMember,'referred_by_reference'=>$routeCode['code'],'date_of_birth'=>'','occupation'=>'','pin_code'=>'','area_from'=>'','city_district'=>'','client_state'=>'','aadhaar_number'=>'','package_id'=>'','guests'=>100,'proposal_amount'=>'','proposal_note'=>'','note'=>'']);
    $result=$runRoute('--action',$payload);
    check(($result['type']??'')==='ok','admin booking save succeeds: '.($result['message']??''));
    check((int)member_referral_code($routeOwner)['usage_count']===1,'admin booking save records referral once');
    $result=$runRoute('--action',$payload);
    check(($result['type']??'')==='ok' && (int)member_referral_code($routeOwner)['usage_count']===1,'admin repeat save remains idempotent');
    $referralPost=['action'=>'save_referral','booking_id'=>$actionMember,'referred_by_reference'=>$routeCode['code'],'referral_eligible'=>1,'referral_referrer_bonus'=>'0','referral_referred_bonus'=>'0','referral_note'=>'Admin test'];
    $result=$runRoute('--action',$referralPost);
    check(($result['type']??'')==='ok' && (float)booking($actionMember)['referral_referrer_bonus']===0.0,'admin can explicitly save zero bonus');
    $result=$runRoute('--action',['action'=>'replace_referral_code','booking_id'=>$routeOwner,'expected_code_id'=>$routeCode['id']]);
    check(($result['type']??'')==='ok','admin replacement handler succeeds');
    $result=$runRoute('--action',['action'=>'replace_referral_code','booking_id'=>$routeOwner,'expected_code_id'=>$routeCode['id']]);
    check(($result['type']??'')==='error','admin duplicate replacement handler rejects stale code');
    $result=$runRoute('--action',['action'=>'save_referral_settings','usage_limit'=>'2','eligibility_days'=>'45','apply_current'=>1]);
    check(($result['type']??'')==='ok' && referral_code_usage_limit()===2 && (int)member_referral_code($routeOwner)['usage_limit']===2,'admin settings apply to existing codes');
    $result=$runRoute('--action',['action'=>'save_referral_settings','usage_limit'=>'0','eligibility_days'=>'45']);
    check(($result['type']??'')==='error' && referral_code_usage_limit()===2,'invalid settings rejected');
    $result=$runRoute('--action',['action'=>'save_referral_settings','usage_limit'=>'3','eligibility_days'=>'45','csrf_token'=>'invalid']);
    check(($result['type']??'')==='error' && referral_code_usage_limit()===2,'settings reject invalid CSRF');
    $rateLogBefore=(int)db()->query("SELECT COUNT(*) FROM referral_logs WHERE event_type='settings_updated'")->fetchColumn();
    $result=$runRoute('--action',['action'=>'save_rate_setting','setting_key'=>'referral_code_usage_limit','label'=>'Uses allowed per referral code','setting_value'=>'3','suffix'=>'uses','display_order'=>'13']);
    check(($result['type']??'')==='ok' && referral_code_usage_limit()===3,'Rate Card can edit referral limit');
    check((int)db()->query("SELECT COUNT(*) FROM referral_logs WHERE event_type='settings_updated'")->fetchColumn()===$rateLogBefore+1,'Rate Card referral edit is audited');
    $result=$runRoute('--action',['action'=>'save_rate_setting','setting_key'=>'referral_code_usage_limit','label'=>'Uses allowed per referral code','setting_value'=>'4','csrf_token'=>'invalid']);
    check(($result['type']??'')==='error' && referral_code_usage_limit()===3,'Rate Card rejects invalid referral CSRF');
    ensure_terms_schema();ensure_package_schema();
    $terms=booking_required_terms(3);$package=active_packages()[0];[$minGuests,$maxGuests]=package_guest_bounds($package['guest_range'],300);
    $publicOwner=fixture('Public referrer');$publicCode=member_referral_code($publicOwner);
    $publicPost=['enquire'=>'1','name'=>'Public Test','phone'=>'8333333333','whatsapp_number'=>'','email'=>'public@example.test','pin_code'=>'530045','area_from'=>'Test area','city_district'=>'Test city','client_state'=>'Test state','event_date'=>date('Y-m-d',strtotime('+5 days')),'event_type'=>'Wedding','guests'=>max(1,$minGuests),'package_id'=>$package['id'],'note'=>'','referred_by_reference'=>$publicCode['code'],'accept_terms'=>array_column($terms,'id')];
    $result=$runRoute('--public',$publicPost);
    check($result['error']==='' && $result['success']!=='' && $result['id']>0,'public enquiry handler succeeds: '.$result['error']);
    check((int)member_referral_code($publicOwner)['usage_count']===1,'public enquiry records referral use');
    $before=(int)db()->query('SELECT COUNT(*) FROM event_bookings')->fetchColumn();
    $result=$runRoute('--public',$publicPost);
    check($result['error']!=='' && (int)db()->query('SELECT COUNT(*) FROM event_bookings')->fetchColumn()===$before,'public repeat submission does not create duplicate booking');
    echo "PASS: $checks referral checks (isolated database).\n";
} finally {
    if (db()->inTransaction()) db()->rollBack();
    if (!preg_match('/^anantha_referral_test_[a-f0-9]+$/',$database)) throw new RuntimeException('Unsafe cleanup target');
    db()->exec('DROP DATABASE IF EXISTS `'.$database.'`');
}

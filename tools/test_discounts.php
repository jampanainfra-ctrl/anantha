<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/../includes/config.php';
if (($argv[1]??'')==='--action') {
    $database=$argv[2]??'';
    if(!preg_match('/^anantha_discount_test_[a-f0-9]+$/',$database))exit(2);
    db()->exec('USE `'.$database.'`');
    $_POST=json_decode(base64_decode($argv[3]),true);$_GET=[];$_FILES=[];$_SERVER['REQUEST_METHOD']='POST';
    session_id('discount-test-'.bin2hex(random_bytes(8)));
    require_once __DIR__.'/../includes/admin_auth.php';
    $_SESSION['admin_id']=(int)db()->query('SELECT id FROM admin_users LIMIT 1')->fetchColumn();
    if(!isset($_POST['csrf_token']))$_POST['csrf_token']=referral_csrf_token();
    register_shutdown_function(function(){echo json_encode($_SESSION['flash']??[]);session_destroy();});
    require __DIR__.'/../admin/actions.php';exit;
}
$database='anantha_discount_test_'.bin2hex(random_bytes(5));$checks=0;
function check(bool $ok,string $message):void{global $checks;if(!$ok)throw new RuntimeException('FAILED: '.$message);$checks++;}
function row(int $id):array{$s=db()->prepare('SELECT * FROM event_bookings WHERE id=?');$s->execute([$id]);return $s->fetch();}
function paycount(int $id):int{$s=db()->prepare('SELECT COUNT(*) FROM payments WHERE booking_id=?');$s->execute([$id]);return (int)$s->fetchColumn();}
try {
    db()->exec('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');db()->exec('USE `'.$database.'`');
    $schema=preg_replace('/^(CREATE DATABASE[^;]+;|USE\s+anantha;)[\r\n]*/mi','',file_get_contents(__DIR__.'/../database.sql'));
    if(preg_match('/\bUSE\s+anantha\b/i',$schema))throw new RuntimeException('Unsafe test schema');db()->exec($schema);
    ensure_booking_enquiry_schema();ensure_referral_schema();
    $route=function(array $post)use($database):array{
        $pipes=[];$proc=proc_open([PHP_BINARY,__FILE__,'--action',$database,base64_encode(json_encode($post))],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
        $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
        if(proc_close($proc)!==0||$err!=='')throw new RuntimeException($err.' '.$out);
        $result=json_decode($out,true);if(!is_array($result))throw new RuntimeException($out);return $result;
    };
    db()->prepare("INSERT INTO event_bookings(booking_reference,client_name,phone,event_date) VALUES (?,'Discount Test','9000000000',CURDATE())")->execute([generate_unique_booking_reference('Discount Test')]);$id=(int)db()->lastInsertId();
    $base=['action'=>'save_payment_tab','booking_id'=>$id,'proposal_amount'=>'10000','discount_type'=>'percent','discount_value'=>'10','payment_note'=>'Agreed discount','paid_on'=>date('Y-m-d'),'payment_mode'=>'cash','verification_status'=>'verified'];
    $result=$route($base+['save_pricing'=>'1']);
    check(($result['type']??'')==='ok','discount-only save succeeds');check(paycount($id)===0,'discount-only adds no payment');
    $price=booking_price_summary(row($id));check($price['net_cents']===900000&&$price['discount_cents']===100000,'10 percent discount');
    check((float)row($id)['proposal_amount']===10000.0,'original total preserved');
    $route($base+['save_pricing'=>'1']);check(booking_price_summary(row($id))['net_cents']===900000,'repeat save does not compound discount');
    $result=$route($base+['amount'=>'3000']);check(($result['type']??'')==='ok'&&row($id)['payment_status']==='advance_paid','first verified instalment is advance paid');
    $result=$route($base+['amount'=>'6000']);check(($result['type']??'')==='ok'&&row($id)['payment_status']==='paid','multiple payments settle discounted total');
    check(paycount($id)===2,'two instalments recorded');
    $fixed=array_replace($base,['discount_type'=>'fixed','discount_value'=>'500','save_pricing'=>'1']);$route($fixed);
    check(booking_price_summary(row($id))['net_cents']===950000&&row($id)['payment_status']==='advance_paid','fixed discount and status recalculated');
    $none=array_replace($fixed,['discount_type'=>'none','discount_value'=>'999999']);$route($none);
    check((float)row($id)['discount_value']===0.0&&booking_price_summary(row($id))['net_cents']===1000000,'no discount clears old discount');
    $before=row($id);$count=paycount($id);
    foreach([
        ['discount_type'=>'percent','discount_value'=>'101'],['discount_type'=>'percent','discount_value'=>'-1'],
        ['discount_type'=>'fixed','discount_value'=>'10001'],['discount_type'=>'other','discount_value'=>'5'],
        ['proposal_amount'=>'-10'],['proposal_amount'=>'10000.001'],['discount_value'=>'1e2'],
        ['proposal_amount'=>'','discount_type'=>'percent','discount_value'=>'10'],['csrf_token'=>'invalid']
    ]as$invalid){$result=$route(array_replace($base,['save_pricing'=>'1'],$invalid));check(($result['type']??'')==='error','invalid pricing rejected: '.json_encode($invalid));}
    check(row($id)['discount_type']===$before['discount_type']&&row($id)['proposal_amount']===$before['proposal_amount']&&paycount($id)===$count,'failed pricing saves leave booking and payments unchanged');
    $result=$route(array_replace($base,['amount'=>'0']));check(($result['type']??'')==='error'&&row($id)['discount_type']==='none','invalid payment cannot partially save discount');
    $result=$route(array_replace($base,['amount'=>'1','payment_mode'=>'invalid']));check(($result['type']??'')==='error','invalid payment type rejected');
    $result=$route(array_replace($base,['amount'=>'1','paid_on'=>'2026-02-30']));check(($result['type']??'')==='error','invalid payment date rejected');
    $result=$route(array_replace($base,['amount'=>'1','verification_status'=>'invalid']));check(($result['type']??'')==='error'&&row($id)['discount_type']==='none','invalid verification rolls back pricing change');
    $route(array_replace($base,['proposal_amount'=>'333.33','discount_value'=>'12.50','save_pricing'=>'1']));
    check(booking_price_summary(row($id))['discount_cents']===4167&&booking_price_summary(row($id))['net_cents']===29166,'fractional percentage rounds to nearest paise');
    $route(array_replace($base,['discount_value'=>'100','save_pricing'=>'1']));check(booking_price_summary(row($id))['net_cents']===0&&row($id)['payment_status']==='paid','100 percent discount supported');
    db()->prepare("INSERT INTO event_bookings(booking_reference,client_name,phone,event_date) VALUES (?,'Verification Test','9111111111',CURDATE())")->execute([generate_unique_booking_reference('Verification Test')]);$second=(int)db()->lastInsertId();
    $route(array_replace($base,['booking_id'=>$second,'proposal_amount'=>'1000','amount'=>'900','verification_status'=>'pending']));
    check(row($second)['payment_status']==='pending','unchecked payment does not settle discounted total');
    $paymentId=(int)db()->query('SELECT MAX(id) FROM payments')->fetchColumn();
    $route(['action'=>'update_payment','payment_id'=>$paymentId,'verification_status'=>'verified']);check(row($second)['payment_status']==='paid','verification uses discounted total');
    $route(['action'=>'update_payment','payment_id'=>$paymentId,'verification_status'=>'rejected']);check(row($second)['payment_status']==='pending','rejected payment stops counting');
    $route(array_replace($base,['booking_id'=>$second,'proposal_amount'=>'100','discount_type'=>'fixed','discount_value'=>'100','save_pricing'=>'1']));check(row($second)['payment_status']==='paid','fully waived balance with no verified payments is settled');
    // Apply the real Rate Card to booking totals and verify database snapshots/history.
    db()->prepare("INSERT INTO event_bookings(booking_reference,client_name,phone,event_date,guests) VALUES (?,'Rate Card Test','9222222222',CURDATE(),100)")->execute([generate_unique_booking_reference('Rate Card Test')]);$rateId=(int)db()->lastInsertId();
    $chargePost=array_replace($base,['booking_id'=>$rateId,'proposal_amount'=>'50000','discount_value'=>'10','save_pricing'=>'1','apply_rate_card'=>'1','electricity_units'=>'10']);
    $result=$route($chargePost);check(($result['type']??'')==='ok','Rate Card pricing save succeeds');
    $price=booking_price_summary(row($rateId));$snapshot=$price['rate_snapshot'];
    check($price['net_cents']===5619000,'grand total includes discounted venue plus cleaning, deposit and electricity');
    check($price['cleaning']===1000&&$price['deposit']===10000&&$price['electricity']===190,'itemized charge amounts correct');
    check($price['discount']===5000&&$price['venue_net']===45000,'discount applies to venue only');
    check($price['suggested_advance']===33095,'advance is 50 percent of services plus full deposit');
    check($snapshot['guest_count']===100&&$snapshot['electricity_units']==='10.00','guest count and electricity units saved');
    check(isset($snapshot['catalog']['security_deposit'])&&count($snapshot['items'])===3,'unit rates and line items saved');
    $historyCount=(int)db()->query('SELECT COUNT(*) FROM booking_pricing_history WHERE booking_id='.$rateId)->fetchColumn();
    check($historyCount===1,'pricing version saved in database');
    $route($chargePost);check((int)db()->query('SELECT COUNT(*) FROM booking_pricing_history WHERE booking_id='.$rateId)->fetchColumn()===$historyCount,'unchanged save does not duplicate pricing history');
    db()->exec("UPDATE rate_card_settings SET setting_value=20000 WHERE setting_key='security_deposit'");
    db()->exec("UPDATE rate_card_settings SET setting_value=20 WHERE setting_key='cleaning_per_guest'");
    db()->exec("UPDATE rate_card_settings SET setting_value=30 WHERE setting_key='electricity_per_unit'");
    check(booking_price_summary(row($rateId))['net_cents']===5619000,'Rate Card edits do not alter saved totals');
    $route($chargePost);check(booking_price_summary(row($rateId))['net_cents']===5619000,'later saves retain frozen rates');
    $route(array_replace($chargePost,['refresh_rate_card'=>'1']));$price=booking_price_summary(row($rateId));
    check($price['net_cents']===6730000&&$price['deposit']===20000,'explicit refresh uses current Rate Card');
    db()->prepare('UPDATE event_bookings SET guests=? WHERE id=?')->execute([150,$rateId]);
    check(booking_price_summary(row($rateId))['cleaning']===2000,'guest edits preserve saved charge until pricing is saved');
    $route($chargePost);check(booking_price_summary(row($rateId))['cleaning']===3000,'pricing save updates cleaning quantity with saved rate');
    db()->prepare('UPDATE event_bookings SET guests=? WHERE id=?')->execute([20,$rateId]);
    $route($chargePost);check(booking_price_summary(row($rateId))['cleaning']===500,'minimum cleaning charge applies to small gathering');
    db()->exec("INSERT INTO rate_card_settings(setting_key,label,setting_value,suffix,display_order) VALUES ('custom_fee','Extra setup',2000,'Rs',99)");
    $catalog=booking_rate_catalog();check(isset($catalog['extras']['custom_fee'])&&!isset($catalog['extras']['referral_bonus_amount'])&&!isset($catalog['extras']['maximum_capacity'])&&!isset($catalog['extras']['advance_percent']),'only optional monetary rates are billable extras');
    $withExtra=array_replace($chargePost,['refresh_rate_card'=>'1','extra_charge_keys'=>['custom_fee'],'extra_charge_qty'=>['custom_fee'=>'1.50']]);
    $route($withExtra);$price=booking_price_summary(row($rateId));check($price['extras']===3000&&count($price['rate_snapshot']['items'])===4,'custom monetary fee with quantity saved');
    check($price['rate_snapshot']['items'][3]['label']==='Extra setup'&&$price['rate_snapshot']['items'][3]['quantity']==='1.50','custom item label and quantity preserved');
    $unchanged=row($rateId)['rate_card_snapshot'];
    foreach([['electricity_units'=>'-1'],['electricity_units'=>'1.001'],['extra_charge_keys'=>['referral_bonus_amount']],['extra_charge_keys'=>['custom_fee'],'extra_charge_qty'=>['custom_fee'=>'0']]]as$bad){$result=$route(array_replace($withExtra,$bad));check(($result['type']??'')==='error','invalid charges rejected');}
    check(row($rateId)['rate_card_snapshot']===$unchanged,'failed charge changes preserve stored snapshot');
    $result=$route(array_replace($withExtra,['cleaning_amount'=>'0','security_deposit'=>'0','rate_card_snapshot'=>'{}']));check(($result['type']??'')==='ok'&&booking_price_summary(row($rateId))['deposit']===20000,'posted charge tampering ignored; trusted rates used');
    $pay=array_replace($withExtra,['save_pricing'=>'0','amount'=>'68800','verification_status'=>'verified']);$result=$route($pay);
    check(($result['type']??'')==='ok'&&row($rateId)['payment_status']==='paid','verified payment settles full charge-inclusive total');
    $result=$route(array_replace($withExtra,['discount_value'=>'100']));$price=booking_price_summary(row($rateId));check(($result['type']??'')==='ok'&&$price['net_cents']===2380000,'100 percent venue discount retains full charges and deposit');
    db()->exec("DELETE FROM rate_card_settings WHERE setting_key='custom_fee'");
    $keepExtra=$withExtra;unset($keepExtra['refresh_rate_card']);$result=$route($keepExtra);check(($result['type']??'')==='ok'&&booking_price_summary(row($rateId))['extras']===3000,'deleted Rate Card fee retained in saved booking history');
    $result=$route($withExtra);check(($result['type']??'')==='error','refresh cannot silently remove a selected deleted charge');
    db()->prepare('UPDATE event_bookings SET guests=NULL WHERE id=?')->execute([$rateId]);$result=$route($chargePost);check(($result['type']??'')==='error','guest count required for automatic cleaning charge');
    db()->prepare('UPDATE event_bookings SET guests=100 WHERE id=?')->execute([$rateId]);
    $meterPost=array_replace($chargePost,['electricity_meter_start'=>'1000','electricity_meter_end'=>'1025.50','electricity_units'=>'99999']);
    $result=$route($meterPost);$price=booking_price_summary(row($rateId));$meter=$price['rate_snapshot'];
    check(($result['type']??'')==='ok'&&$meter['electricity_units']==='25.50','meter difference calculated by server; posted units ignored');
    check($meter['electricity_meter_start']==='1000.00'&&$meter['electricity_meter_end']==='1025.50','both meter readings stored');
    check($price['electricity']===765&&$price['net_cents']===6776500,'meter units multiplied by saved electricity rate');
    check($meter['items'][2]['meter_start']==='1000.00'&&$meter['items'][2]['meter_end']==='1025.50','readings stored in electricity line item');
    $history=db()->query('SELECT snapshot FROM booking_pricing_history WHERE booking_id='.$rateId.' ORDER BY id DESC LIMIT 1')->fetchColumn();check(str_contains($history,'1025.50'),'readings retained in pricing history');
    $histories=(int)db()->query('SELECT COUNT(*) FROM booking_pricing_history WHERE booking_id='.$rateId)->fetchColumn();$route($meterPost);
    check((int)db()->query('SELECT COUNT(*) FROM booking_pricing_history WHERE booking_id='.$rateId)->fetchColumn()===$histories,'unchanged meter readings do not duplicate pricing history');
    $before=row($rateId)['rate_card_snapshot'];$count=paycount($rateId);
    foreach([['electricity_meter_start'=>'100','electricity_meter_end'=>'99'],['electricity_meter_start'=>'100','electricity_meter_end'=>''],['electricity_meter_start'=>'','electricity_meter_end'=>'100'],['electricity_meter_start'=>'-1','electricity_meter_end'=>'100'],['electricity_meter_start'=>'1.001','electricity_meter_end'=>'100']]as$bad){$result=$route(array_replace($meterPost,$bad));check(($result['type']??'')==='error','invalid meter readings rejected: '.json_encode($bad));}
    check(row($rateId)['rate_card_snapshot']===$before&&paycount($rateId)===$count,'invalid readings leave pricing and payments unchanged');
    $route(array_replace($meterPost,['electricity_meter_start'=>'0','electricity_meter_end'=>'1.25']));$meter=booking_price_summary(row($rateId));check($meter['rate_snapshot']['electricity_units']==='1.25'&&$meter['electricity']===37.5,'zero start and fractional units supported');
    $route(array_replace($meterPost,['electricity_meter_start'=>'100','electricity_meter_end'=>'100']));check(booking_price_summary(row($rateId))['electricity']===0,'equal readings produce zero usage');
    $noReadings=$chargePost;unset($noReadings['electricity_meter_start'],$noReadings['electricity_meter_end']);$route($noReadings);check(booking_price_summary(row($rateId))['electricity']===0,'saved meter readings take precedence over legacy posted units');
    $route(array_replace($meterPost,['electricity_meter_start'=>'','electricity_meter_end'=>'']));check(booking_price_summary(row($rateId))['rate_snapshot']['electricity_units']==='0.00','clearing existing readings clears metered usage');
    $legacyMeter=booking_electricity_readings(['electricity_meter_start'=>'','electricity_meter_end'=>'','electricity_units'=>'999'],['electricity_units'=>'12.50']);check($legacyMeter['units']==='12.50','legacy saved units retained until readings are supplied');
    echo "PASS: $checks discount/payment checks (isolated database).\n";
}finally{
    if(db()->inTransaction())db()->rollBack();
    if(!preg_match('/^anantha_discount_test_[a-f0-9]+$/',$database))throw new RuntimeException('Unsafe cleanup target');
    db()->exec('DROP DATABASE IF EXISTS `'.$database.'`');
}

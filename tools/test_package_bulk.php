<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../includes/config.php';
if(($argv[1] ?? '')==='--action'){
    $database=$argv[2] ?? '';if(!preg_match('/^anantha_packages_test_[a-f0-9]+$/D',$database))exit(2);
    db()->exec('USE `'.$database.'`');$_POST=json_decode(base64_decode($argv[3]),true);$_GET=[];$_FILES=[];$_SERVER['REQUEST_METHOD']='POST';$_SERVER['HTTP_ACCEPT']='application/json';
    session_id('package-bulk-test-'.bin2hex(random_bytes(8)));require_once __DIR__.'/../includes/admin_auth.php';
    $_SESSION['admin_id']=(int)db()->query('SELECT id FROM admin_users LIMIT 1')->fetchColumn();
    if(!isset($_POST['csrf_token']))$_POST['csrf_token']=referral_csrf_token();
    register_shutdown_function(function(){session_destroy();});require __DIR__.'/../admin/actions.php';exit;
}
$database='anantha_packages_test_'.bin2hex(random_bytes(5));$settings=sys_get_temp_dir().'/'.$database.'.php';file_put_contents($settings,"<?php return ['enabled'=>false];");putenv('ANANTHA_MAIL_SETTINGS_PATH='.$settings);
$checks=0;
function package_check(bool $ok,string $label):void{global $checks;if(!$ok)throw new RuntimeException('FAILED: '.$label);$checks++;}
function package_rejects(callable $action,string $label):void{$rejected=false;try{$action();}catch(RuntimeException $e){$rejected=true;}package_check($rejected,$label);}
function package_row(int $id):array{$s=db()->prepare('SELECT * FROM event_packages WHERE id=?');$s->execute([$id]);return $s->fetch() ?: [];}
function package_payload(int $id):array{
    $r=package_row($id);preg_match_all('/\d+/',$r['guest_range'],$numbers);
    return ['package_name'=>$r['package_name'],'guest_min'=>$numbers[0][0],'guest_max'=>$numbers[0][1],'price'=>$r['price'] ?? '','discount_percent'=>$r['discount_percent'],'discount_reason'=>$r['discount_reason'] ?? '','display_order'=>$r['display_order'],'version'=>package_edit_version($r)]+($r['is_active']?['is_active'=>'on']:[]);
}
try{
    db()->exec('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');db()->exec('USE `'.$database.'`');
    $schema=preg_replace('/^(CREATE DATABASE[^;]+;|USE\s+anantha;)[\r\n]*/mi','',file_get_contents(__DIR__.'/../database.sql'));if(preg_match('/\bUSE\s+anantha\b/i',$schema))throw new RuntimeException('Unsafe test schema');db()->exec($schema);ensure_package_schema();
    db()->exec("INSERT INTO event_packages(package_name,guest_range,price,display_order,is_active) VALUES ('Bulk Alpha','0 to 100 guests',1000,10,1),('Bulk Beta','100 to 200 guests',2000,20,1)");
    $a=(int)db()->query("SELECT id FROM event_packages WHERE package_name='Bulk Alpha'")->fetchColumn();$b=(int)db()->query("SELECT id FROM event_packages WHERE package_name='Bulk Beta'")->fetchColumn();
    $alpha=package_payload($a);$beta=package_payload($b);$alpha['price']='1500.50';$alpha['discount_percent']='10';unset($beta['is_active']);
    $new=['package_name'=>'Bulk New','guest_min'=>'10','guest_max'=>'90','price'=>'3000','discount_percent'=>'5','discount_reason'=>'Test offer','display_order'=>'30','is_active'=>'on'];
    $r=save_packages_bulk([$a=>$alpha,$b=>$beta,'new_1'=>$new],[]);
    package_check($r===['added'=>1,'updated'=>2,'removed'=>0],'one save updates multiple rows and adds a package');
    package_check(package_row($a)['price']==='1500.50'&&package_row($a)['discount_percent']==='10.00','price and discount saved');
    package_check((int)package_row($b)['is_active']===0,'unchecked Active saved');
    $c=(int)db()->query("SELECT id FROM event_packages WHERE package_name='Bulk New'")->fetchColumn();
    package_check($c>0&&package_row($c)['guest_range']==='10 to 90 guests'&&package_row($c)['discount_reason']==='Test offer','new row fields persisted');
    $before=package_row($a);$valid=package_payload($a);$valid['price']='9999';$invalid=package_payload($b);$invalid['discount_percent']='101';
    package_rejects(fn()=>save_packages_bulk([$a=>$valid,$b=>$invalid],[]),'invalid row rejects the complete save');
    package_check(package_row($a)===$before,'validation failure leaves every row unchanged');
    $stale=package_payload($b);db()->prepare('UPDATE event_packages SET discount_reason=? WHERE id=?')->execute(['Changed elsewhere',$b]);
    package_rejects(fn()=>save_packages_bulk([$a=>$valid,$b=>$stale],[]),'stale row version rejected');
    package_check(package_row($a)===$before&&package_row($b)['discount_reason']==='Changed elsewhere','stale save preserves all current data');
    foreach(['package_name'=>' Leading','guest_min'=>' 1','guest_max'=>'-1','price'=>'-10','discount_percent'=>'100.01','display_order'=>'-1','discount_reason'=>str_repeat('x',256)] as $field=>$value){$bad=package_payload($a);$bad[$field]=$value;package_rejects(fn()=>save_packages_bulk([$a=>$bad],[]),'invalid '.$field.' rejected');}
    $bad=package_payload($a);$bad['guest_min']='150';$bad['guest_max']='100';package_rejects(fn()=>save_packages_bulk([$a=>$bad],[]),'reversed guest range rejected');
    package_rejects(fn()=>save_packages_bulk([999999=>package_payload($a)],[]),'missing package ID rejected');
    package_rejects(fn()=>save_packages_bulk([$a=>package_payload($a)],[$a=>package_edit_version(package_row($a))]),'same row cannot be edited and removed');
    db()->prepare("INSERT INTO event_bookings(booking_reference,client_name,phone,event_date,package_id) VALUES ('BULK-TEST-BOOKING','Fixture customer','9000001234',CURDATE(),?)")->execute([$b]);
    package_rejects(fn()=>save_packages_bulk([$a=>$valid],[$b=>package_edit_version(package_row($b))]),'package used by bookings cannot be removed');
    package_check(package_row($a)===$before&&package_row($b)!==[],'failed removal rolls back accompanying edits');
    $r=save_packages_bulk([$a=>$valid],[$c=>package_edit_version(package_row($c))]);
    package_check($r['updated']===1&&$r['removed']===1&&package_row($c)===[],'one save edits and removes together');
    $route=function(array $post)use($database):array{
        $pipes=[];$process=proc_open([PHP_BINARY,__FILE__,'--action',$database,base64_encode(json_encode($post))],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
        $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($process)!==0||$err!=='')throw new RuntimeException($err.' '.$out);$r=json_decode($out,true);if(!is_array($r))throw new RuntimeException($out);return $r;
    };
    $post=['action'=>'save_packages_bulk','packages'=>[$a=>package_payload($a)],'submission_complete'=>'1'];$post['packages'][$a]['price']='4000';
    $r=$route($post);package_check($r['ok']&&package_row($a)['price']==='4000.00','authenticated single-save action persists changes');
    $post['packages'][$a]=package_payload($a);$post['packages'][$a]['price']='5000';$post['csrf_token']='invalid';$r=$route($post);
    package_check(!$r['ok']&&package_row($a)['price']==='4000.00','invalid CSRF rejected without changes');
    unset($post['csrf_token'],$post['submission_complete']);$r=$route($post);
    package_check(!$r['ok']&&package_row($a)['price']==='4000.00','truncated form rejected without partial save');
    echo 'PASS: '.$checks." package bulk-save checks (isolated database; no external messages).\n";
}finally{
    if(db()->inTransaction())db()->rollBack();db()->exec('USE `anantha`');db()->exec('DROP DATABASE IF EXISTS `'.$database.'`');if(is_file($settings))unlink($settings);putenv('ANANTHA_MAIL_SETTINGS_PATH');
}

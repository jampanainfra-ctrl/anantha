<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../includes/config.php';
if(in_array($argv[1] ?? '',['--action','--public'],true)){
    $database=$argv[2] ?? '';if(!preg_match('/^anantha_spacing_test_[a-f0-9]+$/',$database))exit(2);
    db()->exec('USE `'.$database.'`');$_POST=json_decode(base64_decode($argv[3]),true);$_GET=[];$_FILES=[];$_SERVER['REQUEST_METHOD']='POST';
    if($argv[1]==='--action'){
        session_id('spacing-test-'.bin2hex(random_bytes(8)));require_once __DIR__.'/../includes/admin_auth.php';
        $_SESSION['admin_id']=(int)db()->query('SELECT id FROM admin_users LIMIT 1')->fetchColumn();$_POST['csrf_token']=referral_csrf_token();
        register_shutdown_function(function(){echo json_encode($_SESSION['flash'] ?? []);session_destroy();});require __DIR__.'/../admin/actions.php';
    }else{ob_start();require __DIR__.'/../availability.php';ob_end_clean();echo json_encode(['error'=>$formError,'success'=>$success]);}
    exit;
}
$checks=0;$database='anantha_spacing_test_'.bin2hex(random_bytes(5));
function spacing_check(bool $ok,string $label):void{global $checks;if(!$ok)throw new RuntimeException('FAILED: '.$label);$checks++;}
try{
    foreach([' Name',"\tName","\nName","\u{00A0}Name","\u{2003}Name","\u{FEFF}Name","\u{200B}Name",'   '] as $value)spacing_check(required_leading_space_error(['name'=>$value],['name'=>'Name'])!=='','leading whitespace rejected');
    foreach(['Jai Sai Ram','Name ',"Review\nsecond line",'0','12.50',''] as $value)spacing_check(required_leading_space_error(['value'=>$value],['value'=>'Value'])==='','valid value preserved');
    spacing_check(required_leading_space_error(['name'=>[]],['name'=>'Name'])!=='','non scalar rejected');
    spacing_check(required_leading_space_error(['note'=>' optional'],['name'=>'Name'])==='','optional fields not changed');
    validate_admin_required_spacing('save_payment_tab',['save_pricing'=>'1','amount'=>' 100','discount_type'=>'none']);spacing_check(true,'unused payment amount ignored on pricing-only save');
    foreach([
        ['action'=>'save_payment_tab','discount_type'=>'percent','discount_value'=>' 5'],
        ['action'=>'save_payment_tab','save_pricing'=>'1','electricity_meter_start'=>' 100','electricity_meter_end'=>'110'],
        ['action'=>'save_payment_tab','save_pricing'=>'1','extra_charge_keys'=>['extra'],'extra_charge_qty'=>['extra'=>' 2']],
    ] as $input){try{validate_admin_required_spacing($input['action'],$input);throw new LogicException('Invalid required value accepted');}catch(RuntimeException $e){spacing_check(str_contains($e->getMessage(),'must not start'),'conditional required field rejected');}}
    db()->exec('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');db()->exec('USE `'.$database.'`');
    $schema=preg_replace('/^(CREATE DATABASE[^;]+;|USE\s+anantha;)[\r\n]*/mi','',file_get_contents(__DIR__.'/../database.sql'));if(preg_match('/\bUSE\s+anantha\b/i',$schema))throw new RuntimeException('Unsafe schema');db()->exec($schema);
    $route=function(string $mode,array $post)use($database):array{
        $pipes=[];$process=proc_open([PHP_BINARY,__FILE__,$mode,$database,base64_encode(json_encode($post))],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
        $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($process)!==0 || $err!=='')throw new RuntimeException($err.' '.$out);$result=json_decode($out,true);if(!is_array($result))throw new RuntimeException($out);return $result;
    };
    $cases=[
        ['action'=>'save_booking_details','client_name'=>' Name','phone'=>'9000022222'],
        ['action'=>'save_booking_details','client_name'=>'Name','phone'=>' 9000022222'],
        ['action'=>'save_package','package_name'=>' Package'],
        ['action'=>'save_package','package_name'=>'Package','guest_min'=>' 1'],
        ['action'=>'save_rate_setting','label'=>'Rule','setting_value'=>' 10'],
        ['action'=>'save_term','title'=>'Title','term_text'=>"\nTerms"],
        ['action'=>'save_review','client_name'=>'Name','review_text'=>' Review'],
        ['action'=>'add_gallery','title'=>' Image'],
        ['action'=>'add_blog_image','title'=>' Image'],
        ['action'=>'save_blog_image','title'=>' Image'],
        ['action'=>'save_gallery_category','category_name'=>' Category'],
        ['action'=>'add_penalty','penalty_title'=>' Penalty'],
        ['action'=>'save_payment_tab','amount'=>' 100'],
        ['action'=>'save_referral_settings','usage_limit'=>' 5'],
    ];
    foreach($cases as $post){$result=$route('--action',$post);spacing_check(($result['type'] ?? '')==='error'&&str_contains($result['message'] ?? '','must not start'),'admin raw value rejected: '.$post['action']);}
    $base=['enquire'=>'1','name'=>'Spacing Test','phone'=>'9000022222','email'=>'spacing@example.test','pin_code'=>'530045','event_date'=>date('Y-m-d',strtotime('+60 days')),'event_type'=>'Wedding','guests'=>'50','package_id'=>'1'];
    foreach(['name'=>' Name','phone'=>' 9000022222','email'=>' spacing@example.test','pin_code'=>' 530045','guests'=>' 50'] as $field=>$value){$result=$route('--public',array_replace($base,[$field=>$value]));spacing_check(str_contains($result['error'] ?? '','must not start')&&empty($result['success']),'public raw '.$field.' rejected');}
    spacing_check((int)db()->query('SELECT COUNT(*) FROM event_bookings')->fetchColumn()===0,'invalid enquiries never stored');
    echo 'PASS: '.$checks." required-spacing checks (isolated database).\n";
}finally{if(db()->inTransaction())db()->rollBack();db()->exec('USE `anantha`');db()->exec('DROP DATABASE IF EXISTS `'.$database.'`');}
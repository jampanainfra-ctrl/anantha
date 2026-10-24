<?php
require_once __DIR__.'/_bootstrap.php';
require_once __DIR__.'/../includes/admin_auth.php';
header('Cache-Control: no-store, private');
header('Referrer-Policy: no-referrer');
api_require_db();
if($_SERVER['REQUEST_METHOD']!=='POST')json_response(['ok'=>false,'error'=>'Please use the customer lookup form.'],405);
try{
    require_referral_csrf();
    if(!is_string($_POST['phone'] ?? null) || !is_string($_POST['reference'] ?? ''))throw new RuntimeException('Please enter your mobile number and previous booking reference.');
    $result=returning_customer_lookup((string)($_POST['phone'] ?? ''),(string)($_POST['reference'] ?? ''),admin_user()!==null);
    if(isset($result['customer']))session_regenerate_id(true);
    json_response($result);
}catch(RuntimeException $e){json_response(['ok'=>false,'error'=>$e->getMessage()],422);}
catch(Throwable $e){json_response(['ok'=>false,'error'=>'Customer details could not be loaded. Please try again.'],503);}

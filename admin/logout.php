<?php
require_once __DIR__ . '/../includes/admin_auth.php';
require_admin();
if(($_SERVER['REQUEST_METHOD'] ?? '')!=='POST')redirect('dashboard.php');
try{require_referral_csrf();}catch(RuntimeException $e){$_SESSION['flash']=['type'=>'error','message'=>$e->getMessage()];redirect('dashboard.php');}
session_destroy();
redirect('login.php');

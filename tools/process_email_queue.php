<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../includes/config.php';
try{
    $result=process_mail_queue(max(1,min(100,(int)($argv[1] ?? 20))));
    echo json_encode($result,JSON_PRETTY_PRINT).PHP_EOL;
    exit($result['failed']>0?1:0);
}catch(Throwable $e){fwrite(STDERR,"Email worker could not run. Check the database and mail configuration.\n");exit(1);}